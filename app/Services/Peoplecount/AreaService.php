<?php

namespace App\Services\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Area;
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
            return Area::all();
        }

        // Otherwise, return areas for the current organization
        return Organization::query()
            ->findOrFail($currentOrgId)
            ->areas()
            ->get();
    }

    /**
     * Create a new area.
     *
     * @param  array<string, mixed>  $attributes
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
     * @throws AuthorizationException
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
}
