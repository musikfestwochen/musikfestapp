<?php

use App\Http\Controllers\Peoplecount\AreaSingleResetController;
use App\Http\Requests\Peoplecount\AreaSingleResetDestroyRequest;
use App\Http\Requests\Peoplecount\AreaSingleResetStoreRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaSingleReset;
use App\Models\Peoplecount\Event;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('shows the create form for a new area single reset', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    $this->actingAs($admin)
        ->get(route('peoplecount.areas.single-resets.create', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]))
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('peoplecount/NewManualReset')
            ->has('area')
            ->where('area.id', $area->id)
            ->has('organization')
            ->where('organization.id', $org->id)
            ->where('organization.slug', $org->slug)
            ->has('status')
        );
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

    $response->assertRedirect(route('peoplecount.areas.edit', [
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

    $response->assertRedirect(route('peoplecount.areas.edit', [
        'organization' => $org->slug,
        'area' => $area->id,
    ]));

    $this->assertDatabaseMissing('peoplecount_area_single_resets', [
        'id' => $reset->id,
    ]);
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

it('uses the correct form requests', function () {
    // middleware
    test()->assertRouteUsesMiddleware(
        'peoplecount.areas.single-resets.create',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    // store
    test()->assertActionUsesFormRequest(
        AreaSingleResetController::class,
        'store',
        AreaSingleResetStoreRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.single-resets.store',
        AreaSingleResetStoreRequest::class);

    // destroy
    test()->assertActionUsesFormRequest(
        AreaSingleResetController::class,
        'destroy',
        AreaSingleResetDestroyRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.single-resets.destroy',
        AreaSingleResetDestroyRequest::class);
});
