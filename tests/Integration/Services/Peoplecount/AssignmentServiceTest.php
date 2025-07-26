<?php

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\AssignmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

covers(AssignmentService::class);

beforeEach(function () {
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    $this->service = new AssignmentService;
});

describe('getAssignments', function () {
    it('returns assignments', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create();
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        Assignment::factory()->count(5)
            ->for($event)
            ->for($area)
            ->for($sensor)
            ->create();

        setPermissionsOrgId($org->id);

        $result = $this->service->getAssignments();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(5);

        // Verify that relationships are loaded
        $assignment = $result->first();
        expect($assignment->relationLoaded('event'))->toBeTrue()
            ->and($assignment->relationLoaded('area'))->toBeTrue()
            ->and($assignment->relationLoaded('sensor'))->toBeTrue();
    });

    it('filters assignments by organization', function () {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $event1 = Event::factory()->for($org1, 'organization')->create();
        $event2 = Event::factory()->for($org2, 'organization')->create();

        $area1 = Area::factory()->for($event1)->create();
        $area2 = Area::factory()->for($event2)->create();

        $sensor1 = Sensor::factory()->withOrganization($org1)->create();
        $sensor2 = Sensor::factory()->withOrganization($org2)->create();

        // Create 3 assignments for org1
        Assignment::factory()->count(3)
            ->for($event1)
            ->for($area1)
            ->for($sensor1)
            ->create();

        // Create 7 assignments for org2
        Assignment::factory()->count(7)
            ->for($event2)
            ->for($area2)
            ->for($sensor2)
            ->create();

        setPermissionsOrgId($org1->id);

        $result = $this->service->getAssignments();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(3);
    });

    it('returns empty collection when no assignments exist', function () {
        $org = Organization::factory()->create();
        setPermissionsOrgId($org->id);

        $result = $this->service->getAssignments();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(0);
    });

    it('returns all assignments for global organization', function () {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $event1 = Event::factory()->for($org1, 'organization')->create();
        $event2 = Event::factory()->for($org2, 'organization')->create();

        $area1 = Area::factory()->for($event1)->create();
        $area2 = Area::factory()->for($event2)->create();

        $sensor1 = Sensor::factory()->withOrganization($org1)->create();
        $sensor2 = Sensor::factory()->withOrganization($org2)->create();

        // Create 3 assignments for org1
        Assignment::factory()->count(3)
            ->for($event1)
            ->for($area1)
            ->for($sensor1)
            ->create();

        // Create 7 assignments for org2
        Assignment::factory()->count(7)
            ->for($event2)
            ->for($area2)
            ->for($sensor2)
            ->create();

        setPermissionsOrgId(GLOBAL_ORG_ID);

        $result = $this->service->getAssignments();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(10);
    });
});

describe('create', function () {
    it('creates an assignment', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create([
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        setPermissionsOrgId($org->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => now()->subDays(2)->toDateTimeString(),
            'active_to' => now()->addDays(2)->toDateTimeString(),
        ];

        $assignment = $this->service->create($attributes);

        expect($assignment)->toBeInstanceOf(Assignment::class)
            ->and($assignment->event_id)->toBe($event->id)
            ->and($assignment->area_id)->toBe($area->id)
            ->and($assignment->sensor_id)->toBe($sensor->id)
            ->and($assignment->direction_flipped)->toBe(false)
            ->and($assignment->active_from)->toBeInstanceOf(\Carbon\Carbon::class)
            ->and($assignment->active_to)->toBeInstanceOf(\Carbon\Carbon::class);

        $this->assertDatabaseHas('peoplecount_assignments', [
            'id' => $assignment->id,
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
        ]);
    });

    it('throws authorization exception when event does not belong to current organization', function () {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $event = Event::factory()->for($org2, 'organization')->create([
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org1)->create();

        setPermissionsOrgId($org1->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => now()->subDays(2)->toDateTimeString(),
            'active_to' => now()->addDays(2)->toDateTimeString(),
        ];

        $this->expectException(AuthorizationException::class);
        $this->service->create($attributes);
    });

    it('throws authorization exception when area does not belong to event', function () {
        $org = Organization::factory()->create();
        $event1 = Event::factory()->for($org, 'organization')->create([
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);
        $event2 = Event::factory()->for($org, 'organization')->create();
        $area = Area::factory()->for($event2)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        setPermissionsOrgId($org->id);

        $attributes = [
            'event_id' => $event1->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => now()->subDays(2)->toDateTimeString(),
            'active_to' => now()->addDays(2)->toDateTimeString(),
        ];

        $this->expectException(AuthorizationException::class);
        $this->service->create($attributes);
    });

    it('throws authorization exception when sensor does not belong to current organization', function () {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $event = Event::factory()->for($org1, 'organization')->create([
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org2)->create();

        setPermissionsOrgId($org1->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => now()->subDays(2)->toDateTimeString(),
            'active_to' => now()->addDays(2)->toDateTimeString(),
        ];

        $this->expectException(AuthorizationException::class);
        $this->service->create($attributes);
    });

    it('throws validation exception when assignment time is outside event time', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create([
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(5),
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        setPermissionsOrgId($org->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => now()->subDays(2)->toDateTimeString(), // Before event starts
            'active_to' => now()->addDays(2)->toDateTimeString(),
        ];

        $this->expectException(ValidationException::class);
        $this->service->create($attributes);
    });

    it('throws validation exception when assignment active_from equals event end time', function () {
        $org = Organization::factory()->create();
        $eventEndTime = now()->addDays(5);
        $event = Event::factory()->for($org, 'organization')->create([
            'starts_at' => now()->addDays(1),
            'ends_at' => $eventEndTime,
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        setPermissionsOrgId($org->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => $eventEndTime->toDateTimeString(), // Equals event end time
            'active_to' => $eventEndTime->copy()->addHours(1)->toDateTimeString(),
        ];

        $this->expectException(ValidationException::class);
        $this->service->create($attributes);
    });

    it('throws validation exception when assignment active_to equals event start time', function () {
        $org = Organization::factory()->create();
        $eventStartTime = now()->addDays(1);
        $event = Event::factory()->for($org, 'organization')->create([
            'starts_at' => $eventStartTime,
            'ends_at' => now()->addDays(5),
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        setPermissionsOrgId($org->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => $eventStartTime->copy()->subHours(1)->toDateTimeString(),
            'active_to' => $eventStartTime->toDateTimeString(), // Equals event start time
        ];

        $this->expectException(ValidationException::class);
        $this->service->create($attributes);
    });

    it('validates that time boundary error messages contain both active_from and active_to fields', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create([
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(5),
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        setPermissionsOrgId($org->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => now()->subDays(2)->toDateTimeString(),
            'active_to' => now()->addDays(2)->toDateTimeString(),
        ];

        try {
            $this->service->create($attributes);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $validationException) {
            $errors = $validationException->errors();
            expect($errors)->toHaveKey('active_from')
                ->and($errors)->toHaveKey('active_to');
        }
    });

    it('allows assignment when active_from equals event start time', function () {
        $org = Organization::factory()->create();
        $eventStartTime = now()->addDays(1);
        $event = Event::factory()->for($org, 'organization')->create([
            'starts_at' => $eventStartTime,
            'ends_at' => now()->addDays(5),
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        setPermissionsOrgId($org->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => $eventStartTime->toDateTimeString(), // Equals event start time
            'active_to' => now()->addDays(2)->toDateTimeString(),
        ];

        // This should not throw an exception
        $assignment = $this->service->create($attributes);
        expect($assignment)->toBeInstanceOf(Assignment::class);
    });

    it('allows assignment when active_to equals event end time', function () {
        $org = Organization::factory()->create();
        $eventEndTime = now()->addDays(5);
        $event = Event::factory()->for($org, 'organization')->create([
            'starts_at' => now()->addDays(1),
            'ends_at' => $eventEndTime,
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        setPermissionsOrgId($org->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => now()->addDays(2)->toDateTimeString(),
            'active_to' => $eventEndTime->toDateTimeString(), // Equals event end time
        ];

        // This should not throw an exception
        $assignment = $this->service->create($attributes);
        expect($assignment)->toBeInstanceOf(Assignment::class);
    });

    it('throws validation exception when there are overlapping assignments during create', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create([
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        // Create an existing assignment
        Assignment::factory()
            ->for($event)
            ->for($area)
            ->for($sensor)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->subDays(3),
                'active_to' => now()->addDays(3),
            ]);

        setPermissionsOrgId($org->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => now()->subDays(2)->toDateTimeString(), // Overlaps with existing
            'active_to' => now()->addDays(2)->toDateTimeString(),
        ];

        try {
            $this->service->create($attributes);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $validationException) {
            $errors = $validationException->errors();
            expect($errors)->toHaveKey('sensor_id')
                ->and($errors)->toHaveKey('direction_flipped')
                ->and($errors)->toHaveKey('active_from')
                ->and($errors)->toHaveKey('active_to');
        }
    });
});

describe('update', function () {
    it('updates an assignment', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create([
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        $assignment = Assignment::factory()
            ->for($event)
            ->for($area)
            ->for($sensor)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->subDays(2),
                'active_to' => now()->addDays(2),
            ]);

        setPermissionsOrgId($org->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => true,
            'active_from' => now()->subDays(1)->toDateTimeString(),
            'active_to' => now()->addDays(1)->toDateTimeString(),
        ];

        $updatedAssignment = $this->service->update($assignment, $attributes);

        expect($updatedAssignment)->toBeInstanceOf(Assignment::class)
            ->and($updatedAssignment->id)->toBe($assignment->id)
            ->and($updatedAssignment->event_id)->toBe($event->id)
            ->and($updatedAssignment->area_id)->toBe($area->id)
            ->and($updatedAssignment->sensor_id)->toBe($sensor->id)
            ->and($updatedAssignment->direction_flipped)->toBe(true);

        $this->assertDatabaseHas('peoplecount_assignments', [
            'id' => $assignment->id,
            'direction_flipped' => true,
        ]);
    });

    it('throws validation exception when there are overlapping assignments', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create([
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        // Create an existing assignment
        Assignment::factory()
            ->for($event)
            ->for($area)
            ->for($sensor)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->subDays(3),
                'active_to' => now()->addDays(3),
            ]);

        // Create another assignment that we'll try to update to overlap with the first one
        $assignment2 = Assignment::factory()
            ->for($event)
            ->for($area)
            ->for($sensor)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->addDays(4),
                'active_to' => now()->addDays(5),
            ]);

        setPermissionsOrgId($org->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => now()->subDays(2)->toDateTimeString(), // Overlaps with first assignment
            'active_to' => now()->addDays(2)->toDateTimeString(),
        ];

        $this->expectException(ValidationException::class);
        $this->service->update($assignment2, $attributes);
    });

    it('throws authorization exception when updating with event from different organization', function () {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $event1 = Event::factory()->for($org1, 'organization')->create([
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);
        $event2 = Event::factory()->for($org2, 'organization')->create([
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);

        $area1 = Area::factory()->for($event1)->create();
        $area2 = Area::factory()->for($event2)->create();
        $sensor = Sensor::factory()->withOrganization($org1)->create();

        $assignment = Assignment::factory()
            ->for($event1)
            ->for($area1)
            ->for($sensor)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->subDays(2),
                'active_to' => now()->addDays(2),
            ]);

        setPermissionsOrgId($org1->id);

        $attributes = [
            'event_id' => $event2->id, // Different organization
            'area_id' => $area2->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => now()->subDays(1)->toDateTimeString(),
            'active_to' => now()->addDays(1)->toDateTimeString(),
        ];

        $this->expectException(AuthorizationException::class);
        $this->service->update($assignment, $attributes);
    });

    it('throws authorization exception when updating with area from different event', function () {
        $org = Organization::factory()->create();
        $event1 = Event::factory()->for($org, 'organization')->create([
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);
        $event2 = Event::factory()->for($org, 'organization')->create([
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);

        $area1 = Area::factory()->for($event1)->create();
        $area2 = Area::factory()->for($event2)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        $assignment = Assignment::factory()
            ->for($event1)
            ->for($area1)
            ->for($sensor)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->subDays(2),
                'active_to' => now()->addDays(2),
            ]);

        setPermissionsOrgId($org->id);

        $attributes = [
            'event_id' => $event1->id,
            'area_id' => $area2->id, // Area from different event
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => now()->subDays(1)->toDateTimeString(),
            'active_to' => now()->addDays(1)->toDateTimeString(),
        ];

        $this->expectException(AuthorizationException::class);
        $this->service->update($assignment, $attributes);
    });

    it('throws authorization exception when updating with sensor from different organization', function () {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $event = Event::factory()->for($org1, 'organization')->create([
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor1 = Sensor::factory()->withOrganization($org1)->create();
        $sensor2 = Sensor::factory()->withOrganization($org2)->create();

        $assignment = Assignment::factory()
            ->for($event)
            ->for($area)
            ->for($sensor1)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->subDays(2),
                'active_to' => now()->addDays(2),
            ]);

        setPermissionsOrgId($org1->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor2->id, // Sensor from different organization
            'direction_flipped' => false,
            'active_from' => now()->subDays(1)->toDateTimeString(),
            'active_to' => now()->addDays(1)->toDateTimeString(),
        ];

        $this->expectException(AuthorizationException::class);
        $this->service->update($assignment, $attributes);
    });

    it('throws validation exception when updating with time outside event boundaries', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create([
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(5),
        ]);
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        $assignment = Assignment::factory()
            ->for($event)
            ->for($area)
            ->for($sensor)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->addDays(2),
                'active_to' => now()->addDays(3),
            ]);

        setPermissionsOrgId($org->id);

        $attributes = [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => false,
            'active_from' => now()->subDays(1)->toDateTimeString(), // Before event starts
            'active_to' => now()->addDays(2)->toDateTimeString(),
        ];

        $this->expectException(ValidationException::class);
        $this->service->update($assignment, $attributes);
    });
});

describe('verifyEventBelongsToCurrentOrganization', function () {
    it('throws exception when event does not belong to current organization', function () {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        $event = Event::factory()->for($org2, 'organization')->create();

        setPermissionsOrgId($org1->id);

        $this->expectException(AuthorizationException::class);
        $this->service->verifyEventBelongsToCurrentOrganization($event->id);
    });

    it('passes when event belongs to current organization', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create();

        setPermissionsOrgId($org->id);

        // This should not throw an exception
        $this->service->verifyEventBelongsToCurrentOrganization($event->id);

        // If we get here, the test passes
        $this->assertTrue(true);
    });

    it('passes for any event when organization is global', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create();

        setPermissionsOrgId(GLOBAL_ORG_ID);

        // This should not throw an exception because global org can access any event
        $this->service->verifyEventBelongsToCurrentOrganization($event->id);

        // If we get here, the test passes
        $this->assertTrue(true);
    });
});

describe('verifySensorBelongsToCurrentOrganization', function () {
    it('throws exception when sensor does not belong to current organization', function () {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org2)->create();

        setPermissionsOrgId($org1->id);

        $this->expectException(AuthorizationException::class);
        $this->service->verifySensorBelongsToCurrentOrganization($sensor->id);
    });

    it('passes when sensor belongs to current organization', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        setPermissionsOrgId($org->id);

        // This should not throw an exception
        $this->service->verifySensorBelongsToCurrentOrganization($sensor->id);

        // If we get here, the test passes
        $this->assertTrue(true);
    });

    it('passes for any sensor when organization is global', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        setPermissionsOrgId(GLOBAL_ORG_ID);

        // This should not throw an exception because global org can access any sensor
        $this->service->verifySensorBelongsToCurrentOrganization($sensor->id);

        // If we get here, the test passes
        $this->assertTrue(true);
    });
});

describe('verifyNoOverlappingAssignments', function () {
    it('passes when there are no overlapping assignments', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create();
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        // Create an existing assignment
        Assignment::factory()
            ->for($event)
            ->for($area)
            ->for($sensor)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->subDays(5),
                'active_to' => now()->subDays(3),
            ]);

        setPermissionsOrgId($org->id);

        // This should not throw an exception
        $this->service->verifyNoOverlappingAssignments(
            null,
            $sensor->id,
            false,
            now()->subDays(2)->toDateTimeString(),
            now()->addDays(2)->toDateTimeString()
        );

        // If we get here, the test passes
        $this->assertTrue(true);
    });

    it('throws when new assignment starts during an existing assignment', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create();
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        // Create an existing assignment
        Assignment::factory()
            ->for($event)
            ->for($area)
            ->for($sensor)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->subDays(5),
                'active_to' => now()->addDays(1),
            ]);

        setPermissionsOrgId($org->id);

        $this->expectException(ValidationException::class);
        $this->service->verifyNoOverlappingAssignments(
            null,
            $sensor->id,
            false,
            now()->subDays(1)->toDateTimeString(), // Starts during existing assignment
            now()->addDays(5)->toDateTimeString()
        );
    });

    it('throws when new assignment ends during an existing assignment', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create();
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        // Create an existing assignment
        Assignment::factory()
            ->for($event)
            ->for($area)
            ->for($sensor)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->subDays(1),
                'active_to' => now()->addDays(5),
            ]);

        setPermissionsOrgId($org->id);

        $this->expectException(ValidationException::class);
        $this->service->verifyNoOverlappingAssignments(
            null,
            $sensor->id,
            false,
            now()->subDays(5)->toDateTimeString(),
            now()->addDays(1)->toDateTimeString() // Ends during existing assignment
        );
    });

    it('throws when new assignment completely contains an existing assignment', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create();
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        // Create an existing assignment
        Assignment::factory()
            ->for($event)
            ->for($area)
            ->for($sensor)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->subDays(1),
                'active_to' => now()->addDays(1),
            ]);

        setPermissionsOrgId($org->id);

        $this->expectException(ValidationException::class);
        $this->service->verifyNoOverlappingAssignments(
            null,
            $sensor->id,
            false,
            now()->subDays(5)->toDateTimeString(), // Starts before existing assignment
            now()->addDays(5)->toDateTimeString()  // Ends after existing assignment
        );
    });

    it('passes when assignments have same time but different directions', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create();
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        // Create an existing assignment with IN direction
        Assignment::factory()
            ->for($event)
            ->for($area)
            ->for($sensor)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->subDays(2),
                'active_to' => now()->addDays(2),
            ]);

        setPermissionsOrgId($org->id);

        // This should not throw an exception because the direction is different
        $this->service->verifyNoOverlappingAssignments(
            null,
            $sensor->id,
            true, // Different direction
            now()->subDays(2)->toDateTimeString(),
            now()->addDays(2)->toDateTimeString()
        );

        // If we get here, the test passes
        $this->assertTrue(true);
    });

    it('excludes current assignment when updating', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->for($org, 'organization')->create();
        $area = Area::factory()->for($event)->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        // Create an assignment that we'll update
        $assignment = Assignment::factory()
            ->for($event)
            ->for($area)
            ->for($sensor)
            ->create([
                'direction_flipped' => false,
                'active_from' => now()->subDays(2),
                'active_to' => now()->addDays(2),
            ]);

        setPermissionsOrgId($org->id);

        // This should not throw an exception because we're excluding the current assignment
        $this->service->verifyNoOverlappingAssignments(
            $assignment->id, // Exclude this assignment
            $sensor->id,
            false,
            now()->subDays(2)->toDateTimeString(),
            now()->addDays(2)->toDateTimeString()
        );

        // If we get here, the test passes
        $this->assertTrue(true);
    });
});
