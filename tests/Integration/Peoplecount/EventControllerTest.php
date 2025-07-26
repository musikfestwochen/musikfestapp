<?php

use App\Http\Controllers\Peoplecount\EventController;
use App\Http\Requests\Peoplecount\EventCreateRequest;
use App\Http\Requests\Peoplecount\EventDestroyRequest;
use App\Http\Requests\Peoplecount\EventEditRequest;
use App\Http\Requests\Peoplecount\EventIndexRequest;
use App\Http\Requests\Peoplecount\EventShowRequest;
use App\Http\Requests\Peoplecount\EventStoreRequest;
use App\Http\Requests\Peoplecount\EventUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('can list events for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $events = Event::factory()->count(3)->create([
        'organization_id' => $org->id,
    ]);

    $this->actingAs($admin)
        ->get(route('peoplecount.events.index', ['organization' => $org->slug]))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('peoplecount/Events')
            ->has('events')
            ->has('organization')
        );
});

it('shows the create event form for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $this->actingAs($admin)
        ->get(route('peoplecount.events.create', ['organization' => $org->slug]))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('peoplecount/NewEvent')
            ->has('organization')
        );
});

it('can create an event for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $eventData = [
        'name' => 'Test Event',
        'starts_at' => now()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
    ];

    $response = $this->actingAs($admin)
        ->post(route('peoplecount.events.store', ['organization' => $org->slug]), $eventData);
    $response->assertRedirect(route('peoplecount.events.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('peoplecount_events', [
        'name' => 'Test Event',
        'organization_id' => $org->id,
        'deleted_at' => null,
    ]);
});

it('shows the edit event form for an organization event', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);

    // Create areas and assignments for the event
    $area1 = Area::factory()->create(['event_id' => $event->id, 'name' => 'Test Area 1']);
    $area2 = Area::factory()->create(['event_id' => $event->id, 'name' => 'Test Area 2']);
    $assignment1 = Assignment::factory()->create(['event_id' => $event->id]);
    $assignment2 = Assignment::factory()->create(['event_id' => $event->id]);

    $this->actingAs($admin)
        ->get(route('peoplecount.events.edit', ['organization' => $org->slug, 'event' => $event->id]))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('peoplecount/EditEvent')
            ->has('organization')
            ->has('event')
            ->has('event.areas', 2)
            ->has('event.assignments', 2)
            ->where('event.areas.0.name', 'Test Area 1')
            ->where('event.areas.1.name', 'Test Area 2')
        );
});

it('can update an event for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Original Name',
    ]);
    $newName = 'Updated Name';

    $response = $this->actingAs($admin)
        ->put(route('peoplecount.events.update', ['organization' => $org->slug, 'event' => $event->id]), [
            'name' => $newName,
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
        ]);
    $response->assertRedirect(route('peoplecount.events.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('peoplecount_events', [
        'id' => $event->id,
        'name' => $newName,
        'deleted_at' => null,
    ]);
});

it('can delete an event for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Delete Me Event',
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('peoplecount.events.destroy', ['organization' => $org->slug, 'event' => $event->id]));
    $response->assertRedirect(route('peoplecount.events.index', ['organization' => $org->slug]));
    $this->assertSoftDeleted('peoplecount_events', [
        'id' => $event->id,
        'name' => 'Delete Me Event',
    ]);
});

it('redirects show to edit for an organization event', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('peoplecount.events.show', ['organization' => $org->slug, 'event' => $event->id]));
    $response->assertRedirect(route('peoplecount.events.edit', ['organization' => $org->slug, 'event' => $event->id]));
});

it('uses the correct form requests', function () {
    // middleware
    test()->assertRouteUsesMiddleware(
        'peoplecount.events.index',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    // create
    test()->assertActionUsesFormRequest(
        EventController::class,
        'create',
        EventCreateRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.events.create',
        EventCreateRequest::class);

    // destroy
    test()->assertActionUsesFormRequest(
        EventController::class,
        'destroy',
        EventDestroyRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.events.destroy',
        EventDestroyRequest::class);

    // edit
    test()->assertActionUsesFormRequest(
        EventController::class,
        'edit',
        EventEditRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.events.edit',
        EventEditRequest::class);

    // index
    test()->assertActionUsesFormRequest(
        EventController::class,
        'index',
        EventIndexRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.events.index',
        EventIndexRequest::class);

    // show
    test()->assertActionUsesFormRequest(
        EventController::class,
        'show',
        EventShowRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.events.show',
        EventShowRequest::class);

    // store
    test()->assertActionUsesFormRequest(
        EventController::class,
        'store',
        EventStoreRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.events.store',
        EventStoreRequest::class);

    // update
    test()->assertActionUsesFormRequest(
        EventController::class,
        'update',
        EventUpdateRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.events.update',
        EventUpdateRequest::class);
});
