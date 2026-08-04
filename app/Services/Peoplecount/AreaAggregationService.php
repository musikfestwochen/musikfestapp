<?php

declare(strict_types=1);

namespace App\Services\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\IntervalCount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use RuntimeException;

class AreaAggregationService
{
    private const int WINDOW_CHUNK_SIZE = 1440;

    private const int INTERVAL_ROW_PAGE_SIZE = 10_000;

    public function __construct(
        protected AreaService $areaService
    ) {}

    /**
     * Update the aggregated counts for the given area.
     */
    public function updateAggregatedCounts(Area $area): void
    {
        $areaConfigChecksum = $this->prepareAreaForAggregation($area);

        if ($area->assignments->isEmpty()) {
            return;
        }

        $runWatermark = $this->getCurrentDataWatermark($area);
        $resetTimes = $this->getPastResetTimes($area);
        $checkpoint = $this->getAggregationCheckpoint($area, $runWatermark);
        $lastCount = $checkpoint['initial_count'];

        foreach ($this->getAggregationWindowChunks($area, $resetTimes, $checkpoint['recalculate_from']) as $aggregationWindows) {
            $lastCount = $this->calculateAggregatedCountsForWindows($area, $aggregationWindows, $areaConfigChecksum, $lastCount, $runWatermark);
        }

        $this->updateDataWatermark($area, $runWatermark);
    }

    /**
     * Prepare the area for aggregation by loading relationships and cleaning invalid data.
     */
    protected function prepareAreaForAggregation(Area $area): string
    {
        $this->loadAreaRelationships($area);
        $areaConfigChecksum = $this->areaService->calculateChecksum($area);
        $this->deleteInvalidAggregationRows($area, $areaConfigChecksum);

        return $areaConfigChecksum;
    }

    /**
     * Load all necessary relationships for the area.
     */
    protected function loadAreaRelationships(Area $area): void
    {
        $area->load([
            'aggregatedCounts' => fn (HasMany $query) => $query->orderBy('period_end', 'desc'),
            'areaRecurringResets',
            'areaSingleResets',
            'assignments.sensor',
            'event',
        ]);
    }

    protected function deleteInvalidAggregationRows(Area $area, string $areaConfigChecksum): void
    {
        if ($area->aggregatedCounts->isEmpty()) {
            return;
        }

        if ($this->deleteRowsWithInvalidChecksum($area, $areaConfigChecksum) > 0) {
            $area->forceFill(['data_watermark' => null])->save();
        }

        if ($this->deleteRowsWithInvalidWindowSize($area) > 0) {
            $area->forceFill(['data_watermark' => null])->save();
        }
    }

    /**
     * Delete aggregated counts that have a different checksum.
     */
    protected function deleteRowsWithInvalidChecksum(Area $area, string $areaConfigChecksum): int
    {
        $binaryChecksum = hex2bin($areaConfigChecksum);

        return $area->aggregatedCounts()
            ->where('checksum', '!=', $binaryChecksum)
            ->delete();
    }

    /**
     * Delete all aggregated counts if the median window size differs from config.
     */
    protected function deleteRowsWithInvalidWindowSize(Area $area): int
    {
        $counts = $area->aggregatedCounts;
        if ($counts->isEmpty()) {
            return 0;
        }

        $configWindowSize = (float) config('peoplecount.aggregation.granularity_minutes');
        $medianWindowSize = $this->calculateMedianWindowSize($counts);

        if ($medianWindowSize !== $configWindowSize) {
            return $area->aggregatedCounts()->delete();
        }

        return 0;
    }

    /**
     * Calculate the median window size from aggregated counts.
     *
     * @param  Collection<int, AreaAggregatedCount>  $counts
     */
    protected function calculateMedianWindowSize(Collection $counts): float
    {
        /** @var Collection<int, int> $differences */
        $differences = $counts->map(function (AreaAggregatedCount $count) {
            /** @var Carbon $from */
            $from = $count->period_start;
            /** @var Carbon $to */
            $to = $count->period_end;

            return $from->diffInMinutes($to);
        })->sort()->values();

        $count = $differences->count();

        if ($count % 2 === 0) {
            return ($differences->get((int) ($count / 2 - 1)) + $differences->get((int) ($count / 2))) / 2;
        }

        return $differences->get((int) floor($count / 2));
    }

    /**
     * Get reset times that are in the past.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function getPastResetTimes(Area $area): Collection
    {
        $resetTimes = $this->areaService->getAreaResets($area);

        return $resetTimes->filter(fn (array $item) => $item['at']->isPast());
    }

    /**
     * Get window configuration for aggregation.
     *
     * @return array<string, mixed>
     */
    protected function getWindowConfiguration(Area $area): array
    {
        return [
            'windowSize' => (int) config('peoplecount.aggregation.granularity_minutes'),
            'startTime' => $area->event->starts_at,
            'endTime' => $area->event->ends_at,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $resetTimes
     * @return LazyCollection<int, LazyCollection<int, array<string, mixed>>>
     */
    protected function getAggregationWindowChunks(Area $area, Collection $resetTimes, Carbon $recalculateFrom): LazyCollection
    {
        return $this->generateAggregationWindows($area, $resetTimes, $recalculateFrom)
            ->chunk(self::WINDOW_CHUNK_SIZE);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $resetTimes
     * @return LazyCollection<int, array<string, mixed>>
     */
    protected function generateAggregationWindows(Area $area, Collection $resetTimes, Carbon $recalculateFrom): LazyCollection
    {
        $windowConfig = $this->getWindowConfiguration($area);
        $sortedResetTimes = $resetTimes->sortBy('at');

        return LazyCollection::make(function () use ($windowConfig, $sortedResetTimes, $recalculateFrom) {
            $currentTime = $windowConfig['startTime'];

            while ($currentTime < $windowConfig['endTime']) {
                $window = $this->createWindow($currentTime, $windowConfig, $sortedResetTimes);
                $currentTime = $window['end'];

                if (! $window['start']->isPast()) {
                    continue;
                }

                if ($window['start']->lessThan($recalculateFrom)) {
                    continue;
                }

                yield $window;
            }
        });
    }

    /**
     * Create a single aggregation window.
     *
     * @param  array<string, mixed>  $config
     * @param  Collection<int, array<string, mixed>>  $resetTimes
     * @return array<string, mixed>
     */
    protected function createWindow(Carbon $startTime, array $config, Collection $resetTimes): array
    {
        $windowEnd = $this->calculateWindowEnd($startTime, $config['startTime'], $config['endTime'], $config['windowSize'], $resetTimes);
        $resetValue = $this->getResetValueAtWindowStart($startTime, $resetTimes);

        return [
            'start' => $startTime->copy(),
            'end' => $windowEnd,
            'reset_value' => $resetValue,
        ];
    }

    /**
     * Calculate the end time for the current aggregation window.
     *
     * @param  Collection<int, array<string, mixed>>  $resetTimes
     */
    protected function calculateWindowEnd(Carbon $startTime, Carbon $eventStartTime, Carbon $eventEndTime, int $windowSize, Collection $resetTimes): Carbon
    {
        $minutesFromEventStart = (int) $eventStartTime->diffInMinutes($startTime);
        $nextBoundaryMinutes = $minutesFromEventStart - ($minutesFromEventStart % $windowSize) + $windowSize;
        $naturalWindowEnd = $eventStartTime->copy()->addMinutes($nextBoundaryMinutes);

        if ($naturalWindowEnd->greaterThan($eventEndTime)) {
            $naturalWindowEnd = $eventEndTime;
        }

        $resetWithinWindow = $this->findResetWithinWindow($startTime, $naturalWindowEnd, $resetTimes);

        return $resetWithinWindow && $resetWithinWindow['at'] > $startTime
            ? $resetWithinWindow['at']
            : $naturalWindowEnd;
    }

    /**
     * Find a reset that occurs within the current window.
     *
     * @param  Collection<int, array<string, mixed>>  $resetTimes
     * @return array<string, mixed>|null
     */
    protected function findResetWithinWindow(Carbon $startTime, Carbon $windowEnd, Collection $resetTimes): ?array
    {
        return $resetTimes->first(function (array $reset) use ($startTime, $windowEnd): bool {
            return $reset['at']->greaterThan($startTime) && $reset['at']->lessThanOrEqualTo($windowEnd);
        });
    }

    /**
     * Get the reset value if there's a reset at the start of the current window.
     *
     * @param  Collection<int, array<string, mixed>>  $resetTimes
     */
    protected function getResetValueAtWindowStart(Carbon $startTime, Collection $resetTimes): ?int
    {
        $resetAtStart = $resetTimes->first(function (array $reset) use ($startTime) {
            return $reset['at']->eq($startTime);
        });

        return $resetAtStart ? $resetAtStart['reset_value'] : null;
    }

    /**
     * Calculate and store aggregated counts for all windows.
     *
     * @param  iterable<int, array<string, mixed>>  $aggregationWindows
     */
    protected function calculateAggregatedCountsForWindows(Area $area, iterable $aggregationWindows, string $areaConfigChecksum, int $lastCount, ?Carbon $runWatermark = null): int
    {
        $windows = collect($aggregationWindows)->values();
        $netCounts = $this->calculateNetCountsForWindows($area, $windows, $runWatermark);
        $aggregatedRows = collect();
        $binaryChecksum = hex2bin($areaConfigChecksum);

        throw_if($binaryChecksum === false, RuntimeException::class, 'Area config checksum must be valid hexadecimal.');

        foreach ($windows as $index => $window) {
            $lastCount = ($window['reset_value'] ?? $lastCount) + ($netCounts->get($index, 0));

            $aggregatedRows->push([
                'area_id' => $area->id,
                'period_start' => $window['start']->toDateTimeString(),
                'period_end' => $window['end']->toDateTimeString(),
                'count' => $lastCount,
                'checksum' => $binaryChecksum,
            ]);
        }

        $this->writeAggregatedCounts($aggregatedRows);

        return $lastCount;
    }

    /**
     * Compute per-window net deltas for one chunk of planned windows.
     *
     * Replaces the previous temp-window-table grouped SQL join with a streamed
     * scan over interval_counts for the chunk range. The database does one
     * indexed range scan per chunk; PHP matches each row to its containing
     * window (binary search over sorted windows) and its active assignments
     * already loaded on the area. Memory stays bounded by the page size and
     * the planned window count, never by the total interval row count.
     *
     * @param  Collection<int, array<string, mixed>>  $windows
     * @return Collection<int, int>
     */
    protected function calculateNetCountsForWindows(Area $area, Collection $windows, ?Carbon $runWatermark = null): Collection
    {
        if ($windows->isEmpty()) {
            return collect();
        }

        $windowLookup = $this->buildWindowLookup($windows);
        $assignmentsBySensor = $this->buildAssignmentsBySensor($area);

        /** @var array<int, int> $netCounts */
        $netCounts = array_fill(0, $windows->count(), 0);

        if ($assignmentsBySensor->isEmpty()) {
            return collect($netCounts);
        }

        $chunkBounds = $this->getChunkBounds($windows);

        foreach ($this->intervalCountsForChunkQuery($chunkBounds, $assignmentsBySensor->keys(), $runWatermark)->lazyById(self::INTERVAL_ROW_PAGE_SIZE, 'id') as $row) {
            $tsFrom = Date::parse($row->ts_from);
            $windowIndex = $this->findWindowIndexForInterval($tsFrom, $windowLookup);

            if ($windowIndex === null) {
                continue;
            }

            /** @var Collection<int, Assignment> $assignments */
            $assignments = $assignmentsBySensor->get($row->sensor_id, collect());

            foreach ($assignments as $assignment) {
                if (! $this->intervalWithinAssignment($tsFrom, $assignment)) {
                    continue;
                }

                $netCounts[$windowIndex] += $assignment->direction_flipped
                    ? (int) $row->count_out - (int) $row->count_in
                    : (int) $row->count_in - (int) $row->count_out;
            }
        }

        return collect($netCounts);
    }

    /**
     * Build an ordered window lookup for binary search by interval start time.
     *
     * @param  Collection<int, array<string, mixed>>  $windows
     * @return array{starts: array<int, Carbon>, ends: array<int, Carbon>}
     */
    protected function buildWindowLookup(Collection $windows): array
    {
        return [
            'starts' => $windows->map(fn (array $window): Carbon => $window['start'])->values()->all(),
            'ends' => $windows->map(fn (array $window): Carbon => $window['end'])->values()->all(),
        ];
    }

    /**
     * Group assignments by sensor_id so each interval row can find its
     * potentially active assignments with one collection lookup.
     *
     * @return Collection<int, Collection<int, Assignment>>
     */
    protected function buildAssignmentsBySensor(Area $area): Collection
    {
        /** @var Collection<int, Collection<int, Assignment>> $bySensor */
        $bySensor = collect();

        foreach ($area->assignments as $assignment) {
            $sensorId = $assignment->sensor_id;
            $group = $bySensor->get($sensorId, collect());
            $group->push($assignment);
            $bySensor->put($sensorId, $group);
        }

        return $bySensor;
    }

    /**
     * Find the index of the window containing an interval's ts_from.
     *
     * Windows are sorted by start time and contiguous, so binary search for
     * the rightmost window whose start <= ts_from, then verify ts_from < end.
     *
     * @param  array{starts: array<int, Carbon>, ends: array<int, Carbon>}  $lookup
     */
    protected function findWindowIndexForInterval(Carbon $tsFrom, array $lookup): ?int
    {
        $starts = $lookup['starts'];
        $ends = $lookup['ends'];

        $lo = 0;
        $hi = count($starts) - 1;
        $candidate = null;

        while ($lo <= $hi) {
            $mid = (int) floor(($lo + $hi) / 2);

            if ($starts[$mid]->lessThanOrEqualTo($tsFrom)) {
                $candidate = $mid;
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        if ($candidate === null) {
            return null;
        }

        return $tsFrom->lessThan($ends[$candidate]) ? $candidate : null;
    }

    /**
     * Determine whether an interval's ts_from falls within an assignment's
     * active range. Bound semantics match the previous SQL join exactly:
     * active_from <= ts_from < active_to.
     */
    protected function intervalWithinAssignment(Carbon $tsFrom, Assignment $assignment): bool
    {
        return $tsFrom->greaterThanOrEqualTo($assignment->active_from)
            && $tsFrom->lessThan($assignment->active_to);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $windows
     * @return array{start: Carbon, end: Carbon}
     */
    protected function getChunkBounds(Collection $windows): array
    {
        return [
            'start' => $windows->first()['start'],
            'end' => $windows->last()['end'],
        ];
    }

    /**
     * Build the streamed interval_counts query for one chunk.
     *
     * Selects only the columns needed for per-window net computation. Orders
     * by id so lazyById can page safely without offset drift.
     *
     * @param  array{start: Carbon, end: Carbon}  $chunkBounds
     * @param  Collection<int, int>  $sensorIds
     */
    protected function intervalCountsForChunkQuery(array $chunkBounds, Collection $sensorIds, ?Carbon $runWatermark): QueryBuilder
    {
        return DB::table((new IntervalCount)->getTable())
            ->select(['id', 'sensor_id', 'ts_from', 'count_in', 'count_out', 'received_at'])
            ->whereIn('sensor_id', $sensorIds->all())
            ->where('ts_from', '>=', $chunkBounds['start'])
            ->where('ts_from', '<', $chunkBounds['end'])
            ->when($runWatermark instanceof Carbon, fn (QueryBuilder $query): QueryBuilder => $query->where('received_at', '<=', $runWatermark))
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, array{area_id: int, period_start: string, period_end: string, count: int, checksum: string}>  $rows
     */
    protected function writeAggregatedCounts(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        AreaAggregatedCount::query()->upsert(
            $rows->all(),
            ['area_id', 'period_start', 'period_end'],
            ['count', 'checksum']
        );
    }

    /**
     * @return array{recalculate_from: Carbon, initial_count: int}
     */
    protected function getAggregationCheckpoint(Area $area, ?Carbon $runWatermark = null): array
    {
        $latestCounts = $area->aggregatedCounts()
            ->latest('period_end')
            ->limit(2)
            ->get(['id', 'area_id', 'period_start', 'period_end', 'count']);

        $previousCount = $latestCounts->get(1);
        $recalculateFrom = $latestCounts->get(0)->period_start ?? $area->event->starts_at;
        $initialCount = $previousCount ? $previousCount->count : 0;
        $lateRecalculateFrom = $area->exists ? $this->getLateArrivalRecalculateFrom($area, $runWatermark) : null;

        if ($lateRecalculateFrom && $lateRecalculateFrom->lessThan($recalculateFrom)) {
            $recalculateFrom = $lateRecalculateFrom;
            $initialCount = $this->getInitialCountBefore($area, $recalculateFrom);
        }

        return [
            'recalculate_from' => $recalculateFrom,
            'initial_count' => $initialCount,
        ];
    }

    protected function getLateArrivalRecalculateFrom(Area $area, ?Carbon $runWatermark): ?Carbon
    {
        if (! $area->data_watermark || ! $runWatermark) {
            return null;
        }

        $lateTsFrom = $this->areaIntervalCountsQuery($area)
            ->where('interval_counts.received_at', '>', $area->data_watermark)
            ->where('interval_counts.received_at', '<=', $runWatermark)
            ->min('interval_counts.ts_from');

        if (! $lateTsFrom) {
            return null;
        }

        return $area->aggregatedCounts()
            ->where('period_start', '<=', $lateTsFrom)
            ->where('period_end', '>', $lateTsFrom)
            ->latest('period_start')
            ->value('period_start') ?? Date::parse($lateTsFrom);
    }

    protected function getInitialCountBefore(Area $area, Carbon $recalculateFrom): int
    {
        return (int) ($area->aggregatedCounts()
            ->where('period_end', '<=', $recalculateFrom)
            ->latest('period_end')
            ->value('count') ?? 0);
    }

    protected function getCurrentDataWatermark(Area $area): ?Carbon
    {
        $watermark = $this->areaIntervalCountsQuery($area)->max('interval_counts.received_at');

        return $watermark ? Date::parse($watermark) : null;
    }

    protected function updateDataWatermark(Area $area, ?Carbon $runWatermark): void
    {
        $watermark = $runWatermark;

        if (! $watermark instanceof Carbon) {
            return;
        }

        $area->forceFill(['data_watermark' => $watermark])->save();
    }

    protected function areaIntervalCountsQuery(Area $area): QueryBuilder
    {
        return DB::table('peoplecount_assignments as assignments')
            ->join((new IntervalCount)->getTable().' as interval_counts', function (JoinClause $join): void {
                $join->on('interval_counts.sensor_id', '=', 'assignments.sensor_id')
                    ->whereColumn('interval_counts.ts_from', '>=', 'assignments.active_from')
                    ->whereColumn('interval_counts.ts_from', '<', 'assignments.active_to');
            })
            ->where('assignments.area_id', $area->id)
            ->where('interval_counts.ts_from', '>=', $area->event->starts_at)
            ->where('interval_counts.ts_from', '<', $area->event->ends_at)
            ->whereNull('assignments.deleted_at');
    }

    /**
     * Get aggregated count series for active areas in an organization within a time window.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveAreaAggregatedCountsHistory(Organization $organization, Carbon $from, Carbon $to): array
    {
        $timezone = (string) config('app.timezone');
        $now = Date::now()->setTimezone($timezone);
        $from = $from->copy()->setTimezone($timezone);
        $to = $to->copy()->setTimezone($timezone);

        $areas = Area::query()
            ->whereHas('event', function (Builder $query) use ($organization, $now) {
                $query->where('organization_id', $organization->id)
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>=', $now);
            })
            ->with([
                'event:id,name',
                'aggregatedCounts' => function (Relation $query) use ($from, $to): void {
                    $query->where('period_start', '>=', $from)
                        ->where('period_start', '<', $to)
                        ->orderBy('period_start', 'asc')
                        ->select(['id', 'area_id', 'count', 'period_start', 'period_end']);
                },
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'event_id']);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Area> $areas */
        return $areas->map(function (Area $area) use ($now): array {
            return [
                'id' => $area->id,
                'name' => $area->name,
                'event_name' => $area->event->name,
                'data' => $area->aggregatedCounts->map(function (AreaAggregatedCount $count) use ($now): array {
                    return [
                        'time' => $count->period_end->greaterThan($now)
                            ? $now->toIso8601String()
                            : $count->period_end->toIso8601String(),
                        'count' => $count->count,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Get the latest aggregated counts for active areas in an organization.
     *
     * @return array<int, array<string, mixed>>
     *
     * @todo Optimize with caching
     */
    public function getActiveAreaAggregatedCounts(Organization $organization): array
    {
        $cache_time = 5;
        $cacheKey = 'org_active_area_counts:'.$organization->id;

        return Cache::remember($cacheKey, now()->addSeconds($cache_time), function () use ($organization): array {
            $timezone = (string) config('app.timezone');
            $now = Date::now()->setTimezone($timezone);
            $oneHourAgo = $now->copy()->subHour();

            $latestCountQuery = AreaAggregatedCount::query()
                ->whereColumn('area_id', 'peoplecount_areas.id')
                ->latest('period_end')
                ->limit(1);
            $oneHourAgoCountQuery = AreaAggregatedCount::query()
                ->whereColumn('area_id', 'peoplecount_areas.id')
                ->where('period_end', '<=', $oneHourAgo)
                ->latest('period_end')
                ->limit(1);

            $areas = Area::query()
                ->select(['peoplecount_areas.id', 'peoplecount_areas.name', 'peoplecount_areas.event_id'])
                ->addSelect([
                    'latest_count' => (clone $latestCountQuery)->select('count'),
                    'latest_period_end' => (clone $latestCountQuery)->select('period_end'),
                    'one_hour_ago_count' => (clone $oneHourAgoCountQuery)->select('count'),
                    'one_hour_ago_period_end' => (clone $oneHourAgoCountQuery)->select('period_end'),
                ])
                ->whereHas('event', function (Builder $query) use ($organization, $now) {
                    $query->where('organization_id', $organization->id)
                        ->where('starts_at', '<=', $now)
                        ->where('ends_at', '>=', $now);
                })
                ->with('event:id,name')
                ->get();

            /** @var \Illuminate\Database\Eloquent\Collection<int, Area> $areas */
            return $areas->map(function (Area $area) use ($now): array {
                $latestCount = $area->getAttribute('latest_count');
                $latestPeriodEnd = $area->getAttribute('latest_period_end');
                $oneHourAgoCount = $area->getAttribute('one_hour_ago_count');
                $oneHourAgoPeriodEnd = $area->getAttribute('one_hour_ago_period_end');

                $netChange = null;
                $netChangeTimeAgo = null;

                if ($latestCount !== null && $latestPeriodEnd !== null && $oneHourAgoCount !== null && $oneHourAgoPeriodEnd !== null) {
                    $netChange = (int) $latestCount - (int) $oneHourAgoCount;
                    $netChangeTimeAgo = Date::parse((string) $latestPeriodEnd)
                        ->diffForHumans(Date::parse((string) $oneHourAgoPeriodEnd), ['syntax' => true]);
                }

                $lastUpdated = null;

                if ($latestPeriodEnd !== null) {
                    $latestPeriodEnd = Date::parse((string) $latestPeriodEnd);
                    $lastUpdated = ($latestPeriodEnd->greaterThan($now) ? $now : $latestPeriodEnd)->toIso8601String();
                }

                return [
                    'id' => $area->id,
                    'name' => $area->name,
                    'event_name' => $area->event->name,
                    'count' => $latestCount !== null ? (int) $latestCount : 0,
                    'net_change' => $netChange,
                    'net_change_time_ago' => $netChangeTimeAgo,
                    'last_updated' => $lastUpdated,
                ];
            })->all();
        });

    }
}
