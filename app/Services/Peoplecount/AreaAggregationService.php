<?php

declare(strict_types=1);

namespace App\Services\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\Peoplecount\IntervalCount;
use Exception;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use RuntimeException;

class AreaAggregationService
{
    private const int WINDOW_CHUNK_SIZE = 1440;

    private const string TEMP_WINDOW_TABLE = 'temp_peoplecount_aggregation_windows';

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

        $this->deleteRowsWithInvalidChecksum($area, $areaConfigChecksum);
        $this->deleteRowsWithInvalidWindowSize($area);
    }

    /**
     * Delete aggregated counts that have a different checksum.
     */
    protected function deleteRowsWithInvalidChecksum(Area $area, string $areaConfigChecksum): void
    {
        $binaryChecksum = hex2bin($areaConfigChecksum);

        $area->aggregatedCounts()
            ->where('checksum', '!=', $binaryChecksum)
            ->delete();
    }

    /**
     * Delete all aggregated counts if the median window size differs from config.
     */
    protected function deleteRowsWithInvalidWindowSize(Area $area): void
    {
        $counts = $area->aggregatedCounts;
        if ($counts->isEmpty()) {
            return;
        }

        $configWindowSize = (float) config('peoplecount.aggregation.granularity_minutes');
        $medianWindowSize = $this->calculateMedianWindowSize($counts);

        if ($medianWindowSize !== $configWindowSize) {
            $area->aggregatedCounts()->get()->each->delete();
        }
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
    protected function getAggregationWindowChunks(Area $area, Collection $resetTimes, ?Carbon $recalculateFrom): LazyCollection
    {
        return $this->generateAggregationWindows($area, $resetTimes, $recalculateFrom)
            ->chunk(self::WINDOW_CHUNK_SIZE);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $resetTimes
     * @return LazyCollection<int, array<string, mixed>>
     */
    protected function generateAggregationWindows(Area $area, Collection $resetTimes, ?Carbon $recalculateFrom): LazyCollection
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

                if ($recalculateFrom && $window['start']->lessThan($recalculateFrom)) {
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
     * @param  Collection<int, array<string, mixed>>  $windows
     * @return Collection<int, int>
     */
    protected function calculateNetCountsForWindows(Area $area, Collection $windows, ?Carbon $runWatermark = null): Collection
    {
        if ($windows->isEmpty()) {
            return collect();
        }

        $this->replaceTemporaryWindows($windows);

        return DB::table(self::TEMP_WINDOW_TABLE.' as windows')
            ->leftJoin('peoplecount_assignments as assignments', function (JoinClause $join) use ($area): void {
                $join->where('assignments.area_id', $area->id)
                    ->whereNull('assignments.deleted_at')
                    ->whereColumn('assignments.active_from', '<=', 'windows.period_end')
                    ->whereColumn('assignments.active_to', '>=', 'windows.period_start');
            })
            ->leftJoin('peoplecount_interval_counts as interval_counts', function (JoinClause $join) use ($runWatermark): void {
                $join->on('interval_counts.sensor_id', '=', 'assignments.sensor_id')
                    ->whereColumn('interval_counts.ts_from', '>=', 'windows.period_start')
                    ->whereColumn('interval_counts.ts_from', '<', 'windows.period_end')
                    ->whereColumn('interval_counts.ts_from', '>=', 'assignments.active_from')
                    ->whereColumn('interval_counts.ts_from', '<', 'assignments.active_to');

                if ($runWatermark instanceof Carbon) {
                    $join->where('interval_counts.received_at', '<=', $runWatermark);
                }
            })
            ->select('windows.window_index')
            ->selectRaw('COALESCE(SUM(CASE WHEN assignments.direction_flipped = 1 THEN CAST(interval_counts.count_out AS SIGNED) - CAST(interval_counts.count_in AS SIGNED) ELSE CAST(interval_counts.count_in AS SIGNED) - CAST(interval_counts.count_out AS SIGNED) END), 0) as net_count')
            ->groupBy('windows.window_index')
            ->orderBy('windows.window_index')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->window_index => (int) $row->net_count]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $windows
     */
    protected function replaceTemporaryWindows(Collection $windows): void
    {
        DB::statement('CREATE TEMPORARY TABLE IF NOT EXISTS '.self::TEMP_WINDOW_TABLE.' (window_index INTEGER PRIMARY KEY, period_start DATETIME NOT NULL, period_end DATETIME NOT NULL)');
        DB::table(self::TEMP_WINDOW_TABLE)->delete();

        DB::table(self::TEMP_WINDOW_TABLE)->insert($windows->map(fn (array $window, int $index): array => [
            'window_index' => $index,
            'period_start' => $window['start']->toDateTimeString(),
            'period_end' => $window['end']->toDateTimeString(),
        ])->all());
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
     * @return array{recalculate_from: Carbon|null, initial_count: int}
     */
    protected function getAggregationCheckpoint(Area $area, ?Carbon $runWatermark = null): array
    {
        $latestCounts = $area->aggregatedCounts()
            ->latest('period_end')
            ->limit(2)
            ->get(['id', 'area_id', 'period_start', 'period_end', 'count']);

        $previousCount = $latestCounts->get(1);
        $recalculateFrom = $latestCounts->get(0)?->period_start;
        $initialCount = $previousCount ? $previousCount->count : 0;
        $lateRecalculateFrom = $area->exists ? $this->getLateArrivalRecalculateFrom($area, $runWatermark) : null;

        if ($lateRecalculateFrom && (! $recalculateFrom || $lateRecalculateFrom->lessThan($recalculateFrom))) {
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

            // Get all events and areas in a single query
            $areas = Area::query()
                ->whereHas('event', function (Builder $query) use ($organization, $now) {
                    $query->where('organization_id', $organization->id)
                        ->where('starts_at', '<=', $now)
                        ->where('ends_at', '>=', $now);
                })
                ->with([
                    'event:id,name',
                    'aggregatedCounts' => function (Relation $query) {
                        $query->select(['id', 'area_id', 'count', 'period_end'])
                            ->latest('period_end');
                    },
                ])
                ->get(['id', 'name', 'event_id']);

            /** @var \Illuminate\Database\Eloquent\Collection<int, Area> $areas */
            return $areas->map(function (Area $area) use ($now, $oneHourAgo): array {
                // Get the latest count and find the last count that ended at least one hour ago
                /** @var AreaAggregatedCount|null $latestCount */
                $latestCount = $area->aggregatedCounts->first();
                /** @var AreaAggregatedCount|null $oneHourAgoCount */
                $oneHourAgoCount = $area->aggregatedCounts
                    ->where('period_end', '<=', $oneHourAgo)
                    ->sortByDesc('period_end')
                    ->first();

                // Calculate debug counts with graceful fallback
                try {
                    $debugCounts = $this->areaService->calculateAreaDebugCounts($area);
                } catch (Exception $exception) {
                    Log::error(sprintf('Failed to calculate area counts for area %d: ', $area->id).$exception->getMessage());
                    $debugCounts = [
                        'in' => 0,
                        'out' => 0,
                        'net' => 0,
                        'last_reset_type' => null,
                        'last_reset_at' => null,
                        'last_reset_value' => 0,
                        'net_plus_reset' => 0,
                    ];
                }

                return [
                    'id' => $area->id,
                    'name' => $area->name,
                    'event_name' => $area->event->name,
                    'count' => $latestCount->count ?? 0,
                    'net_change' => $latestCount && $oneHourAgoCount
                        ? $latestCount->count - $oneHourAgoCount->count
                        : null,
                    'net_change_time_ago' => $latestCount && $oneHourAgoCount
                        ? Date::parse($latestCount->period_end)->diffForHumans(Date::parse($oneHourAgoCount->period_end), ['syntax' => true])
                        : null,
                    'debug_counts' => $debugCounts,
                    'last_updated' => $now->toIso8601String(),
                ];
            })->all();
        });

    }
}
