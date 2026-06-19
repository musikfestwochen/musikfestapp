<?php

declare(strict_types=1);

namespace App\Services\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

class AreaAggregationService
{
    private const int WINDOW_CHUNK_SIZE = 1440;

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

        $resetTimes = $this->getPastResetTimes($area);
        $checkpoint = $this->getAggregationCheckpoint($area);
        $lastCount = $checkpoint['initial_count'];

        foreach ($this->getAggregationWindowChunks($area, $resetTimes, $checkpoint['recalculate_from']) as $aggregationWindows) {
            $lastCount = $this->calculateAggregatedCountsForWindows($area, $aggregationWindows, $areaConfigChecksum, $lastCount);
        }
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
        $windowEnd = $this->calculateWindowEnd($startTime, $config['endTime'], $config['windowSize'], $resetTimes);
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
    protected function calculateWindowEnd(Carbon $startTime, Carbon $eventEndTime, int $windowSize, Collection $resetTimes): Carbon
    {
        $naturalWindowEnd = min($startTime->copy()->addMinutes($windowSize), $eventEndTime);
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
        return $resetTimes->first(function (array $reset) use ($startTime, $windowEnd) {
            return $reset['at']->between($startTime, $windowEnd);
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
    protected function calculateAggregatedCountsForWindows(Area $area, iterable $aggregationWindows, string $areaConfigChecksum, int $lastCount): int
    {
        foreach ($aggregationWindows as $window) {
            $lastCount = $this->areaService->calculateAndStoreAggregatedCount(
                $area,
                $window['start'],
                $window['end'],
                $window['reset_value'] ?? $lastCount,
                $areaConfigChecksum
            );
        }

        return $lastCount;
    }

    /**
     * @return array{recalculate_from: Carbon|null, initial_count: int}
     */
    protected function getAggregationCheckpoint(Area $area): array
    {
        $latestCounts = $area->aggregatedCounts()
            ->latest('period_end')
            ->limit(3)
            ->get(['id', 'area_id', 'period_start', 'period_end', 'count']);

        return [
            'recalculate_from' => $latestCounts->get(1)?->period_start,
            'initial_count' => $latestCounts->has(2) ? $latestCounts->get(2)->count : 0,
        ];
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
