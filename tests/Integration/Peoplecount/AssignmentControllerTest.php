<?php

use App\Http\Controllers\Peoplecount\AssignmentController;
use App\Http\Requests\Peoplecount\AssignmentCreateRequest;
use App\Http\Requests\Peoplecount\AssignmentDestroyRequest;
use App\Http\Requests\Peoplecount\AssignmentEditRequest;
use App\Http\Requests\Peoplecount\AssignmentIndexRequest;
use App\Http\Requests\Peoplecount\AssignmentShowRequest;
use App\Http\Requests\Peoplecount\AssignmentStoreRequest;
use App\Http\Requests\Peoplecount\AssignmentUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\Sensor;
use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('can list assignments for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org, 'organization')->create();
    $area = Area::factory()->for($event)->create();
    $sensor = Sensor::factory()->for($org)->create();

    $assignments = Assignment::factory()->count(3)
        ->for($event)
        ->for($area)
        ->for($sensor)
        ->create();

    $this->actingAs($admin)
        ->get(route('peoplecount.assignments.index', ['organization' => $org->slug]))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('peoplecount/Assignments')
            ->has('assignments')
            ->has('organization')
        );
});

it('shows the create assignment form for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $this->actingAs($admin)
        ->get(route('peoplecount.assignments.create', ['organization' => $org->slug]))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('peoplecount/NewAssignment')
            ->has('organization')
        );
});

it('can create an assignment for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org, 'organization')->create([
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->addDays(5),
    ]);
    $area = Area::factory()->for($event)->create();
    $sensor = Sensor::factory()->for($org)->create();

    $assignmentData = [
        'event_id' => (string) $event->id,
        'area_id' => (string) $area->id,
        'sensor_id' => (string) $sensor->id,
        'direction_flipped' => '0',
        'active_from' => now()->subDays(2)->toIso8601ZuluString('millisecond'),
        'active_to' => now()->addDays(2)->toIso8601ZuluString('millisecond'),
    ];

    $response = $this->actingAs($admin)
        ->post(route('peoplecount.assignments.store', ['organization' => $org->slug]), $assignmentData);
    $response->assertRedirect(route('peoplecount.assignments.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('peoplecount_assignments', [
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'deleted_at' => null,
    ]);
});

it('shows the edit assignment form for an organization assignment', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org, 'organization')->create();
    $area = Area::factory()->for($event)->create();
    $sensor = Sensor::factory()->for($org)->create();
    $assignment = Assignment::factory()
        ->for($event)
        ->for($area)
        ->for($sensor)
        ->create();

    $this->actingAs($admin)
        ->get(route('peoplecount.assignments.edit', ['organization' => $org->slug, 'assignment' => $assignment->id]))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('peoplecount/EditAssignment')
            ->has('organization')
            ->has('assignment')
            ->where('assignment.id', $assignment->id)
        );
});

it('shows archived current sensor when editing an existing assignment', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org, 'organization')->create();
    $area = Area::factory()->for($event)->create();
    $sensor = Sensor::factory()->for($org)->create([
        'archived_at' => now(),
    ]);
    $assignment = Assignment::factory()
        ->for($event)
        ->for($area)
        ->for($sensor)
        ->create();

    $this->actingAs($admin)
        ->get(route('peoplecount.assignments.edit', ['organization' => $org->slug, 'assignment' => $assignment->id]))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('peoplecount/EditAssignment')
            ->has('sensors', 1)
            ->where('sensors.0.id', $sensor->id)
        );
});

it('does not show edit form for another organization assignment', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $foreignOrg = Organization::factory()->create();
    $foreignEvent = Event::factory()->for($foreignOrg, 'organization')->create();
    $foreignArea = Area::factory()->for($foreignEvent)->create();
    $foreignSensor = Sensor::factory()->for($foreignOrg)->create();
    $foreignAssignment = Assignment::factory()
        ->for($foreignEvent)
        ->for($foreignArea)
        ->for($foreignSensor)
        ->create();

    $this->actingAs($admin)
        ->get(route('peoplecount.assignments.edit', ['organization' => $org->slug, 'assignment' => $foreignAssignment->id]))
        ->assertNotFound();
});

it('can update an assignment for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org, 'organization')->create([
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->addDays(5),
    ]);
    $area = Area::factory()->for($event)->create();
    $sensor = Sensor::factory()->for($org)->create();
    $assignment = Assignment::factory()
        ->for($event)
        ->for($area)
        ->for($sensor)
        ->create([
            'direction_flipped' => false,
            'active_from' => now()->subDays(2),
            'active_to' => now()->addDays(2),
        ]);

    $newDirection = true;

    $response = $this->actingAs($admin)
        ->put(route('peoplecount.assignments.update', ['organization' => $org->slug, 'assignment' => $assignment->id]), [
            'event_id' => (string) $event->id,
            'area_id' => (string) $area->id,
            'sensor_id' => (string) $sensor->id,
            'direction_flipped' => '1',
            'active_from' => now()->subDays(1)->toIso8601ZuluString('millisecond'),
            'active_to' => now()->addDays(1)->toIso8601ZuluString('millisecond'),
        ]);
    $response->assertRedirect(route('peoplecount.assignments.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('peoplecount_assignments', [
        'id' => $assignment->id,
        'direction_flipped' => $newDirection,
        'deleted_at' => null,
    ]);
});

it('does not update another organization assignment', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org, 'organization')->create([
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->addDays(5),
    ]);
    $area = Area::factory()->for($event)->create();
    $sensor = Sensor::factory()->for($org)->create();
    $foreignOrg = Organization::factory()->create();
    $foreignEvent = Event::factory()->for($foreignOrg, 'organization')->create([
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->addDays(5),
    ]);
    $foreignArea = Area::factory()->for($foreignEvent)->create();
    $foreignSensor = Sensor::factory()->for($foreignOrg)->create();
    $foreignAssignment = Assignment::factory()
        ->for($foreignEvent)
        ->for($foreignArea)
        ->for($foreignSensor)
        ->create(['direction_flipped' => false]);

    $this->actingAs($admin)
        ->put(route('peoplecount.assignments.update', ['organization' => $org->slug, 'assignment' => $foreignAssignment->id]), [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => true,
            'active_from' => now()->subDay()->toIso8601ZuluString('millisecond'),
            'active_to' => now()->addDay()->toIso8601ZuluString('millisecond'),
        ])
        ->assertNotFound();

    expect($foreignAssignment->refresh()->direction_flipped)->toBeFalse();
});

it('can delete an assignment for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org, 'organization')->create();
    $area = Area::factory()->for($event)->create();
    $sensor = Sensor::factory()->for($org)->create();
    $assignment = Assignment::factory()
        ->for($event)
        ->for($area)
        ->for($sensor)
        ->create();

    $response = $this->actingAs($admin)
        ->delete(route('peoplecount.assignments.destroy', ['organization' => $org->slug, 'assignment' => $assignment->id]));
    $response->assertRedirect(route('peoplecount.assignments.index', ['organization' => $org->slug]));
    $this->assertSoftDeleted('peoplecount_assignments', [
        'id' => $assignment->id,
    ]);
});

it('does not delete another organization assignment', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $foreignOrg = Organization::factory()->create();
    $foreignEvent = Event::factory()->for($foreignOrg, 'organization')->create();
    $foreignArea = Area::factory()->for($foreignEvent)->create();
    $foreignSensor = Sensor::factory()->for($foreignOrg)->create();
    $foreignAssignment = Assignment::factory()
        ->for($foreignEvent)
        ->for($foreignArea)
        ->for($foreignSensor)
        ->create();

    $this->actingAs($admin)
        ->delete(route('peoplecount.assignments.destroy', ['organization' => $org->slug, 'assignment' => $foreignAssignment->id]))
        ->assertNotFound();

    $this->assertNotSoftDeleted('peoplecount_assignments', ['id' => $foreignAssignment->id]);
});

it('redirects show to edit for an organization assignment', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org, 'organization')->create();
    $area = Area::factory()->for($event)->create();
    $sensor = Sensor::factory()->for($org)->create();
    $assignment = Assignment::factory()
        ->for($event)
        ->for($area)
        ->for($sensor)
        ->create();

    $response = $this->actingAs($admin)
        ->get(route('peoplecount.assignments.show', ['organization' => $org->slug, 'assignment' => $assignment->id]));
    $response->assertRedirect(route('peoplecount.assignments.edit', ['organization' => $org->slug, 'assignment' => $assignment->id]));
});

it('does not show another organization assignment', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $foreignOrg = Organization::factory()->create();
    $foreignEvent = Event::factory()->for($foreignOrg, 'organization')->create();
    $foreignArea = Area::factory()->for($foreignEvent)->create();
    $foreignSensor = Sensor::factory()->for($foreignOrg)->create();
    $foreignAssignment = Assignment::factory()
        ->for($foreignEvent)
        ->for($foreignArea)
        ->for($foreignSensor)
        ->create();

    $this->actingAs($admin)
        ->get(route('peoplecount.assignments.show', ['organization' => $org->slug, 'assignment' => $foreignAssignment->id]))
        ->assertNotFound();
});

it('rejects non-ISO 8601 UTC datetimes when creating an assignment', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org, 'organization')->create([
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->addDays(5),
    ]);
    $area = Area::factory()->for($event)->create();
    $sensor = Sensor::factory()->for($org)->create();

    $response = $this->actingAs($admin)
        ->post(route('peoplecount.assignments.store', ['organization' => $org->slug]), [
            'event_id' => (string) $event->id,
            'area_id' => (string) $area->id,
            'sensor_id' => (string) $sensor->id,
            'direction_flipped' => '0',
            'active_from' => now()->subDays(2)->toDateTimeString(),
            'active_to' => now()->addDays(2)->toDateTimeString(),
        ]);

    $response->assertSessionHasErrors(['active_from', 'active_to']);
});

it('validates overlapping assignments on create', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org, 'organization')->create([
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->addDays(5),
    ]);
    $area = Area::factory()->for($event)->create();
    $sensor = Sensor::factory()->for($org)->create();

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

    // Try to create an overlapping assignment
    $assignmentData = [
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => now()->subDays(2)->toIso8601ZuluString('millisecond'),
        'active_to' => now()->addDays(2)->toIso8601ZuluString('millisecond'),
    ];

    $response = $this->actingAs($admin)
        ->post(route('peoplecount.assignments.store', ['organization' => $org->slug]), $assignmentData);

    $response->assertSessionHasErrors(['sensor_id', 'direction_flipped', 'active_from', 'active_to']);
});

it('validates assignment time within event time on create', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org, 'organization')->create([
        'starts_at' => now()->addDays(1),
        'ends_at' => now()->addDays(5),
    ]);
    $area = Area::factory()->for($event)->create();
    $sensor = Sensor::factory()->for($org)->create();

    // Try to create an assignment with time outside event time
    $assignmentData = [
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => now()->subDays(2)->toIso8601ZuluString('millisecond'), // Before event starts
        'active_to' => now()->addDays(2)->toIso8601ZuluString('millisecond'),
    ];

    $response = $this->actingAs($admin)
        ->post(route('peoplecount.assignments.store', ['organization' => $org->slug]), $assignmentData);

    $response->assertSessionHasErrors(['active_from', 'active_to']);
});

it('validates overlapping assignments on update', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org, 'organization')->create([
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->addDays(5),
    ]);
    $area = Area::factory()->for($event)->create();
    $sensor = Sensor::factory()->for($org)->create();

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

    // Try to update the second assignment to overlap with the first one
    $updateData = [
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => now()->subDays(2)->toIso8601ZuluString('millisecond'), // Overlaps with first assignment
        'active_to' => now()->addDays(2)->toIso8601ZuluString('millisecond'),
    ];

    $response = $this->actingAs($admin)
        ->put(route('peoplecount.assignments.update', ['organization' => $org->slug, 'assignment' => $assignment2->id]), $updateData);

    $response->assertSessionHasErrors(['sensor_id', 'direction_flipped', 'active_from', 'active_to']);
});

it('validates assignment time within event time on update', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org, 'organization')->create([
        'starts_at' => now()->addDays(1),
        'ends_at' => now()->addDays(5),
    ]);
    $area = Area::factory()->for($event)->create();
    $sensor = Sensor::factory()->for($org)->create();
    $assignment = Assignment::factory()
        ->for($event)
        ->for($area)
        ->for($sensor)
        ->create([
            'direction_flipped' => false,
            'active_from' => now()->addDays(2),
            'active_to' => now()->addDays(4),
        ]);

    // Try to update the assignment with time outside event time
    $updateData = [
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => now()->subDays(2)->toIso8601ZuluString('millisecond'), // Before event starts
        'active_to' => now()->addDays(2)->toIso8601ZuluString('millisecond'),
    ];

    $response = $this->actingAs($admin)
        ->put(route('peoplecount.assignments.update', ['organization' => $org->slug, 'assignment' => $assignment->id]), $updateData);

    $response->assertSessionHasErrors(['active_from', 'active_to']);
});

it('uses the correct form requests', function () {
    // middleware
    test()->assertRouteUsesMiddleware(
        'peoplecount.assignments.index',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    // create
    test()->assertActionUsesFormRequest(
        AssignmentController::class,
        'create',
        AssignmentCreateRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.assignments.create',
        AssignmentCreateRequest::class);

    // destroy
    test()->assertActionUsesFormRequest(
        AssignmentController::class,
        'destroy',
        AssignmentDestroyRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.assignments.destroy',
        AssignmentDestroyRequest::class);

    // edit
    test()->assertActionUsesFormRequest(
        AssignmentController::class,
        'edit',
        AssignmentEditRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.assignments.edit',
        AssignmentEditRequest::class);

    // index
    test()->assertActionUsesFormRequest(
        AssignmentController::class,
        'index',
        AssignmentIndexRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.assignments.index',
        AssignmentIndexRequest::class);

    // show
    test()->assertActionUsesFormRequest(
        AssignmentController::class,
        'show',
        AssignmentShowRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.assignments.show',
        AssignmentShowRequest::class);

    // store
    test()->assertActionUsesFormRequest(
        AssignmentController::class,
        'store',
        AssignmentStoreRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.assignments.store',
        AssignmentStoreRequest::class);

    // update
    test()->assertActionUsesFormRequest(
        AssignmentController::class,
        'update',
        AssignmentUpdateRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.assignments.update',
        AssignmentUpdateRequest::class);
});
