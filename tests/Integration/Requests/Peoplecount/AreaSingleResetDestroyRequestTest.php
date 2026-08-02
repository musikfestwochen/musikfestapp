<?php

use App\Http\Requests\Peoplecount\AreaSingleResetDestroyRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaSingleReset;
use App\Models\Peoplecount\Event;
use App\Models\User;

covers(AreaSingleResetDestroyRequest::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('has correct rules', function () {
    $request = new AreaSingleResetDestroyRequest;
    expect($request->rules())->toBeEmpty();
});

it('authorizes when user can destroy area resets', function () {
    // Create a user with the permission to destroy area resets
    $admin = User::factory()->globalAdmin()->create();

    // Create the necessary models
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

    // Test the authorization by making a request to the destroy endpoint
    $response = $this->actingAs($admin)
        ->delete(route('peoplecount.areas.single-resets.destroy', [
            'organization' => $org->slug,
            'area' => $area->id,
            'single_reset' => $reset->id,
        ]));

    // Assert that the request was authorized (not a 403)
    $response->assertStatus(302); // Redirect after successful deletion
});

it('authorizes when user is the creator of the reset', function () {
    // Create a regular user without the permission to destroy area resets
    $user = User::factory()->create();

    // Create the necessary models
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    // Create a reset with the user as creator
    $reset = AreaSingleReset::factory()->create([
        'area_id' => $area->id,
        'created_by' => $user->id,
    ]);

    // Test the authorization by making a request to the destroy endpoint
    $response = $this->actingAs($user)
        ->delete(route('peoplecount.areas.single-resets.destroy', [
            'organization' => $org->slug,
            'area' => $area->id,
            'single_reset' => $reset->id,
        ]));

    // Assert that the request was authorized (not a 403)
    $response->assertStatus(302); // Redirect after successful deletion
});

it('denies authorization when user has no permission and is not the creator', function () {
    // Create two regular users without the permission to destroy area resets
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Create the necessary models
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    // Create a reset with user1 as creator
    $reset = AreaSingleReset::factory()->create([
        'area_id' => $area->id,
        'created_by' => $user1->id,
    ]);

    // Test the authorization by making a request to the destroy endpoint as user2
    $response = $this->actingAs($user2)
        ->delete(route('peoplecount.areas.single-resets.destroy', [
            'organization' => $org->slug,
            'area' => $area->id,
            'single_reset' => $reset->id,
        ]));

    // Assert that the request was not authorized
    $response->assertStatus(403); // Forbidden
});
