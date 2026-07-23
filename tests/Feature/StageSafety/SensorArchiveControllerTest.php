<?php

use App\Http\Controllers\StageSafety\SensorArchiveController;
use App\Http\Requests\StageSafety\SensorArchiveUpdateRequest;
use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use App\Models\User;
use App\Services\StageSafety\SensorService;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('archives a sensor and revokes its tokens', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($organization)->create();
    $sensor = Sensor::factory()->for($organization)->create();
    $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);

    $this->actingAs($admin)
        ->post(route('stage-safety.sensors.archive.store', [
            'organization' => $organization,
            'stageSafetySensor' => $sensor,
        ]))
        ->assertRedirect(route('stage-safety.sensors.index', ['organization' => $organization]));

    expect($sensor->refresh()->archived_at)->not->toBeNull()
        ->and($sensor->tokens()->count())->toBe(0);
});

it('restores a sensor without issuing a token', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($organization)->create();
    $sensor = Sensor::factory()->for($organization)->create(['archived_at' => now()]);

    $this->actingAs($admin)
        ->delete(route('stage-safety.sensors.archive.destroy', [
            'organization' => $organization,
            'stageSafetySensor' => $sensor,
        ]))
        ->assertRedirect(route('stage-safety.sensors.index', [
            'organization' => $organization,
            'archived' => true,
        ]));

    expect($sensor->refresh()->archived_at)->toBeNull()
        ->and($sensor->tokens()->count())->toBe(0);
});

it('uses the archive request and organization middleware', function () {
    test()->assertRouteUsesMiddleware(
        'stage-safety.sensors.archive.store',
        ['permissions.organization_slug', 'auth', 'verified'],
    );
    test()->assertActionUsesFormRequest(
        SensorArchiveController::class,
        'store',
        SensorArchiveUpdateRequest::class,
    );
    test()->assertRouteUsesFormRequest(
        'stage-safety.sensors.archive.store',
        SensorArchiveUpdateRequest::class,
    );
    test()->assertActionUsesFormRequest(
        SensorArchiveController::class,
        'destroy',
        SensorArchiveUpdateRequest::class,
    );
    test()->assertRouteUsesFormRequest(
        'stage-safety.sensors.archive.destroy',
        SensorArchiveUpdateRequest::class,
    );
});
