<?php

use App\Http\Controllers\Peoplecount\SensorTokenController;
use App\Http\Requests\Peoplecount\SensorTokenUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('creates a token if none exist', function () {
    $org = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($org)->create();
    $admin = User::factory()->organizationAdmin($org)->create();

    expect($sensor->api_token)->toBeNull();

    $response = test()->actingAs($admin)
        ->post(route('peoplecount.sensors.regenerate-token', [
            'organization' => $org->slug,
            'sensor' => $sensor->id,
        ]));

    $response->assertRedirect(route('peoplecount.sensors.index', ['organization' => $org->slug]));

    expect($sensor->fresh()->api_token)->not->toBeNull();
});

it('regenerates an existing token', function () {
    $org = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($org)->create();
    $admin = User::factory()->organizationAdmin($org)->create();

    $originalToken = 'original-token';
    $sensor->api_token = $originalToken;
    $sensor->save();

    expect($sensor->api_token)->toBe($originalToken);

    $response = test()->actingAs($admin)
        ->post(route('peoplecount.sensors.regenerate-token', [
            'organization' => $org->slug,
            'sensor' => $sensor->id,
        ]));

    $response->assertRedirect(route('peoplecount.sensors.index', ['organization' => $org->slug]));

    expect($sensor->fresh()->api_token)->not->toBe($originalToken)
        ->and($sensor->fresh()->api_token)->not->toBeNull()
        ->and($sensor->fresh()->api_token)->toBeString()
        ->and($sensor->fresh()->api_token)->not->toBe($originalToken);
});

it('uses the correct form requests', function () {
    // middleware
    test()->assertRouteUsesMiddleware(
        'peoplecount.sensors.regenerate-token',
        ['permissions.organization_slug', 'auth', 'verified'],
    );
    // update
    test()->assertActionUsesFormRequest(
        SensorTokenController::class,
        'update',
        SensorTokenUpdateRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.sensors.regenerate-token',
        SensorTokenUpdateRequest::class);
});
