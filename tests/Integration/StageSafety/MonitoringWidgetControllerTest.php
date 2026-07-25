<?php

use App\Http\Controllers\StageSafety\SensorMonitoringController;
use App\Http\Controllers\Widgets\StageSafetyCurrentWindWidgetController;
use App\Http\Controllers\Widgets\StageSafetySensorHealthWidgetController;
use App\Http\Controllers\Widgets\StageSafetyWindHistoryWidgetController;
use App\Http\Requests\StageSafety\SensorMonitoringIndexRequest;
use App\Http\Requests\Widgets\StageSafety\CurrentWindIndexRequest;
use App\Http\Requests\Widgets\StageSafety\SensorHealthIndexRequest;
use App\Http\Requests\Widgets\StageSafety\WindHistoryIndexRequest;
use App\Models\Organization;
use App\Models\StageSafety\Reading;
use App\Models\StageSafety\Sensor;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    Carbon::setTestNow('2026-07-25 12:00:00 UTC');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('allows a Stage Safety viewer to access all monitoring endpoints', function () {
    $organization = Organization::factory()->create();
    $viewer = User::factory()->create();
    $viewer->organizations()->attach($organization);
    setPermissionsOrgId($organization->id);
    $viewer->assignRole('StageSafetyViewer');

    expect($viewer->can('stage-safety.sensors.edit'))->toBeFalse()
        ->and($viewer->can('stage-safety.sensors.update'))->toBeFalse()
        ->and($viewer->can('stage-safety.sensors.destroy'))->toBeFalse();

    $sensor = Sensor::factory()->for($organization)->create();
    Reading::factory()->for($sensor)->create([
        'observed_at' => now()->subMinute(),
        'received_at' => now()->subMinute(),
    ]);

    $this->actingAs($viewer)
        ->getJson(route('stage-safety.current-wind.index', ['organization' => $organization]))
        ->assertSuccessful()
        ->assertJsonPath('sensors.0.sensor.id', $sensor->id);

    $this->actingAs($viewer)
        ->getJson(route('stage-safety.sensor-health.index', ['organization' => $organization]))
        ->assertSuccessful()
        ->assertJsonPath('fresh.0.id', $sensor->id);

    $this->actingAs($viewer)
        ->getJson(route('stage-safety.wind-history.index', ['organization' => $organization]))
        ->assertSuccessful()
        ->assertJsonPath('from', '2026-07-25T11:00:00+00:00')
        ->assertJsonPath('to', '2026-07-25T12:00:00+00:00')
        ->assertJsonPath('sensors.0.sensor.id', $sensor->id);

    $this->actingAs($viewer)
        ->getJson(route('stage-safety.sensors.monitoring.index', [
            'organization' => $organization,
            'stageSafetySensor' => $sensor,
        ]))
        ->assertSuccessful()
        ->assertJsonPath('current.sensor.id', $sensor->id)
        ->assertJsonPath('history.sensors.0.sensor.id', $sensor->id);
});

it('forbids users without monitoring permission', function (string $routeName) {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route($routeName, ['organization' => $organization]))
        ->assertForbidden();
})->with([
    'current wind' => 'stage-safety.current-wind.index',
    'sensor health' => 'stage-safety.sensor-health.index',
    'wind history' => 'stage-safety.wind-history.index',
]);

it('forbids an organization viewer from accessing another organization', function (string $routeName) {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $viewer = User::factory()->create();
    $viewer->organizations()->attach($organization);
    setPermissionsOrgId($organization->id);
    $viewer->assignRole('StageSafetyViewer');

    $this->actingAs($viewer)
        ->getJson(route($routeName, ['organization' => $otherOrganization]))
        ->assertForbidden();
})->with([
    'current wind' => 'stage-safety.current-wind.index',
    'sensor health' => 'stage-safety.sensor-health.index',
    'wind history' => 'stage-safety.wind-history.index',
]);

it('does not bind another organization sensor to monitoring endpoint', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->globalAdmin()->create();
    $foreignSensor = Sensor::factory()->create();

    $this->actingAs($admin)
        ->getJson(route('stage-safety.sensors.monitoring.index', [
            'organization' => $organization,
            'stageSafetySensor' => $foreignSensor,
        ]))
        ->assertNotFound();
});

it('rejects invalid history ranges', function (array $query, array $invalidFields) {
    $organization = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($organization)->create();

    $this->actingAs($admin)
        ->getJson(route('stage-safety.wind-history.index', [
            'organization' => $organization,
            ...$query,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($invalidFields);
})->with([
    'longer than 24 hours' => [[
        'from' => '2026-07-24T11:59:59Z',
        'to' => '2026-07-25T12:00:00Z',
    ], ['to']],
    'reversed' => [[
        'from' => '2026-07-25T12:00:00Z',
        'to' => '2026-07-25T11:00:00Z',
    ], ['from', 'to']],
]);

it('uses monitoring form requests and organization middleware', function () {
    foreach ([
        'stage-safety.current-wind.index' => [StageSafetyCurrentWindWidgetController::class, CurrentWindIndexRequest::class],
        'stage-safety.sensor-health.index' => [StageSafetySensorHealthWidgetController::class, SensorHealthIndexRequest::class],
        'stage-safety.wind-history.index' => [StageSafetyWindHistoryWidgetController::class, WindHistoryIndexRequest::class],
        'stage-safety.sensors.monitoring.index' => [SensorMonitoringController::class, SensorMonitoringIndexRequest::class],
    ] as $routeName => [$controller, $request]) {
        test()->assertRouteUsesMiddleware(
            $routeName,
            ['permissions.organization_slug', 'auth', 'verified'],
        );

        test()->assertActionUsesFormRequest($controller, 'index', $request);
        test()->assertRouteUsesFormRequest($routeName, $request);
    }
});
