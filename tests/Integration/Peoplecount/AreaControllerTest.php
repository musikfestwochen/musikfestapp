<?php

use App\Http\Controllers\Peoplecount\AreaController;
use App\Http\Requests\Peoplecount\AreaCreateRequest;
use App\Http\Requests\Peoplecount\AreaDestroyRequest;
use App\Http\Requests\Peoplecount\AreaEditRequest;
use App\Http\Requests\Peoplecount\AreaIndexRequest;
use App\Http\Requests\Peoplecount\AreaShowRequest;
use App\Http\Requests\Peoplecount\AreaStoreRequest;
use App\Http\Requests\Peoplecount\AreaUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Models\Peoplecount\AreaSingleReset;
use App\Models\Peoplecount\Event;
use App\Models\User;
use Database\Factories\Peoplecount\AlertFactory;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('can list areas for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $areas = Area::factory()->count(3)->create([
        'event_id' => $event->id,
    ]);

    $this->actingAs($admin)
        ->get(route('peoplecount.areas.index', ['organization' => $org->slug]))
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('peoplecount/Areas')
            ->has('areas', 3)
            ->has('organization')
            ->where('organization.id', $org->id)
            ->where('organization.slug', $org->slug)
            ->has('status')
        );
});

it('shows the create area form for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);

    $this->actingAs($admin)
        ->get(route('peoplecount.areas.create', ['organization' => $org->slug]))
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('peoplecount/NewArea')
            ->has('organization')
            ->where('organization.id', $org->id)
            ->where('organization.slug', $org->slug)
            ->has('events', 1)
            ->has('events.0', fn (Assert $page): Assert => $page
                ->where('id', $event->id)
                ->where('organization_id', $org->id)
                ->etc()
            )
            ->has('status')
        );
});

it('can create an area for an organization event', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $areaData = [
        'name' => 'Test Area',
        'event_id' => $event->id,
    ];

    $response = $this->actingAs($admin)
        ->post(route('peoplecount.areas.store', ['organization' => $org->slug]), $areaData);
    $response->assertRedirect(route('peoplecount.areas.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('peoplecount_areas', [
        'name' => 'Test Area',
        'event_id' => $event->id,
        'deleted_at' => null,
    ]);
});

it('shows the edit area form for an organization area with alert options and lazy alerts', function () {
    Date::setTestNow('2024-01-15 06:00:00 UTC');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    // Org users for alerts_users prop (and one outsider)
    $uA = User::factory()->create(['name' => 'Alice']);
    $uB = User::factory()->create(['name' => 'Bob']);
    $uC = User::factory()->create(['name' => 'Charlie']);
    $uOutside = User::factory()->create(['name' => 'Zed']);
    $uA->organizations()->attach($org->id);
    $uB->organizations()->attach($org->id);
    $uC->organizations()->attach($org->id);
    // Outside user belongs to another org
    $otherOrg = Organization::factory()->create();
    $uOutside->organizations()->attach($otherOrg->id);

    // Create some single resets for the area (unrelated but existing behavior)
    AreaSingleReset::factory()->count(2)->create([
        'area_id' => $area->id,
        'created_by' => $admin->id,
    ]);
    AreaRecurringReset::factory()->create([
        'area_id' => $area->id,
        'reset_time' => '08:00',
        'timezone' => 'Europe/Zurich',
    ]);

    // Create a couple of alerts to be returned on partial reload
    $alert1 = AlertFactory::new()->create([
        'area_id' => $area->id,
        'created_by' => $admin->id,
    ]);
    $alert2 = AlertFactory::new()->create([
        'area_id' => $area->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('peoplecount.areas.edit', ['organization' => $org->slug, 'area' => $area->id]))
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('peoplecount/EditArea')
            ->has('organization')
            ->where('organization.id', $org->id)
            ->where('organization.slug', $org->slug)
            ->has('area', fn (Assert $page): Assert => $page
                ->where('id', $area->id)
                ->where('event_id', $event->id)
                ->has('event')
                ->has('assignments')
                ->where('area_recurring_resets.0.next_occurrence', '2024-01-15T07:00:00.000000Z')
                ->etc()
            )
            ->has('events', 1)
            ->has('events.0', fn (Assert $page): Assert => $page
                ->where('id', $event->id)
                ->where('organization_id', $org->id)
                ->etc()
            )
            // New functionality: options and users
            ->has('alertTypeOptions')
            ->has('alertChannelOptions')
            ->has('alerts_users', fn (Assert $page): Assert => $page
                ->has(3)
                // Sorted by name asc: Alice, Bob, Charlie
                ->where('0.name', 'Alice')
                ->where('1.name', 'Bob')
                ->where('2.name', 'Charlie')
                ->etc()
            )
            // Optional alerts should be missing initially
            ->missing('alerts')
            ->has('status')
            // On partial reload, alerts are present with two items
            ->reloadOnly('alerts', fn (Assert $reload): Assert => $reload
                ->has('alerts', 2)
                ->has('alerts.0', fn (Assert $page): Assert => $page
                    ->has('id')
                    ->has('type')
                    ->has('channel')
                    ->has('recipients_count')
                    ->etc()
                )
            )
        );
});

it('can update an area for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
        'name' => 'Original Name',
    ]);
    $newName = 'Updated Name';

    $response = $this->actingAs($admin)
        ->put(route('peoplecount.areas.update', ['organization' => $org->slug, 'area' => $area->id]), [
            'name' => $newName,
            'event_id' => $event->id,
        ]);
    $response->assertRedirect(route('peoplecount.areas.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('peoplecount_areas', [
        'id' => $area->id,
        'name' => $newName,
        'deleted_at' => null,
    ]);
});

it('can delete an area for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
        'name' => 'Delete Me Area',
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('peoplecount.areas.destroy', ['organization' => $org->slug, 'area' => $area->id]));
    $response->assertRedirect(route('peoplecount.areas.index', ['organization' => $org->slug]));
    $this->assertSoftDeleted('peoplecount_areas', [
        'id' => $area->id,
        'name' => 'Delete Me Area',
    ]);
});

it('redirects show to edit for an organization area', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('peoplecount.areas.show', ['organization' => $org->slug, 'area' => $area->id]));
    $response->assertRedirect(route('peoplecount.areas.edit', ['organization' => $org->slug, 'area' => $area->id]));
});

it('uses the correct form requests', function () {
    // middleware
    test()->assertRouteUsesMiddleware(
        'peoplecount.areas.index',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    // create
    test()->assertActionUsesFormRequest(
        AreaController::class,
        'create',
        AreaCreateRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.create',
        AreaCreateRequest::class);

    // destroy
    test()->assertActionUsesFormRequest(
        AreaController::class,
        'destroy',
        AreaDestroyRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.destroy',
        AreaDestroyRequest::class);

    // edit
    test()->assertActionUsesFormRequest(
        AreaController::class,
        'edit',
        AreaEditRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.edit',
        AreaEditRequest::class);

    // index
    test()->assertActionUsesFormRequest(
        AreaController::class,
        'index',
        AreaIndexRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.index',
        AreaIndexRequest::class);

    // show
    test()->assertActionUsesFormRequest(
        AreaController::class,
        'show',
        AreaShowRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.show',
        AreaShowRequest::class);

    // store
    test()->assertActionUsesFormRequest(
        AreaController::class,
        'store',
        AreaStoreRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.store',
        AreaStoreRequest::class);

    // update
    test()->assertActionUsesFormRequest(
        AreaController::class,
        'update',
        AreaUpdateRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.update',
        AreaUpdateRequest::class);
});
