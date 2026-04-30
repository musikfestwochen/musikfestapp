<?php

namespace App\Services\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

class SensorService
{
    const string SENSOR_TOKEN_NAME = 'peoplecount_sensor_token';

    /**
     * @return Collection<int, Sensor>
     */
    public function getSensors(): Collection
    {
        $currentOrgId = getPermissionsOrgId();
        $query = Sensor::query();

        if ($currentOrgId !== GLOBAL_ORG_ID) {
            $query->where('organization_id', $currentOrgId);
        }

        return $query->get();
    }

    /**
     * Get health status for currently assigned sensors in an organization.
     *
     * A sensor is healthy if:
     * - The latest interval count is < 2 minutes old (based on ts_to), and
     * - At least one of the last 10 interval counts has a non-zero in/out value.
     * If recent (< 2 minutes) but all last 10 are zero => suspicious.
     * If not recent => unhealthy.
     *
     * @pest-mutate-ignore
     *
     * @return array{last_updated: string, total: int, all_healthy: bool, healthy: array<int, array<string, mixed>>, suspicious: array<int, array<string, mixed>>, unhealthy: array<int, array<string, mixed>>}
     */
    public function getAssignedSensorsHealthStatus(Organization $organization): array
    {
        $cacheTtlSeconds = 5;
        $cacheKey = 'peoplecount:sensor_health:org:'.$organization->id;

        return Cache::remember($cacheKey, now()->addSeconds($cacheTtlSeconds), function () use ($organization): array {
            $now = Date::now()->setTimezone('UTC');
            $recentThreshold = $now->copy()->subMinutes(2);

            // Find sensors that are currently assigned somewhere within an active assignment window
            $assignedSensorIds = Assignment::query()
                ->whereBetween('active_from', ['1900-01-01', $now])
                ->where('active_to', '>=', $now)
                ->pluck('sensor_id')
                ->unique()
                ->values();

            if ($assignedSensorIds->isEmpty()) {
                return [
                    'last_updated' => $now->toIso8601String(),
                    'total' => 0,
                    'all_healthy' => true,
                    'healthy' => [],
                    'suspicious' => [],
                    'unhealthy' => [],
                ];
            }

            // Load sensors for the organization and that are currently assigned
            $sensors = Sensor::query()
                ->whereIn('id', $assignedSensorIds)
                ->where('organization_id', $organization->id)
                ->get(['id', 'vendor', 'model', 'serial']);

            $healthy = [];
            $suspicious = [];
            $unhealthy = [];

            foreach ($sensors as $sensor) {
                // Fetch last 10 interval counts ordered by ts_to desc
                /** @var Collection<int, IntervalCount> $counts */
                $counts = IntervalCount::query()
                    ->where('sensor_id', $sensor->id)
                    ->orderByDesc('ts_to')
                    ->limit(10)
                    ->get(['id', 'sensor_id', 'ts_from', 'ts_to', 'count_in', 'count_out']);

                $latest = $counts->first();
                $isRecent = $latest && Date::parse($latest->ts_to)->greaterThanOrEqualTo($recentThreshold);
                $anyNonZero = $counts->contains(function (IntervalCount $c): bool {
                    return ($c->count_in ?? 0) > 0 || ($c->count_out ?? 0) > 0;
                });

                $sensorPayload = [
                    'id' => $sensor->id,
                    'serial' => $sensor->serial,
                    'vendor' => $sensor->vendor,
                    'model' => $sensor->model,
                    'latest_ts' => $latest ? Date::parse($latest->ts_to)->toIso8601String() : null,
                    'interval_counts' => $counts->map(function (IntervalCount $c): array {
                        return [
                            'ts_from' => Date::parse($c->ts_from)->toIso8601String(),
                            'ts_to' => Date::parse($c->ts_to)->toIso8601String(),
                            'count_in' => (int) $c->count_in,
                            'count_out' => (int) $c->count_out,
                        ];
                    })->all(),
                ];

                if ($isRecent) {
                    if ($anyNonZero) {
                        $healthy[] = $sensorPayload;
                    } else {
                        $suspicious[] = $sensorPayload;
                    }
                } else {
                    $unhealthy[] = $sensorPayload;
                }
            }

            $total = count($healthy) + count($suspicious) + count($unhealthy);

            return [
                'last_updated' => $now->toIso8601String(),
                'total' => $total,
                'all_healthy' => $total > 0 && count($healthy) === $total,
                'healthy' => $healthy,
                'suspicious' => $suspicious,
                'unhealthy' => $unhealthy,
            ];
        });
    }

    /**
     * Create a new sensor and generate its API token.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createWithToken(array $attributes): Sensor
    {
        $sensor = Sensor::query()->create($attributes);
        $token = $this->createOrRegenerateToken($sensor);
        $sensor->api_token = $token;
        $sensor->save();

        return $sensor;
    }

    /**
     * Create or regenerate the peoplecount_sensor_token for a sensor.
     * Deletes any existing token with the same name before creating a new one.
     *
     * @return string The plain text token
     */
    public function createOrRegenerateToken(Sensor $sensor): string
    {
        // Delete existing token(s) with the same name
        $sensor->tokens()->where('name', self::SENSOR_TOKEN_NAME)->delete();
        // Create new token and return plain text
        $token = $sensor->createToken(self::SENSOR_TOKEN_NAME);

        // TODO: Storing token in plaintext, revisit if API becomes sensitive
        // The token is formatted as <id>|<token>, so we only want the token part
        $parts = explode('|', $token->plainTextToken, 2);

        return $parts[1] ?? $token->plainTextToken;
    }
}
