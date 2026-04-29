<?php

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

class AreaAggregationService
{
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
        $aggregationWindows = $this->getFilteredAggregationWindows($area, $resetTimes);
        $this->calculateAggregatedCountsForWindows($area, $aggregationWindows, $areaConfigChecksum);
    }

    /**
     * Prepare the area for aggregation by loading relationships and cleaning invalid data.
     */
    protected function prepareAreaForAggregation(Area $area): string
    {
        $this->loadAreaRelationships($area); // @pest-mutate-ignore
        $areaConfigChecksum = $this->areaService->calculateChecksum($area);
        $this->deleteInvalidAggregationRows($area, $areaConfigChecksum);

        return $areaConfigChecksum;
    }

    /**
     * Load all necessary relationships for the area.
     *
     * @pest-mutate-ignore
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
     * @pest-mutate-ignore
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
     * Get aggregation windows filtered by time and existing aggregations.
     *
     * @param  Collection<int, array<string, mixed>>  $resetTimes
     * @return Collection<int, array<string, mixed>>
     */
    protected function getFilteredAggregationWindows(Area $area, Collection $resetTimes): Collection
    {
        $aggregationWindows = $this->splitIntoAggregationWindows($area, $resetTimes);
        $aggregationWindows = $this->filterFutureWindows($aggregationWindows);

        return $this->filterAlreadyAggregatedWindows($area, $aggregationWindows);
    }

    /**
     * Split the reset times into aggregation windows.
     *
     * @param  Collection<int, array<string, mixed>>  $resetTimes
     * @return Collection<int, array<string, mixed>>
     */
    protected function splitIntoAggregationWindows(Area $area, Collection $resetTimes): Collection
    {
        $windowConfig = $this->getWindowConfiguration($area);
        $sortedResetTimes = $resetTimes->sortBy('at');

        return $this->generateWindows($windowConfig, $sortedResetTimes);
    }

    /**
     * Get window configuration for aggregation.
     *
     * @return array<string, mixed>
     */
    protected function getWindowConfiguration(Area $area): array
    {
        return [
            'windowSize' => config('peoplecount.aggregation.granularity_minutes'),
            'startTime' => $area->event->starts_at,
            'endTime' => $area->event->ends_at,
        ];
    }

    /**
     * Generate aggregation windows based on configuration and reset times.
     *
     * @param  array<string, mixed>  $config
     * @param  Collection<int, array<string, mixed>>  $sortedResetTimes
     * @return Collection<int, array<string, mixed>>
     */
    protected function generateWindows(array $config, Collection $sortedResetTimes): Collection
    {
        $windows = collect();
        $currentTime = $config['startTime'];

        while ($currentTime < $config['endTime']) {
            $window = $this->createWindow($currentTime, $config, $sortedResetTimes);
            $windows->push($window);
            $currentTime = $window['end'];
        }

        return $windows;
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
     * Filter out windows that are in the future.
     *
     * @param  Collection<int, array<string, mixed>>  $aggregationWindows
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterFutureWindows(Collection $aggregationWindows): Collection
    {
        return $aggregationWindows->filter(function (array $window) {
            return $window['start']->isPast();
        });
    }

    /**
     * Filter out windows that are already aggregated.
     *
     * @param  Collection<int, array<string, mixed>>  $aggregationWindows
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterAlreadyAggregatedWindows(Area $area, Collection $aggregationWindows): Collection
    {
        $secondLastAggregatedCount = $area->aggregatedCounts()->latest('period_end')->skip(1)->first();

        if (! $secondLastAggregatedCount) {
            return $aggregationWindows;
        }

        return $aggregationWindows->filter(function (array $window) use ($secondLastAggregatedCount) {
            return $window['start']->greaterThanOrEqualTo($secondLastAggregatedCount->period_start);
        });
    }

    /**
     * Calculate and store aggregated counts for all windows.
     *
     * @param  Collection<int, array<string, mixed>>  $aggregationWindows
     */
    protected function calculateAggregatedCountsForWindows(Area $area, Collection $aggregationWindows, string $areaConfigChecksum): void
    {
        $lastCount = $this->getInitialCountForAggregation($area);

        foreach ($aggregationWindows as $window) {
            $lastCount = $this->areaService->calculateAndStoreAggregatedCount(
                $area,
                $window['start'],
                $window['end'],
                $window['reset_value'] ?? $lastCount,
                $areaConfigChecksum
            );
        }
    }

    /**
     * Get the initial count value for aggregation calculations.
     */
    protected function getInitialCountForAggregation(Area $area): int
    {
        $thirdLastAggregatedCount = $area->aggregatedCounts()->latest('period_end')->skip(2)->first();

        return $thirdLastAggregatedCount ? $thirdLastAggregatedCount->count : 0;
    }

    /**
     * Get the latest aggregated counts for active areas in an organization.
     *
     * @return array<int, array<string, mixed>>
     *
     * @pest-mutate-ignore
     *
     * @todo add proper testing and remove @pest-mutate-ignore
     * @todo Optimize with caching
     */
    public function getActiveAreaAggregatedCounts(Organization $organization): array
    {
        $cache_time = 5; // @pest-mutate-ignore
        $cacheKey = 'org_active_area_counts:'.$organization->id;

        return Cache::remember($cacheKey, now()->addSeconds($cache_time), function () use ($organization): array {
            $now = Date::now()->setTimezone('UTC');
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
                    Log::error(sprintf('Failed to calculate area counts for area %d: ', $area->id).$exception->getMessage()); // @pest-mutate-ignore
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
