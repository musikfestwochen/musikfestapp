<?php

use App\Http\Controllers\Peoplecount\AreaSingleResetController;
use App\Http\Requests\Peoplecount\DestroyAreaSingleResetRequest;
use App\Http\Requests\Peoplecount\IndexAreaSingleResetRequest;
use App\Http\Requests\Peoplecount\ShowAreaSingleResetRequest;
use App\Http\Requests\Peoplecount\StoreAreaSingleResetRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaSingleReset;
use App\Models\Peoplecount\Event;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('can list area single resets for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $resets = AreaSingleReset::factory()->count(3)->create([
        'area_id' => $area->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('peoplecount.areas.single-resets.index', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]))
        ->assertStatus(200);
    // TODO: Add Inertia assertions once frontend components exist
    // ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
    //     ->component('peoplecount/AreaSingleResets')
    //     ->has('area')
    //     ->where('area.id', $area->id)
    //     ->has('resets', 3)
    //     ->has('organization')
    //     ->where('organization.id', $org->id)
    //     ->where('organization.slug', $org->slug)
    //     ->has('status')
    // );
});

it('can create an area single reset', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $resetData = [
        'reset_value' => 50,
        'effective_at' => '2025-07-27T15:00:00',
        'notes' => 'Manual reset for testing',
    ];

    $response = $this->actingAs($admin)
        ->post(route('peoplecount.areas.single-resets.store', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]), $resetData);

    $response->assertRedirect(route('peoplecount.areas.single-resets.index', [
        'organization' => $org->slug,
        'area' => $area->id,
    ]));

    $this->assertDatabaseHas('peoplecount_area_single_resets', [
        'area_id' => $area->id,
        'reset_value' => 50,
        'notes' => 'Manual reset for testing',
        'created_by' => $admin->id,
    ]);
});

it('validates reset data when creating', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    // Test with invalid data
    $invalidData = [
        'reset_value' => -10, // negative value
        'effective_at' => 'invalid-date',
        'notes' => str_repeat('a', 1001), // too long
    ];

    $this->actingAs($admin)
        ->post(route('peoplecount.areas.single-resets.store', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]), $invalidData)
        ->assertSessionHasErrors(['reset_value', 'effective_at', 'notes']);
});

it('can show an area single reset', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $reset = AreaSingleReset::factory()->create([
        'area_id' => $area->id,
        'created_by' => $admin->id,
        'reset_value' => 75,
        'notes' => 'Test reset',
    ]);

    $this->actingAs($admin)
        ->get(route('peoplecount.areas.single-resets.show', [
            'organization' => $org->slug,
            'area' => $area->id,
            'single_reset' => $reset->id,
        ]))
        ->assertStatus(200);
    // TODO: Add Inertia assertions once frontend components exist
    // ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
    //     ->component('peoplecount/ShowAreaSingleReset')
    //     ->has('area')
    //     ->where('area.id', $area->id)
    //     ->has('reset')
    //     ->where('reset.id', $reset->id)
    //     ->where('reset.reset_value', 75)
    //     ->where('reset.notes', 'Test reset')
    //     ->has('reset.createdBy')
    //     ->where('reset.createdBy.id', $admin->id)
    //     ->has('organization')
    //     ->where('organization.id', $org->id)
    //     ->has('status')
    // );
});

it('can delete an area single reset', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $reset = AreaSingleReset::factory()->create([
        'area_id' => $area->id,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('peoplecount.areas.single-resets.destroy', [
            'organization' => $org->slug,
            'area' => $area->id,
            'single_reset' => $reset->id,
        ]));

    $response->assertRedirect(route('peoplecount.areas.single-resets.index', [
        'organization' => $org->slug,
        'area' => $area->id,
    ]));

    $this->assertDatabaseMissing('peoplecount_area_single_resets', [
        'id' => $reset->id,
    ]);
});

it('requires proper permissions for index action', function () {
    $user = User::factory()->create(); // Regular user without permissions
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    $this->actingAs($user)
        ->get(route('peoplecount.areas.single-resets.index', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]))
        ->assertStatus(403);
});

it('requires proper permissions for store action', function () {
    $user = User::factory()->create(); // Regular user without permissions
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    $this->actingAs($user)
        ->post(route('peoplecount.areas.single-resets.store', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]), [
            'reset_value' => 50,
            'effective_at' => '2025-07-27T15:00:00',
        ])
        ->assertStatus(403);
});

it('requires proper permissions for show action', function () {
    $user = User::factory()->create(); // Regular user without permissions
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $reset = AreaSingleReset::factory()->create([
        'area_id' => $area->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($user)
        ->get(route('peoplecount.areas.single-resets.show', [
            'organization' => $org->slug,
            'area' => $area->id,
            'single_reset' => $reset->id,
        ]))
        ->assertStatus(403);
});

it('requires proper permissions for destroy action', function () {
    $user = User::factory()->create(); // Regular user without permissions
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $reset = AreaSingleReset::factory()->create([
        'area_id' => $area->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($user)
        ->delete(route('peoplecount.areas.single-resets.destroy', [
            'organization' => $org->slug,
            'area' => $area->id,
            'single_reset' => $reset->id,
        ]))
        ->assertStatus(403);
});

it('handles organization admin permissions correctly', function () {
    $org = Organization::factory()->create();
    $orgAdmin = User::factory()->organizationAdmin($org)->create();

    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    // Organization admin should be able to access their organization's areas
    $this->actingAs($orgAdmin)
        ->get(route('peoplecount.areas.single-resets.index', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]))
        ->assertStatus(200);
});

it('uses the correct form requests', function () {
    // middleware
    test()->assertRouteUsesMiddleware(
        'peoplecount.areas.single-resets.index',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    // index
    test()->assertActionUsesFormRequest(
        AreaSingleResetController::class,
        'index',
        IndexAreaSingleResetRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.single-resets.index',
        IndexAreaSingleResetRequest::class);

    // store
    test()->assertActionUsesFormRequest(
        AreaSingleResetController::class,
        'store',
        StoreAreaSingleResetRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.single-resets.store',
        StoreAreaSingleResetRequest::class);

    // show
    test()->assertActionUsesFormRequest(
        AreaSingleResetController::class,
        'show',
        ShowAreaSingleResetRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.single-resets.show',
        ShowAreaSingleResetRequest::class);

    // destroy
    test()->assertActionUsesFormRequest(
        AreaSingleResetController::class,
        'destroy',
        DestroyAreaSingleResetRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.single-resets.destroy',
        DestroyAreaSingleResetRequest::class);
});
