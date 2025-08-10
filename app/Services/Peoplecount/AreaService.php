<?php

namespace App\Services\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Models\Peoplecount\AreaSingleReset;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\IntervalCount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class AreaService
{
    /**
     * Get all areas for the current organization.
     *
     * @return Collection<int, Area>
     */
    public function getAreas(): Collection
    {
        $currentOrgId = getPermissionsOrgId();

        // If global organization, return all areas
        if ($currentOrgId === GLOBAL_ORG_ID) {
            return Area::with(['event', 'assignments'])->get();
        }

        // Otherwise, return areas for the current organization
        return Organization::query()
            ->findOrFail($currentOrgId)
            ->areas()
            ->with(['event', 'assignments'])
            ->get();
    }

    /**
     * Create a new area.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws AuthorizationException
     */
    public function create(array $attributes): Area
    {
        // Verify that the event belongs to the current organization
        $this->verifyEventBelongsToCurrentOrganization($attributes['event_id']);

        return Area::query()->create($attributes);
    }

    /**
     * Verify that the event belongs to the current organization.
     * This is a security measure to prevent users from assigning areas to events they don't have access to.
     *
     * @throws AuthorizationException|Throwable
     */
    public function verifyEventBelongsToCurrentOrganization(int $eventId): void
    {
        $currentOrgId = getPermissionsOrgId();

        // Skip check for global organization
        if ($currentOrgId === GLOBAL_ORG_ID) {
            return;
        }

        $event = Event::query()->findOrFail($eventId);

        throw_if($event->organization_id !== $currentOrgId, new AuthorizationException('You are not authorized to assign areas to this event.'));
    }

    /**
     * Update an existing area.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Area $area, array $attributes): Area
    {
        // Verify that the event belongs to the current organization
        $this->verifyEventBelongsToCurrentOrganization($attributes['event_id']);

        $area->update($attributes);

        return $area;
    }

    public function getWithRelations(Area $area): Area
    {
        // Eager load the event, assignments, areaSingleResets, and areaRecurringResets relationships
        return $area->load(['event', 'assignments.sensor', 'areaSingleResets.createdBy', 'areaRecurringResets']);
    }

    /**
     * Load all Reset relationships
     */
    public function loadAllResets(Area $area): void
    {
        $area->load([
            'areaSingleResets',
            'areaRecurringResets',
            'event',
        ]);
    }

    /**
     * Calculate a checksum for the area based on all data that affects area count calculation.
     *
     * This method loads all related models and calculates a checksum over fields that are
     * relevant to area count calculation. Fields like notes are excluded as they don't
     * affect the calculation.
     */
    public function calculateChecksum(Area $area): string
    {
        $this->loadChecksumRelationships($area); // @pest-mutate-ignore - relationships are loaded lazily when accessed in collectChecksumData
        $checksumData = $this->collectChecksumData($area);
        $sortedData = $this->sortChecksumData($checksumData);

        return hash('sha256', (string) json_encode($sortedData)); // @pest-mutate-ignore
    }

    /**
     * Load all relationships needed for checksum calculation.
     *
     * @pest-mutate-ignore
     */
    protected function loadChecksumRelationships(Area $area): void
    {
        $area->load([
            'event',
            'assignments.sensor',
            'areaSingleResets',
            'areaRecurringResets',
        ]);
    }

    /**
     * Collect checksum data from an area and its relationships.
     *
     * @return array<string, mixed>
     */
    public function collectChecksumData(Area $area): array
    {
        $checksumConfig = $this->getChecksumConfig();
        $checksumData = [];

        // Add area data
        $checksumData['area'] = $this->extractModelAttributes($area, $checksumConfig['area']);

        // Add parent event data
        if ($area->event) {
            $checksumData['event'] = $this->extractModelAttributes($area->event, $checksumConfig['event']);
        }

        // Add assignments data
        $checksumData['assignments'] = $this->extractCollectionAttributes($area->assignments, $checksumConfig['assignments']);

        // Add single resets data
        $checksumData['areaSingleResets'] = $this->extractCollectionAttributes($area->areaSingleResets, $checksumConfig['areaSingleResets']);

        // Add recurring resets data
        $checksumData['areaRecurringResets'] = $this->extractCollectionAttributes($area->areaRecurringResets, $checksumConfig['areaRecurringResets']);

        return $checksumData;
    }

    /**
     * Get the configuration for checksum calculation.
     * Defines which attributes to include in checksum calculation for each model type.
     *
     * @return array<string, list<string>>
     */
    public function getChecksumConfig(): array
    {
        return [
            'area' => ['id', 'event_id'],
            'event' => ['id', 'starts_at', 'ends_at'],
            'assignments' => ['id', 'area_id', 'sensor_id', 'direction_flipped', 'active_from', 'active_to'],
            'areaSingleResets' => ['id', 'area_id', 'reset_value', 'effective_at'],
            'areaRecurringResets' => ['id', 'area_id', 'reset_value', 'reset_time', 'timezone'],
        ];
    }

    /**
     * Extract specified attributes from a model.
     *
     * @param  list<string>  $attributes
     * @return array<string, mixed>
     */
    public function extractModelAttributes(Assignment|Area|AreaSingleReset|AreaRecurringReset|Event $model, array $attributes): array
    {
        $data = [];
        foreach ($attributes as $attribute) {
            $data[$attribute] = $model->getAttribute($attribute);
        }

        return $data;
    }

    /**
     * Extract specified attributes from a collection of models.
     *
     * @template TModel of Area|Event|Assignment|AreaSingleReset|AreaRecurringReset
     *
     * @param  Collection<int, TModel>  $collection
     * @param  list<string>  $attributes
     * @return array<int, array<string, mixed>>
     */
    public function extractCollectionAttributes(Collection $collection, array $attributes): array
    {
        $data = [];
        foreach ($collection as $index => $model) {
            $data[$index] = $this->extractModelAttributes($model, $attributes);
        }

        return $data;
    }

    /**
     * Sort checksum data to ensure consistent ordering.
     *
     * @param  array<string, mixed>  $checksumData
     * @return array<string, mixed>
     */
    public function sortChecksumData(array $checksumData): array
    {
        ksort($checksumData);
        foreach ($checksumData as $key => $data) {
            if (is_array($data)) {
                ksort($checksumData[$key]);
            }
        }

        return $checksumData;
    }

    /**
     * Get all reset times for the area that need to be separately aggregated with their respective reset values.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getAreaResets(Area $area): Collection
    {
        $eventStartTime = $area->event->starts_at;
        $eventEndTime = $area->event->ends_at;

        $resets = collect();

        $resets->push($this->createEventStartReset($eventStartTime));
        $resets = $resets->merge($this->getSingleResets($area, $eventStartTime, $eventEndTime));
        $resets = $resets->merge($this->getRecurringResets($area, $eventStartTime, $eventEndTime));

        return $this->deduplicateResets($resets->sortBy('at')->values());
    }

    /**
     * Create the event start reset entry.
     *
     * @return array<string, mixed>
     */
    protected function createEventStartReset(Carbon $eventStartTime): array
    {
        return [
            'at' => $eventStartTime,
            'reset_value' => 0,
            'type' => 'event_start',
        ];
    }

    /**
     * Get single resets that fall within the event time period.
     *
     * @return Collection<int, array{at: Carbon, reset_value: int, type: string}>
     */
    protected function getSingleResets(Area $area, Carbon $eventStartTime, Carbon $eventEndTime): Collection
    {
        return $area->areaSingleResets
            ->filter(fn (AreaSingleReset $reset): bool => $this->isResetWithinEventPeriod($reset->effective_at, $eventStartTime, $eventEndTime))
            ->map(fn (AreaSingleReset $reset): array => [
                'at' => $reset->effective_at,
                'reset_value' => $reset->reset_value,
                'type' => 'single_reset',
            ]);
    }

    /**
     * Check if a reset time falls within the event period.
     */
    protected function isResetWithinEventPeriod(Carbon $resetTime, Carbon $eventStartTime, Carbon $eventEndTime): bool
    {
        return $resetTime >= $eventStartTime && $resetTime <= $eventEndTime;
    }

    /**
     * Get recurring resets that fall within the event time period.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function getRecurringResets(Area $area, Carbon $eventStartTime, Carbon $eventEndTime): Collection
    {
        $recurringResets = collect();

        foreach ($area->areaRecurringResets as $recurringReset) {
            $occurrences = $recurringReset->getOccurrencesBetween($eventStartTime, $eventEndTime);

            foreach ($occurrences as $resetTime) {
                $recurringResets->push([
                    'at' => $resetTime,
                    'reset_value' => $recurringReset->reset_value,
                    'type' => 'recurring_reset',
                ]);
            }
        }

        return $recurringResets;
    }

    /**
     * Remove duplicate resets, prioritizing single > recurring > event start.
     *
     * @param  Collection<int, array<string, mixed>>  $resets
     * @return Collection<int, array<string, mixed>>
     */
    protected function deduplicateResets(Collection $resets): Collection
    {
        return $resets->groupBy('at')->map(function (Collection $group) {
            // Highest priority: single reset
            if ($group->contains('type', 'single_reset')) {
                return $group->firstWhere('type', 'single_reset');
            }

            // Next priority: event start (at exact event start time, prefer event start over recurring)
            if ($group->contains('type', 'event_start')) {
                return $group->firstWhere('type', 'event_start');
            }

            // Lowest priority: recurring reset
            if ($group->contains('type', 'recurring_reset')) {
                return $group->firstWhere('type', 'recurring_reset');
            }

            throw new RuntimeException('No valid reset type found in group.');
        })->values();
    }

    public function calculateAndStoreAggregatedCount(Area $area, Carbon $start, Carbon $end, int $start_value, string $areaConfigChecksum): int
    {
        $activeAssignments = $this->getActiveAssignments($area, $start, $end);
        $totalCount = $this->calculateTotalCountForAssignments($activeAssignments, $start, $end);
        $finalCount = $start_value + $totalCount;

        $this->storeAggregatedCount($area, $start, $end, $finalCount, $areaConfigChecksum);

        return $finalCount;
    }

    /**
     * Get assignments that are active during the specified time period.
     *
     * @return Collection<int, Assignment>
     */
    protected function getActiveAssignments(Area $area, Carbon $start, Carbon $end): Collection
    {
        return $area->assignments()
            ->with('sensor')
            ->where('active_from', '<=', $end)
            ->where('active_to', '>=', $start)
            ->get();
    }

    /**
     * Calculate the total count for all active assignments within the time period.
     *
     * @param  Collection<int, Assignment>  $activeAssignments
     */
    protected function calculateTotalCountForAssignments(Collection $activeAssignments, Carbon $start, Carbon $end): int
    {
        $totalCount = 0;

        foreach ($activeAssignments as $assignment) {
            $totalCount += $this->calculateCountForAssignment($assignment, $start, $end);
        }

        return $totalCount;
    }

    /**
     * Calculate the count contribution from a single assignment.
     */
    protected function calculateCountForAssignment(Assignment $assignment, Carbon $start, Carbon $end): int
    {
        $intervalCounts = $this->getRelevantIntervalCounts($assignment, $start, $end);
        $assignmentTotal = 0;

        foreach ($intervalCounts as $intervalCount) {
            if ($this->isIntervalCountValid($intervalCount, $assignment, $start, $end)) {
                $netCount = $intervalCount->count_in - $intervalCount->count_out;

                if ($assignment->direction_flipped) {
                    $netCount = -$netCount;
                }

                $assignmentTotal += $netCount;
            }
        }

        return $assignmentTotal;
    }

    /**
     * Get interval counts that are relevant for the time period.
     *
     * @return Collection<int, IntervalCount>
     */
    protected function getRelevantIntervalCounts(Assignment $assignment, Carbon $start, Carbon $end): Collection
    {
        return $assignment->sensor->intervalCounts()
            ->where('ts_from', '>=', $start)
            ->where('ts_from', '<', $end)
            ->get();
    }

    /**
     * Check if an interval count is valid for aggregation.
     */
    protected function isIntervalCountValid(IntervalCount $intervalCount, Assignment $assignment, Carbon $start, Carbon $end): bool
    {
        return $intervalCount->ts_from >= $assignment->active_from
            && $intervalCount->ts_from < $assignment->active_to;
    }

    /**
     * Store the aggregated count in the database.
     */
    protected function storeAggregatedCount(Area $area, Carbon $start, Carbon $end, int $finalCount, string $areaConfigChecksum): void
    {
        AreaAggregatedCount::query()->updateOrCreate([
            'area_id' => $area->id,
            'period_start' => $start,
            'period_end' => $end,
        ], [
            'checksum' => $areaConfigChecksum,
            'count' => $finalCount,
        ]);
    }

    /**
     * Calculate counts for a whole area
     *
     * This method uses all interval counts for a given area after the last reset (single OR recurring OR event start)
     * and adds them together. It returns the sum of all `in` counts, the sum of all `out` counts, the net count,
     * and additional debug metadata about the last reset.
     *
     * @return array{
     *     in: int,
     *     out: int,
     *     net: int,
     *     last_reset_type: string,
     *     last_reset_at: Carbon,
     *     last_reset_value: int,
     *     net_plus_reset: int
     * }
     *
     * @note This method is for debugging purposes and should not be used in production.
     *
     * @warning This method ignores **direction flips** and **assignment active periods**.
     */
    public function calculateAreaDebugCounts(Area $area): array
    {
        // We need recurring resets as well to derive the latest reset of any type
        $area->load(['assignments.sensor.intervalCounts', 'event', 'areaSingleResets', 'areaRecurringResets']); // @pest-mutate-ignore

        $now = now();

        // Determine the latest reset (single/recurring/event_start) at or before now
        $resets = $this->getAreaResets($area)
            ->filter(fn (array $r) => $r['at']->lte($now))
            ->sortByDesc('at')
            ->values();

        $lastReset = $resets->first();

        if (! $lastReset) {
            // Fallback to event start if no reset is found (should not usually happen because event_start is included)
            $lastReset = [
                'at' => $area->event->starts_at,
                'reset_value' => 0,
                'type' => 'event_start',
            ];
        }

        $start = $lastReset['at'];
        $end = $now;

        // get all interval counts (separate query to avoid using other methods of this service)
        $intervalCounts = $area->assignments->flatMap(function (Assignment $assignment) use ($start, $end) {
            return $assignment->sensor->intervalCounts()
                ->where('ts_from', '>=', $start)
                ->where('ts_from', '<', $end)
                ->get();
        });

        $inCount = $intervalCounts->sum('count_in');
        $outCount = $intervalCounts->sum('count_out');
        $netCount = $inCount - $outCount;
        $netPlusReset = $netCount + (int) $lastReset['reset_value'];

        return [
            'in' => $inCount,
            'out' => $outCount,
            'net' => $netCount,
            'last_reset_type' => (string) $lastReset['type'],
            'last_reset_at' => $start,
            'last_reset_value' => (int) $lastReset['reset_value'],
            'net_plus_reset' => $netPlusReset,
        ];
    }

    /**
     * Get latest single reset before now (or before the given time).
     */
    public function getLatestSingleResetBefore(Area $area, ?Carbon $beforeTime = null): ?AreaSingleReset
    {
        $query = $area->areaSingleResets()
            ->where('effective_at', '<=', $beforeTime ?? now())
            ->orderBy('effective_at', 'desc');

        return $query->first();
    }
}
