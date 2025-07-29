<?php

use App\Http\Controllers\Peoplecount\AreaRecurringResetController;
use App\Http\Requests\Peoplecount\AreaRecurringResetDestroyRequest;
use App\Http\Requests\Peoplecount\AreaRecurringResetIndexRequest;
use App\Http\Requests\Peoplecount\AreaRecurringResetStoreRequest;
use App\Http\Requests\Peoplecount\AreaRecurringResetUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Models\Peoplecount\Event;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('can list area recurring resets for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $resets = AreaRecurringReset::factory()->count(3)->create([
        'area_id' => $area->id,
        'event_id' => $event->id,
    ]);

    $this->actingAs($admin)
        ->get(route('peoplecount.areas.recurring-resets.index', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]))
        ->assertStatus(200);
    // TODO: Add Inertia assertions once frontend components exist
    // ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
    //     ->component('peoplecount/AreaRecurringResets')
    //     ->has('area')
    //     ->where('area.id', $area->id)
    //     ->has('resets', 3)
    //     ->has('organization')
    //     ->where('organization.id', $org->id)
    //     ->where('organization.slug', $org->slug)
    //     ->has('status')
    // );
});

it('can create an area recurring reset', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $resetData = [
        'event_id' => $event->id,
        'reset_value' => 50,
        'rrule' => 'FREQ=DAILY;INTERVAL=1',
        'timezone' => 'Europe/Zurich',
        'notes' => 'Daily recurring reset for testing',
    ];

    $response = $this->actingAs($admin)
        ->post(route('peoplecount.areas.recurring-resets.store', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]), $resetData);

    $response->assertRedirect(route('peoplecount.areas.recurring-resets.index', [
        'organization' => $org->slug,
        'area' => $area->id,
    ]));

    $this->assertDatabaseHas('peoplecount_area_recurring_resets', [
        'area_id' => $area->id,
        'event_id' => $event->id,
        'reset_value' => 50,
        'rrule' => 'FREQ=DAILY;INTERVAL=1',
        'timezone' => 'Europe/Zurich',
        'notes' => 'Daily recurring reset for testing',
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
        'event_id' => 999999, // non-existent event
        'reset_value' => -10, // negative value
        'rrule' => 'INVALID_RRULE', // invalid RRULE format
        'timezone' => 'Invalid/Timezone', // invalid timezone
        'notes' => str_repeat('a', 1001), // too long
    ];

    $this->actingAs($admin)
        ->post(route('peoplecount.areas.recurring-resets.store', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]), $invalidData)
        ->assertSessionHasErrors(['event_id', 'reset_value', 'rrule', 'timezone', 'notes']);
});

it('can show an area recurring reset', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $reset = AreaRecurringReset::factory()->create([
        'area_id' => $area->id,
        'event_id' => $event->id,
    ]);

    $this->actingAs($admin)
        ->get(route('peoplecount.areas.recurring-resets.show', [
            'organization' => $org->slug,
            'area' => $area->id,
            'recurring_reset' => $reset->id,
        ]))
        ->assertRedirect(route('peoplecount.areas.recurring-resets.edit', [
            'organization' => $org->slug,
            'area' => $area->id,
            'recurring_reset' => $reset->id,
        ]));
});

it('can update an area recurring reset', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $reset = AreaRecurringReset::factory()->create([
        'area_id' => $area->id,
        'event_id' => $event->id,
        'reset_value' => 25,
        'rrule' => 'FREQ=DAILY;INTERVAL=1',
        'timezone' => 'Europe/Zurich',
        'notes' => 'Original notes',
    ]);

    $updateData = [
        'event_id' => $event->id,
        'reset_value' => 75,
        'rrule' => 'FREQ=WEEKLY;BYDAY=MO',
        'timezone' => 'America/New_York',
        'notes' => 'Updated notes',
    ];

    $response = $this->actingAs($admin)
        ->put(route('peoplecount.areas.recurring-resets.update', [
            'organization' => $org->slug,
            'area' => $area->id,
            'recurring_reset' => $reset->id,
        ]), $updateData);

    $response->assertRedirect(route('peoplecount.areas.recurring-resets.show', [
        'organization' => $org->slug,
        'area' => $area->id,
        'recurring_reset' => $reset->id,
    ]));

    $this->assertDatabaseHas('peoplecount_area_recurring_resets', [
        'id' => $reset->id,
        'reset_value' => 75,
        'rrule' => 'FREQ=WEEKLY;BYDAY=MO',
        'timezone' => 'America/New_York',
        'notes' => 'Updated notes',
    ]);
});

it('validates reset data when updating', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $reset = AreaRecurringReset::factory()->create([
        'area_id' => $area->id,
        'event_id' => $event->id,
    ]);

    // Test with invalid data
    $invalidData = [
        'event_id' => 999999, // non-existent event
        'reset_value' => -10, // negative value
        'rrule' => 'INVALID_RRULE', // invalid RRULE format
        'timezone' => 'Invalid/Timezone', // invalid timezone
        'notes' => str_repeat('a', 1001), // too long
    ];

    $this->actingAs($admin)
        ->put(route('peoplecount.areas.recurring-resets.update', [
            'organization' => $org->slug,
            'area' => $area->id,
            'recurring_reset' => $reset->id,
        ]), $invalidData)
        ->assertSessionHasErrors(['event_id', 'reset_value', 'rrule', 'timezone', 'notes']);
});

it('can delete an area recurring reset', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $reset = AreaRecurringReset::factory()->create([
        'area_id' => $area->id,
        'event_id' => $event->id,
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('peoplecount.areas.recurring-resets.destroy', [
            'organization' => $org->slug,
            'area' => $area->id,
            'recurring_reset' => $reset->id,
        ]));

    $response->assertRedirect(route('peoplecount.areas.recurring-resets.index', [
        'organization' => $org->slug,
        'area' => $area->id,
    ]));

    $this->assertDatabaseMissing('peoplecount_area_recurring_resets', [
        'id' => $reset->id,
    ]);
});

it('requires proper permissions for index', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    $this->actingAs($user)
        ->get(route('peoplecount.areas.recurring-resets.index', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]))
        ->assertStatus(403);
});

it('requires proper permissions for store', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    $resetData = [
        'event_id' => $event->id,
        'reset_value' => 50,
        'rrule' => 'FREQ=DAILY;INTERVAL=1',
        'timezone' => 'Europe/Zurich',
        'notes' => 'Should fail',
    ];

    $this->actingAs($user)
        ->post(route('peoplecount.areas.recurring-resets.store', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]), $resetData)
        ->assertStatus(403);
});

it('requires proper permissions for update', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $reset = AreaRecurringReset::factory()->create([
        'area_id' => $area->id,
        'event_id' => $event->id,
    ]);

    $updateData = [
        'event_id' => $event->id,
        'reset_value' => 75,
        'rrule' => 'FREQ=WEEKLY;BYDAY=MO',
        'timezone' => 'America/New_York',
        'notes' => 'Should fail',
    ];

    $this->actingAs($user)
        ->put(route('peoplecount.areas.recurring-resets.update', [
            'organization' => $org->slug,
            'area' => $area->id,
            'recurring_reset' => $reset->id,
        ]), $updateData)
        ->assertStatus(403);
});

it('requires proper permissions for destroy', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);
    $reset = AreaRecurringReset::factory()->create([
        'area_id' => $area->id,
        'event_id' => $event->id,
    ]);

    $this->actingAs($user)
        ->delete(route('peoplecount.areas.recurring-resets.destroy', [
            'organization' => $org->slug,
            'area' => $area->id,
            'recurring_reset' => $reset->id,
        ]))
        ->assertStatus(403);
});

it('uses the correct form requests', function () {
    // middleware
    test()->assertRouteUsesMiddleware(
        'peoplecount.areas.recurring-resets.index',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    // destroy
    test()->assertActionUsesFormRequest(
        AreaRecurringResetController::class,
        'destroy',
        AreaRecurringResetDestroyRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.recurring-resets.destroy',
        AreaRecurringResetDestroyRequest::class);

    // index
    test()->assertActionUsesFormRequest(
        AreaRecurringResetController::class,
        'index',
        AreaRecurringResetIndexRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.recurring-resets.index',
        AreaRecurringResetIndexRequest::class);

    // store
    test()->assertActionUsesFormRequest(
        AreaRecurringResetController::class,
        'store',
        AreaRecurringResetStoreRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.recurring-resets.store',
        AreaRecurringResetStoreRequest::class);

    // update
    test()->assertActionUsesFormRequest(
        AreaRecurringResetController::class,
        'update',
        AreaRecurringResetUpdateRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.recurring-resets.update',
        AreaRecurringResetUpdateRequest::class);
});
