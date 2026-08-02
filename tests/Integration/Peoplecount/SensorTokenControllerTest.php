<?php

use App\Http\Controllers\Peoplecount\SensorTokenController;
use App\Http\Requests\Peoplecount\SensorTokenUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Models\User;
use App\Services\Peoplecount\SensorService;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('regenerates a sensor token and returns it once', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($organization)->create();
    $sensor = Sensor::factory()->for($organization)->create();
    $oldToken = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $response = $this->actingAs($admin)->postJson(route('peoplecount.sensors.regenerate-token', [
        'organization' => $organization,
        'sensor' => $sensor,
    ]));

    $response->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonStructure(['token']);

    expect(PersonalAccessToken::findToken($oldToken))->toBeNull()
        ->and($response->json('token'))->not->toContain('|')
        ->and(PersonalAccessToken::findToken($response->json('token')))->not->toBeNull();
});

it('revokes all sensor tokens', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($organization)->create();
    $sensor = Sensor::factory()->for($organization)->create();
    $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);
    $sensor->createToken('legacy-token');

    $this->actingAs($admin)
        ->delete(route('peoplecount.sensors.revoke-token', [
            'organization' => $organization,
            'sensor' => $sensor,
        ]))
        ->assertRedirect(route('peoplecount.sensors.edit', [
            'organization' => $organization,
            'sensor' => $sensor,
        ]));

    expect($sensor->tokens()->count())->toBe(0);
});

it('does not regenerate token for another organization sensor', function () {
    $org = Organization::factory()->create();
    $foreignOrg = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($org)->create();
    $foreignSensor = Sensor::factory()->for($foreignOrg)->create();

    $this->actingAs($admin)
        ->postJson(route('peoplecount.sensors.regenerate-token', [
            'organization' => $org->slug,
            'sensor' => $foreignSensor->id,
        ]))
        ->assertForbidden();
});

it('uses the correct form requests and organization middleware', function () {
    test()->assertRouteUsesMiddleware(
        'peoplecount.sensors.regenerate-token',
        ['permissions.organization_slug', 'auth', 'verified'],
    );
    test()->assertRouteUsesMiddleware(
        'peoplecount.sensors.revoke-token',
        ['permissions.organization_slug', 'auth', 'verified'],
    );
    test()->assertActionUsesFormRequest(
        SensorTokenController::class,
        'update',
        SensorTokenUpdateRequest::class,
    );
    test()->assertRouteUsesFormRequest(
        'peoplecount.sensors.regenerate-token',
        SensorTokenUpdateRequest::class,
    );
    test()->assertActionUsesFormRequest(
        SensorTokenController::class,
        'destroy',
        SensorTokenUpdateRequest::class,
    );
    test()->assertRouteUsesFormRequest(
        'peoplecount.sensors.revoke-token',
        SensorTokenUpdateRequest::class,
    );
});
