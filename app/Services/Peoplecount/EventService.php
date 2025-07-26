<?php

namespace App\Services\Peoplecount;

use App\Models\Peoplecount\Event;
use Illuminate\Support\Collection;

class EventService
{
    /**
     * Get all events for the current organization.
     *
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        $currentOrgId = getPermissionsOrgId();
        $query = Event::query();

        if ($currentOrgId !== GLOBAL_ORG_ID) {
            $query->where('organization_id', $currentOrgId);
        }

        return $query->get();
    }

    /**
     * Create a new event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Event
    {
        return Event::query()->create($attributes);
    }

    /**
     * Update an existing event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Event $event, array $attributes): Event
    {
        $event->update($attributes);

        return $event;
    }

    /**
     * Get an event with its areas and assignments.
     */
    public function getEventWithRelations(Event $event): Event
    {
        return $event->load(['areas.assignments', 'assignments.area', 'assignments.sensor']);
    }
}
