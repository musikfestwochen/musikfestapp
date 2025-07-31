<?php

namespace App\Services\Peoplecount;

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Models\Peoplecount\AreaSingleReset;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use RRule\RRule;

class AreaResetService
{
    /**
     * Create a single reset for an area.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createSingleReset(Area $area, array $attributes): AreaSingleReset
    {
        // Verify that the area belongs to the current organization
        $this->verifyAreaBelongsToCurrentOrganization($area);

        // Convert effective_at to UTC for storage
        $effectiveAt = Carbon::parse($attributes['effective_at'])->utc();

        return AreaSingleReset::query()->create([
            'area_id' => $area->id,
            'reset_value' => $attributes['reset_value'],
            'effective_at' => $effectiveAt,
            'notes' => $attributes['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Verify that the area belongs to the current organization.
     * This is a security measure to prevent users from accessing areas they don't have access to.
     *
     * @throws AuthorizationException
     */
    protected function verifyAreaBelongsToCurrentOrganization(Area $area): void
    {
        $currentOrgId = getPermissionsOrgId();

        // Skip check for global organization
        if ($currentOrgId === GLOBAL_ORG_ID) {
            return;
        }

        // Load the event relationship if not already loaded
        if (! $area->relationLoaded('event')) {
            $area->load('event');
        }

        throw_if(
            $area->event->organization_id !== $currentOrgId,
            new AuthorizationException('You are not authorized to access this area.')
        );
    }

    /**
     * Get all resets for an area.
     *
     * @return Collection<int, AreaSingleReset>
     */
    public function getAreaResets(Area $area): Collection
    {
        // Verify that the area belongs to the current organization
        $this->verifyAreaBelongsToCurrentOrganization($area);

        return $area->areaSingleResets()
            ->with('createdBy')
            ->orderBy('effective_at', 'desc')
            ->get();
    }

    /**
     * Delete a single reset.
     */
    public function deleteSingleReset(AreaSingleReset $reset): void
    {
        // Verify that the area belongs to the current organization
        $this->verifyAreaBelongsToCurrentOrganization($reset->area);

        $reset->delete();
    }

    /**
     * Get all recurring resets for an area.
     *
     * @return Collection<int, AreaRecurringReset>
     */
    public function getAreaRecurringResets(Area $area): Collection
    {
        // Verify that the area belongs to the current organization
        $this->verifyAreaBelongsToCurrentOrganization($area);

        return $area->areaRecurringResets()
            ->with(['area'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create a recurring reset for an area.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createRecurringReset(Area $area, array $attributes): AreaRecurringReset
    {
        // Verify that the area belongs to the current organization
        $this->verifyAreaBelongsToCurrentOrganization($area);

        // Validate RRULE format
        $this->validateRRule($attributes['rrule']);

        return AreaRecurringReset::query()->create([
            'area_id' => $area->id,
            'reset_value' => $attributes['reset_value'],
            'rrule' => $attributes['rrule'],
            'timezone' => $attributes['timezone'],
            'notes' => $attributes['notes'] ?? null,
        ]);
    }

    /**
     * Update a recurring reset.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateRecurringReset(AreaRecurringReset $reset, array $attributes): AreaRecurringReset
    {
        // Verify that the area belongs to the current organization
        $this->verifyAreaBelongsToCurrentOrganization($reset->area);

        // Validate RRULE format
        $this->validateRRule($attributes['rrule']);

        $reset->update([
            'reset_value' => $attributes['reset_value'],
            'rrule' => $attributes['rrule'],
            'timezone' => $attributes['timezone'],
            'notes' => $attributes['notes'] ?? null,
        ]);

        return $reset->fresh();
    }

    /**
     * Delete a recurring reset.
     */
    public function deleteRecurringReset(AreaRecurringReset $reset): void
    {
        // Verify that the area belongs to the current organization
        $this->verifyAreaBelongsToCurrentOrganization($reset->area);

        $reset->delete();
    }

    /**
     * Parse an RRULE string and return an RRule instance.
     *
     * @return RRule<\DateTime>
     */
    public function parseRRule(string $rrule): RRule
    {
        return new RRule($rrule);
    }

    /**
     * Get the next occurrences for an RRULE.
     *
     * @return array<int, \DateTime>
     */
    public function getNextResetOccurrences(string $rrule, int $limit = 5): array
    {
        $ruleInstance = $this->parseRRule($rrule);

        // Use more efficient getOccurrencesBetween method instead of iteration
        $startDate = new \DateTime;
        $endDate = (clone $startDate)->add(new \DateInterval('P1Y')); // 1 year from now

        $occurrences = $ruleInstance->getOccurrencesBetween($startDate, $endDate);

        // Return only the requested number of occurrences
        return array_slice($occurrences, 0, $limit);
    }

    /**
     * Validate RRULE format.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateRRule(string $rrule): void
    {
        try {
            new RRule($rrule);
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException('Invalid RRULE format: '.$exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}
