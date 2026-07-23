<?php

use App\Http\Controllers\StageSafety\SensorTokenController;
use App\Http\Requests\StageSafety\SensorTokenUpdateRequest;
use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use App\Models\User;
use App\Services\StageSafety\SensorService;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('regenerates a sensor token and returns it once', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($organization)->create();
    $sensor = Sensor::factory()->for($organization)->create();
    $oldToken = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $response = $this->actingAs($admin)->postJson(route('stage-safety.sensors.regenerate-token', [
        'organization' => $organization,
        'stageSafetySensor' => $sensor,
    ]));

    $response->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonStructure(['token']);

    expect(PersonalAccessToken::findToken($oldToken))->toBeNull()
        ->and(PersonalAccessToken::findToken($response->json('token')))->not->toBeNull();
});

it('revokes all sensor tokens', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($organization)->create();
    $sensor = Sensor::factory()->for($organization)->create();
    $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);
    $sensor->createToken('legacy-token');

    $this->actingAs($admin)
        ->delete(route('stage-safety.sensors.revoke-token', [
            'organization' => $organization,
            'stageSafetySensor' => $sensor,
        ]))
        ->assertRedirect(route('stage-safety.sensors.edit', [
            'organization' => $organization,
            'stageSafetySensor' => $sensor,
        ]));

    expect($sensor->tokens()->count())->toBe(0);
});

it('does not bind another organization sensor', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($organization)->create();
    $foreignSensor = Sensor::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('stage-safety.sensors.regenerate-token', [
            'organization' => $organization,
            'stageSafetySensor' => $foreignSensor,
        ]))
        ->assertNotFound();
});

it('uses the token request and organization middleware', function () {
    test()->assertRouteUsesMiddleware(
        'stage-safety.sensors.regenerate-token',
        ['permissions.organization_slug', 'auth', 'verified'],
    );
    test()->assertActionUsesFormRequest(
        SensorTokenController::class,
        'update',
        SensorTokenUpdateRequest::class,
    );
    test()->assertRouteUsesFormRequest(
        'stage-safety.sensors.regenerate-token',
        SensorTokenUpdateRequest::class,
    );
    test()->assertActionUsesFormRequest(
        SensorTokenController::class,
        'destroy',
        SensorTokenUpdateRequest::class,
    );
    test()->assertRouteUsesFormRequest(
        'stage-safety.sensors.revoke-token',
        SensorTokenUpdateRequest::class,
    );
});
