<?php

declare(strict_types=1);

namespace App\Services\StageSafety;

use App\Enums\StageSafety\SensorHealthStatus;
use App\Models\Organization;
use App\Models\StageSafety\Reading;
use App\Models\StageSafety\Sensor;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;

class MonitoringService
{
    /**
     * @return array{generated_at: string, sensors: list<array<string, mixed>>}
     */
    public function currentWind(Organization $organization): array
    {
        $now = Date::now();
        $sensors = array_values($this->activeSensorsWithLatestReadings($organization)
            ->filter(fn (Sensor $sensor): bool => $this->status($sensor, $now) === SensorHealthStatus::Fresh)
            ->map(fn (Sensor $sensor): array => $this->currentSensorPayload($sensor, $now))
            ->values()
            ->all());

        return [
            'generated_at' => $now->toIso8601String(),
            'sensors' => $sensors,
        ];
    }

    /**
     * @return array{
     *     generated_at: string,
     *     total: int,
     *     all_fresh: bool,
     *     fresh: list<array<string, mixed>>,
     *     stale: list<array<string, mixed>>,
     *     never_seen: list<array<string, mixed>>
     * }
     */
    public function sensorHealth(Organization $organization): array
    {
        $now = Date::now();
        $groups = [
            SensorHealthStatus::Fresh->value => [],
            SensorHealthStatus::Stale->value => [],
            SensorHealthStatus::NeverSeen->value => [],
        ];

        foreach ($this->activeSensorsWithLatestReadings($organization) as $sensor) {
            $status = $this->status($sensor, $now);
            $groups[$status->value][] = [
                ...$this->sensorPayload($sensor),
                'status' => $status->value,
                'latest_observed_at' => $this->latestObservedAt($sensor)?->toIso8601String(),
            ];
        }

        $total = array_sum(array_map(count(...), $groups));

        return [
            'generated_at' => $now->toIso8601String(),
            'total' => $total,
            'all_fresh' => $total > 0 && count($groups[SensorHealthStatus::Fresh->value]) === $total,
            'fresh' => $groups[SensorHealthStatus::Fresh->value],
            'stale' => $groups[SensorHealthStatus::Stale->value],
            'never_seen' => $groups[SensorHealthStatus::NeverSeen->value],
        ];
    }

    /**
     * @return array{
     *     generated_at: string,
     *     from: string,
     *     to: string,
     *     sensors: list<array{sensor: array<string, mixed>, readings: list<array<string, mixed>>}>
     * }
     */
    public function windHistory(Organization $organization, CarbonInterface $from, CarbonInterface $to): array
    {
        $sensors = array_values($organization->stageSafetySensors()
            ->whereNull('archived_at')
            ->whereHas('readings', fn (Builder $query): Builder => $query
                ->whereBetween('observed_at', [$from, $to]))
            ->with(['readings' => function (Relation $relation) use ($from, $to): void {
                $relation->getQuery()
                    ->whereBetween('observed_at', [$from, $to])
                    ->oldest('observed_at')
                    ->orderBy('kind');
            }])
            ->orderBy('id')
            ->get()
            ->map(fn (Sensor $sensor): array => [
                'sensor' => $this->sensorPayload($sensor),
                'readings' => array_values($sensor->readings
                    ->map(fn (Reading $reading): array => $this->historyReadingPayload($reading))
                    ->values()
                    ->all()),
            ])
            ->values()
            ->all());

        return [
            'generated_at' => Date::now()->toIso8601String(),
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'sensors' => $sensors,
        ];
    }

    /**
     * @return array{generated_at: string, current: array<string, mixed>, history: array<string, mixed>}
     */
    public function sensorMonitoring(Organization $organization, Sensor $sensor, CarbonInterface $from, CarbonInterface $to): array
    {
        throw_if(
            $sensor->organization_id !== $organization->id,
            AuthorizationException::class,
            'You are not authorized to monitor this sensor.',
        );

        $now = Date::now();
        $sensor->loadMissing(['latestWindAverage', 'latestWindGust']);
        $readings = $sensor->readings()
            ->whereBetween('observed_at', [$from, $to])
            ->oldest('observed_at')
            ->orderBy('kind')
            ->get()
            ->map(fn (Reading $reading): array => $this->historyReadingPayload($reading))
            ->values()
            ->all();

        return [
            'generated_at' => $now->toIso8601String(),
            'current' => $this->currentSensorPayload($sensor, $now),
            'history' => [
                'generated_at' => $now->toIso8601String(),
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'sensors' => [[
                    'sensor' => $this->sensorPayload($sensor),
                    'readings' => array_values($readings),
                ]],
            ],
        ];
    }

    public function status(Sensor $sensor, ?CarbonInterface $now = null): SensorHealthStatus
    {
        if ($sensor->archived_at !== null) {
            return SensorHealthStatus::Archived;
        }

        $sensor->loadMissing(['latestWindAverage', 'latestWindGust']);
        $latestObservedAt = $this->latestObservedAt($sensor);

        if (! $latestObservedAt instanceof CarbonInterface) {
            return SensorHealthStatus::NeverSeen;
        }

        $now ??= Date::now();

        return $this->isFresh($latestObservedAt, $sensor, $now)
            ? SensorHealthStatus::Fresh
            : SensorHealthStatus::Stale;
    }

    /**
     * @return Collection<int, Sensor>
     */
    protected function activeSensorsWithLatestReadings(Organization $organization): Collection
    {
        return $organization->stageSafetySensors()
            ->whereNull('archived_at')
            ->with(['latestWindAverage', 'latestWindGust'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function currentSensorPayload(Sensor $sensor, CarbonInterface $now): array
    {
        return [
            'sensor' => $this->sensorPayload($sensor),
            'status' => $this->status($sensor, $now)->value,
            'latest_observed_at' => $this->latestObservedAt($sensor)?->toIso8601String(),
            'wind_average' => $this->currentReadingPayload($sensor, $sensor->latestWindAverage, $now),
            'wind_gust' => $this->currentReadingPayload($sensor, $sensor->latestWindGust, $now),
        ];
    }

    /**
     * @return array{id: int, identifier: string, name: string|null, location: string|null, stale_after_seconds: int}
     */
    protected function sensorPayload(Sensor $sensor): array
    {
        return [
            'id' => $sensor->id,
            'identifier' => $sensor->identifier,
            'name' => $sensor->name,
            'location' => $sensor->location,
            'stale_after_seconds' => $sensor->stale_after_seconds,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function currentReadingPayload(Sensor $sensor, ?Reading $reading, CarbonInterface $now): ?array
    {
        if (! $reading instanceof Reading) {
            return null;
        }

        $status = $this->isFresh($reading->observed_at, $sensor, $now)
            ? SensorHealthStatus::Fresh
            : SensorHealthStatus::Stale;

        return [
            ...$this->historyReadingPayload($reading),
            'status' => $status->value,
            'received_at' => $reading->received_at->toIso8601String(),
            'receipt_delay_seconds' => (int) $reading->observed_at->diffInSeconds($reading->received_at, false),
        ];
    }

    /**
     * @return array{kind: string, value: float, unit: string, observed_at: string, window_seconds: int|null}
     */
    protected function historyReadingPayload(Reading $reading): array
    {
        return [
            'kind' => $reading->kind->value,
            'value' => $reading->value,
            'unit' => $reading->unit,
            'observed_at' => $reading->observed_at->toIso8601String(),
            'window_seconds' => $reading->window_seconds,
        ];
    }

    protected function latestObservedAt(Sensor $sensor): ?CarbonInterface
    {
        $averageObservedAt = $sensor->latestWindAverage?->observed_at;
        $gustObservedAt = $sensor->latestWindGust?->observed_at;

        if ($averageObservedAt === null) {
            return $gustObservedAt;
        }

        if ($gustObservedAt === null) {
            return $averageObservedAt;
        }

        return $averageObservedAt->getTimestamp() >= $gustObservedAt->getTimestamp()
            ? $averageObservedAt
            : $gustObservedAt;
    }

    protected function isFresh(CarbonInterface $observedAt, Sensor $sensor, CarbonInterface $now): bool
    {
        $observedTimestamp = $observedAt->getTimestamp();
        $nowTimestamp = $now->getTimestamp();

        return $observedTimestamp <= $nowTimestamp
            && $observedTimestamp >= $nowTimestamp - $sensor->stale_after_seconds;
    }
}
