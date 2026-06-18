<?php

declare(strict_types=1);

namespace App\Services\Peoplecount;

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\Sensor;
use App\Models\Peoplecount\SensorShare;
use DateTime;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    private readonly SensorShareService $sensorShareService;

    public function __construct(?SensorShareService $sensorShareService = null)
    {
        $this->sensorShareService = $sensorShareService ?? new SensorShareService;
    }

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
        // Verify that the event and area belong to the current organization
        $this->verifyEventBelongsToCurrentOrganization($attributes['event_id']);
        $this->verifyAreaBelongsToEvent($attributes['area_id'], $attributes['event_id']);

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

        $attributes['sensor_share_id'] = $this->resolveSensorShareIdForAssignment($attributes);

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
    public function verifySensorAssignableToEvent(int $sensorId, int $eventId, string $activeFrom, string $activeTo, ?Assignment $assignment = null): ?SensorShare
    {
        $currentOrgId = getPermissionsOrgId();
        $sensor = Sensor::query()->findOrFail($sensorId);

        $this->verifySensorIsNotArchivedForAssignment($sensor, $assignment);

        // Skip check for global organization
        if ($currentOrgId === GLOBAL_ORG_ID) {
            return null;
        }

        $event = Event::query()->findOrFail($eventId);

        if ($sensor->organization_id === $currentOrgId) {
            return null;
        }

        $sensorShare = $this->sensorShareService->findValidShareForAssignment($sensor, $event, $activeFrom, $activeTo);

        throw_if(
            ! $sensorShare instanceof SensorShare,
            AuthorizationException::class,
            'You are not authorized to assign this sensor for the selected period.'
        );

        return $sensorShare;
    }

    /**
     * @throws AuthorizationException
     */
    public function verifySensorBelongsToCurrentOrganization(int $sensorId): void
    {
        $currentOrgId = getPermissionsOrgId();

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
            ->where('direction_flipped', $directionFlipped)
            ->whereHas('event', function (Builder $query) {
                $currentOrgId = getPermissionsOrgId();

                if ($currentOrgId !== GLOBAL_ORG_ID) {
                    $query->where('organization_id', $currentOrgId);
                }
            });

        // Exclude the current assignment when updating
        if ($assignmentId !== null) {
            $query->where('id', '!=', $assignmentId);
        }

        // Exclusive boundary overlap: [existing_from, existing_to) intersects [new_from, new_to)
        $query->where('active_from', '<', $activeTo)
            ->where('active_to', '>', $activeFrom);

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
        $this->verifyAssignmentBelongsToCurrentOrganization($assignment);

        // Verify that the event and area belong to the current organization
        $this->verifyEventBelongsToCurrentOrganization($attributes['event_id']);
        $this->verifyAreaBelongsToEvent($attributes['area_id'], $attributes['event_id']);

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

        $attributes['sensor_share_id'] = $this->resolveSensorShareIdForAssignment($attributes, $assignment);

        $assignment->update($attributes);

        return $assignment;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function resolveSensorShareIdForAssignment(array $attributes, ?Assignment $assignment = null): ?int
    {
        return $this->verifySensorAssignableToEvent(
            $attributes['sensor_id'],
            $attributes['event_id'],
            $attributes['active_from'],
            $attributes['active_to'],
            $assignment
        )?->id;
    }

    protected function verifySensorIsNotArchivedForAssignment(Sensor $sensor, ?Assignment $assignment): void
    {
        if ($sensor->archived_at === null) {
            return;
        }

        if ($assignment?->sensor_id === $sensor->id) {
            return;
        }

        throw ValidationException::withMessages([
            'sensor_id' => 'Archived sensors cannot be assigned.',
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function verifyAssignmentBelongsToCurrentOrganization(Assignment $assignment): void
    {
        $currentOrgId = getPermissionsOrgId();

        if ($currentOrgId === GLOBAL_ORG_ID) {
            return;
        }

        $assignment->loadMissing('event');

        throw_if(
            $assignment->event->organization_id !== $currentOrgId,
            AuthorizationException::class,
            'You are not authorized to manage this assignment.'
        );
    }
}
