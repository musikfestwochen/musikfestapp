<?php

namespace App\Services\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Models\Peoplecount\AreaSingleReset;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

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
     * @throws AuthorizationException|\Throwable
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
}
