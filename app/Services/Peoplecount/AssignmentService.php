<?php

declare(strict_types=1);

namespace App\Services\Peoplecount;

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\Sensor;
use DateTime;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    /**
     * Get all assignments for the current organization.
     *
     * @return Collection<int, Assignment>
     */
    public function getAssignments(): Collection
    {
        $currentOrgId = getPermissionsOrgId();
        $query = Assignment::query();

        if ($currentOrgId !== GLOBAL_ORG_ID) {
            $query->whereHas('event', function (Builder $query) use ($currentOrgId) {
                $query->where('organization_id', $currentOrgId);
            });
        }

        return $query
            ->with(['event', 'area', 'sensor'])
            ->get();
    }

    /**
     * Create a new assignment.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function create(array $attributes): Assignment
    {
        // Verify that the event, area, and sensor belong to the current organization
        $this->verifyEventBelongsToCurrentOrganization($attributes['event_id']);
        $this->verifyAreaBelongsToEvent($attributes['area_id'], $attributes['event_id']);
        $this->verifySensorBelongsToCurrentOrganization($attributes['sensor_id']);

        // Verify that the assignment time boundaries are within event time boundaries
        $this->verifyAssignmentTimeWithinEventTime(
            $attributes['event_id'],
            $attributes['active_from'],
            $attributes['active_to']
        );

        // Verify that there are no overlapping assignments for the same sensor and direction
        $this->verifyNoOverlappingAssignments(
            null,
            $attributes['sensor_id'],
            $attributes['direction_flipped'],
            $attributes['active_from'],
            $attributes['active_to']
        );

        return Assignment::query()->create($attributes);
    }

    /**
     * Verify that the event belongs to the current organization.
     * This is a security measure to prevent users from assigning sensors to events they don't have access to.
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

        throw_if(
            $event->organization_id !== $currentOrgId,
            AuthorizationException::class,
            'You are not authorized to assign sensors to this event.'
        );
    }

    /**
     * Verify that the area belongs to the event.
     * This is a security measure to prevent users from assigning sensors to areas they don't have access to.
     *
     * @throws AuthorizationException
     */
    public function verifyAreaBelongsToEvent(int $areaId, int $eventId): void
    {
        $area = Area::query()->findOrFail($areaId);

        throw_if(
            $area->event_id !== $eventId,
            AuthorizationException::class,
            'The area does not belong to the specified event.'
        );
    }

    /**
     * Verify that the sensor belongs to the current organization.
     * This is a security measure to prevent users from assigning sensors they don't have access to.
     *
     * @throws AuthorizationException
     */
    public function verifySensorBelongsToCurrentOrganization(int $sensorId): void
    {
        $currentOrgId = getPermissionsOrgId();

        // Skip check for global organization
        if ($currentOrgId === GLOBAL_ORG_ID) {
            return;
        }

        $sensor = Sensor::query()->findOrFail($sensorId);

        throw_if(
            $sensor->organization_id !== $currentOrgId,
            AuthorizationException::class,
            'You are not authorized to assign this sensor.'
        );
    }

    /**
     * Verify that the assignment time boundaries are within event time boundaries.
     *
     * @throws ValidationException
     */
    public function verifyAssignmentTimeWithinEventTime(int $eventId, string $activeFrom, string $activeTo): void
    {
        $event = Event::query()->findOrFail($eventId);

        $activeFromDate = new DateTime($activeFrom);
        $activeToDate = new DateTime($activeTo);
        $eventStartsAtDate = new DateTime($event->starts_at->toDateTimeString());
        $eventEndsAtDate = new DateTime($event->ends_at->toDateTimeString());

        throw_if($activeFromDate < $eventStartsAtDate || $activeToDate > $eventEndsAtDate, ValidationException::withMessages([
            'active_from' => 'The assignment time boundaries must be within the event time boundaries.',
            'active_to' => 'The assignment time boundaries must be within the event time boundaries.',
        ]));
    }

    /**
     * Verify that there are no overlapping assignments for the same sensor and direction_flipped value.
     *
     * @throws ValidationException
     */
    public function verifyNoOverlappingAssignments(
        ?int $assignmentId,
        int $sensorId,
        bool $directionFlipped,
        string $activeFrom,
        string $activeTo
    ): void {
        $query = Assignment::query()
            ->where('sensor_id', $sensorId)
            ->where('direction_flipped', $directionFlipped);

        // Exclude the current assignment when updating
        if ($assignmentId !== null) {
            $query->where('id', '!=', $assignmentId);
        }

        // Check for overlapping time periods
        $query->where(function (Builder $query) use ($activeFrom, $activeTo) {
            $query->where(function (Builder $query) use ($activeFrom) {
                // New assignment starts during an existing assignment
                $query->where('active_from', '<=', $activeFrom)
                    ->where('active_to', '>=', $activeFrom);
            })->orWhere(function (Builder $query) use ($activeTo) {
                // New assignment ends during an existing assignment
                $query->where('active_from', '<=', $activeTo)
                    ->where('active_to', '>=', $activeTo);
            })->orWhere(function (Builder $query) use ($activeFrom, $activeTo) {
                // New assignment completely contains an existing assignment
                $query->where('active_from', '>=', $activeFrom)
                    ->where('active_to', '<=', $activeTo);
            });
        });

        $overlappingAssignments = $query->get();

        throw_if($overlappingAssignments->isNotEmpty(), ValidationException::withMessages([
            'sensor_id' => 'There is already an assignment for this sensor and direction_flipped value during the specified time period.',
            'direction_flipped' => 'There is already an assignment for this sensor and direction_flipped value during the specified time period.',
            'active_from' => 'There is already an assignment for this sensor and direction_flipped value during the specified time period.',
            'active_to' => 'There is already an assignment for this sensor and direction_flipped value during the specified time period.',
        ]));
    }

    /**
     * Update an existing assignment.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function update(Assignment $assignment, array $attributes): Assignment
    {
        // Verify that the event, area, and sensor belong to the current organization
        $this->verifyEventBelongsToCurrentOrganization($attributes['event_id']);
        $this->verifyAreaBelongsToEvent($attributes['area_id'], $attributes['event_id']);
        $this->verifySensorBelongsToCurrentOrganization($attributes['sensor_id']);

        // Verify that the assignment time boundaries are within event time boundaries
        $this->verifyAssignmentTimeWithinEventTime(
            $attributes['event_id'],
            $attributes['active_from'],
            $attributes['active_to']
        );

        // Verify that there are no overlapping assignments for the same sensor and direction
        $this->verifyNoOverlappingAssignments(
            $assignment->id,
            $attributes['sensor_id'],
            $attributes['direction_flipped'],
            $attributes['active_from'],
            $attributes['active_to']
        );

        $assignment->update($attributes);

        return $assignment;
    }
}
