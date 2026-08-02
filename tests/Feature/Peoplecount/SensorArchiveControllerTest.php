<?php

use App\Http\Controllers\Peoplecount\SensorArchiveController;
use App\Http\Requests\Peoplecount\SensorArchiveUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Models\User;
use App\Services\Peoplecount\SensorService;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('archives a sensor and revokes its tokens', function () {
    $admin = User::factory()->globalAdmin()->create();
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->for($organization)->create();
    $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);
    $sensor->createToken('legacy-token');

    $this->actingAs($admin)
        ->post(route('peoplecount.sensors.archive.store', [
            'organization' => $organization->slug,
            'sensor' => $sensor->id,
        ]))
        ->assertRedirect(route('peoplecount.sensors.index', ['organization' => $organization->slug]));

    expect($sensor->refresh()->archived_at)->not->toBeNull()
        ->and($sensor->tokens()->count())->toBe(0);
});

it('restores an archived sensor without issuing a token', function () {
    $admin = User::factory()->globalAdmin()->create();
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->for($organization)->create(['archived_at' => now()]);

    $this->actingAs($admin)
        ->delete(route('peoplecount.sensors.archive.destroy', [
            'organization' => $organization->slug,
            'sensor' => $sensor->id,
        ]))
        ->assertRedirect(route('peoplecount.sensors.index', [
            'organization' => $organization->slug,
            'archived' => true,
        ]));

    expect($sensor->refresh()->archived_at)->toBeNull()
        ->and($sensor->tokens()->count())->toBe(0);
});

it('uses the archive request and organization middleware', function () {
    test()->assertRouteUsesMiddleware(
        'peoplecount.sensors.archive.store',
        ['permissions.organization_slug', 'auth', 'verified'],
    );
    test()->assertActionUsesFormRequest(
        SensorArchiveController::class,
        'store',
        SensorArchiveUpdateRequest::class,
    );
    test()->assertRouteUsesFormRequest(
        'peoplecount.sensors.archive.store',
        SensorArchiveUpdateRequest::class,
    );
    test()->assertActionUsesFormRequest(
        SensorArchiveController::class,
        'destroy',
        SensorArchiveUpdateRequest::class,
    );
    test()->assertRouteUsesFormRequest(
        'peoplecount.sensors.archive.destroy',
        SensorArchiveUpdateRequest::class,
    );
});
