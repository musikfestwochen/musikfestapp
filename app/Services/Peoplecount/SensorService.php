<?php

declare(strict_types=1);

namespace App\Services\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SensorService
{
    const string SENSOR_TOKEN_NAME = 'peoplecount_sensor_token';

    /**
     * @return Collection<int, Sensor>
     */
    public function getSensors(bool $onlyArchived = false): Collection
    {
        $currentOrgId = getPermissionsOrgId();
        $query = Sensor::query();

        if ($currentOrgId !== GLOBAL_ORG_ID) {
            $query->where('organization_id', $currentOrgId);
        }

        $onlyArchived
            ? $query->whereNotNull('archived_at')
            : $query->whereNull('archived_at');

        return $query->withExists(['tokens as has_active_token' => function (Builder $query): void {
            $query->where('name', self::SENSOR_TOKEN_NAME);
        }])->get();
    }

    /**
     * @return Collection<int, Sensor>
     */
    public function getAssignableSensorsForOrganization(Organization $organization): Collection
    {
        $ownedSensors = $organization->sensors()
            ->whereNull('archived_at')
            ->get();

        $borrowedSensors = $organization->borrowedSensorShares()
            ->where('ends_at', '>=', Date::now())
            ->with(['sensor.organization'])
            ->get()
            ->pluck('sensor')
            ->filter(fn (?Sensor $sensor): bool => $sensor instanceof Sensor && $sensor->archived_at === null);

        return $ownedSensors->concat($borrowedSensors)->unique('id')->values();
    }

    /**
     * Get assignable sensors for editing an assignment, including the assignment's
     * current sensor even if archived or outside share windows.
     *
     * @return Collection<int, Sensor>
     */
    public function getAssignableSensorsForAssignmentEdit(Organization $organization, Assignment $assignment): Collection
    {
        $sensors = $this->getAssignableSensorsForOrganization($organization);

        if ($assignment->sensor && $sensors->doesntContain('id', $assignment->sensor->id)) {
            $sensors->push($assignment->sensor);
        }

        return $sensors->unique('id')->values();
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
     * @return array{last_updated: string, total: int, all_healthy: bool, healthy: array<int, array<string, mixed>>, suspicious: array<int, array<string, mixed>>, unhealthy: array<int, array<string, mixed>>}
     */
    public function getAssignedSensorsHealthStatus(Organization $organization): array
    {
        $cacheTtlSeconds = 5;
        $cacheKey = 'peoplecount:sensor_health:org:'.$organization->id;

        return Cache::remember($cacheKey, now()->addSeconds($cacheTtlSeconds), function () use ($organization): array {
            $timezone = (string) config('app.timezone');
            $now = Date::now()->setTimezone($timezone);
            $recentThreshold = $now->copy()->subMinutes(2);

            // Find sensors currently assigned to this organization's events.
            $assignedSensorIds = Assignment::query()
                ->whereBetween('active_from', ['1900-01-01', $now])
                ->where('active_to', '>=', $now)
                ->whereHas('event', function (Builder $query) use ($organization): void {
                    $query->where('organization_id', $organization->id);
                })
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

            // Load sensors that are currently assigned within the organization's events
            $sensors = Sensor::query()
                ->whereIn('id', $assignedSensorIds)
                ->get(['id', 'vendor', 'model', 'serial', 'name']);

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
                $isRecent = $latest && $latest->ts_to->greaterThanOrEqualTo($recentThreshold);
                $anyNonZero = $counts->contains(function (IntervalCount $c): bool {
                    return ($c->count_in ?? 0) > 0 || ($c->count_out ?? 0) > 0;
                });

                $sensorPayload = [
                    'id' => $sensor->id,
                    'serial' => $sensor->serial,
                    'vendor' => $sensor->vendor,
                    'model' => $sensor->model,
                    'name' => $sensor->name,
                    'latest_ts' => $latest ? $latest->ts_to->toIso8601String() : null,
                    'interval_counts' => $counts->map(function (IntervalCount $c): array {
                        return [
                            'ts_from' => $c->ts_from->toIso8601String(),
                            'ts_to' => $c->ts_to->toIso8601String(),
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
     * @return array{sensor: Sensor, token: string}
     */
    public function createWithToken(array $attributes): array
    {
        return DB::transaction(function () use ($attributes): array {
            $sensor = Sensor::query()->create($attributes);

            return [
                'sensor' => $sensor,
                'token' => $this->issueToken($sensor),
            ];
        });
    }

    /**
     * Create or regenerate the peoplecount_sensor_token for a sensor.
     * Deletes any existing token with the same name before creating a new one.
     *
     * @return string The plain text token
     */
    public function createOrRegenerateToken(Sensor $sensor): string
    {
        $this->verifySensorManagedByCurrentOrganization($sensor);
        $this->ensureSensorIsActive($sensor);

        return DB::transaction(function () use ($sensor): string {
            $sensor->tokens()->where('name', self::SENSOR_TOKEN_NAME)->delete();

            return $this->issueToken($sensor);
        });
    }

    public function revokeTokens(Sensor $sensor): void
    {
        $this->verifySensorManagedByCurrentOrganization($sensor);

        $sensor->tokens()->delete();
    }

    public function archive(Sensor $sensor): Sensor
    {
        $this->verifySensorManagedByCurrentOrganization($sensor);

        return DB::transaction(function () use ($sensor): Sensor {
            $sensor->tokens()->delete();
            $sensor->update(['archived_at' => Date::now()]);

            return $sensor;
        });
    }

    public function unarchive(Sensor $sensor): Sensor
    {
        $this->verifySensorManagedByCurrentOrganization($sensor);

        $sensor->update(['archived_at' => null]);

        return $sensor;
    }

    public function delete(Sensor $sensor): void
    {
        $this->verifySensorManagedByCurrentOrganization($sensor);

        throw_if($sensor->assignments()->exists(), ValidationException::withMessages([
            'sensor_id' => 'This sensor cannot be deleted because it is used by assignments.',
        ]));

        DB::transaction(function () use ($sensor): void {
            $sensor->tokens()->delete();
            $sensor->delete();
        });
    }

    public function verifySensorManagedByCurrentOrganization(Sensor $sensor): void
    {
        $currentOrgId = getPermissionsOrgId();

        if ($currentOrgId === GLOBAL_ORG_ID) {
            return;
        }

        throw_if(
            $sensor->organization_id !== $currentOrgId,
            AuthorizationException::class,
            'You are not authorized to manage this sensor.'
        );
    }

    protected function issueToken(Sensor $sensor): string
    {
        $token = $sensor->createToken(self::SENSOR_TOKEN_NAME)->plainTextToken;
        $parts = explode('|', $token, 2);

        return $parts[1] ?? $token;
    }

    protected function ensureSensorIsActive(Sensor $sensor): void
    {
        throw_if($sensor->archived_at !== null, ValidationException::withMessages([
            'sensor' => 'Archived sensors cannot receive API tokens.',
        ]));
    }
}
