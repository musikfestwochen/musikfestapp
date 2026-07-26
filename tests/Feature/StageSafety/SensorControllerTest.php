<?php

use App\Enums\StageSafety\SensorType;
use App\Http\Controllers\StageSafety\SensorController;
use App\Http\Requests\StageSafety\SensorCreateRequest;
use App\Http\Requests\StageSafety\SensorDestroyRequest;
use App\Http\Requests\StageSafety\SensorEditRequest;
use App\Http\Requests\StageSafety\SensorIndexRequest;
use App\Http\Requests\StageSafety\SensorStoreRequest;
use App\Http\Requests\StageSafety\SensorUpdateRequest;
use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

function stageSafetySensorPayload(array $overrides = []): array
{
    return array_merge([
        'manufacturer' => SensorType::BroadweighBwWss->manufacturer(),
        'model' => SensorType::BroadweighBwWss->model(),
        'identifier' => 'FF1234',
        'name' => 'Main Stage Wind',
        'location' => 'Main Stage Roof',
        'stale_after_seconds' => 300,
    ], $overrides);
}

it('lists active and archived sensors for an organization', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($organization)->create();
    $activeSensor = Sensor::factory()->for($organization)->create(['name' => 'Active']);
    $archivedSensor = Sensor::factory()->for($organization)->create([
        'name' => 'Archived',
        'archived_at' => now(),
    ]);
    Sensor::factory()->create();

    $this->actingAs($admin)
        ->get(route('stage-safety.sensors.index', ['organization' => $organization]))
        ->assertInertia(fn ($page) => $page
            ->component('stage-safety/Sensors', false)
            ->where('organization.id', $organization->id)
            ->where('showArchived', false)
            ->has('sensors', 1)
            ->where('sensors.0.id', $activeSensor->id));

    $this->actingAs($admin)
        ->get(route('stage-safety.sensors.index', ['organization' => $organization, 'archived' => '1']))
        ->assertInertia(fn ($page) => $page
            ->component('stage-safety/Sensors', false)
            ->where('showArchived', true)
            ->has('sensors', 1)
            ->where('sensors.0.id', $archivedSensor->id));
});

it('shows create and edit pages with supported sensor types', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($organization)->create();
    $sensor = Sensor::factory()->for($organization)->create();

    $this->actingAs($admin)
        ->get(route('stage-safety.sensors.create', ['organization' => $organization]))
        ->assertInertia(fn ($page) => $page
            ->component('stage-safety/NewSensor', false)
            ->where('organization.id', $organization->id)
            ->where('sensorTypes.0.manufacturer', 'broadweigh')
            ->where('sensorTypes.0.model', 'BW-WSS'));

    $this->actingAs($admin)
        ->get(route('stage-safety.sensors.edit', [
            'organization' => $organization,
            'stageSafetySensor' => $sensor,
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('stage-safety/EditSensor', false)
            ->where('sensor.id', $sensor->id)
            ->where('sensor.has_active_token', false)
            ->where('sensorTypes.0.model', 'BW-WSS'));
});

it('creates a sensor for the route organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($organization)->create();

    $response = $this->actingAs($admin)->postJson(
        route('stage-safety.sensors.store', ['organization' => $organization]),
        stageSafetySensorPayload(['organization_id' => $otherOrganization->id]),
    );

    $response->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('sensor.organization_id', $organization->id)
        ->assertJsonPath('sensor.identifier', 'FF1234')
        ->assertJsonStructure(['sensor' => ['id'], 'token']);

    expect($response->json('token'))->not->toContain('|');
    $this->assertDatabaseHas('stage_safety_sensors', [
        'organization_id' => $organization->id,
        'identifier' => 'FF1234',
    ]);
});

it('updates and deletes an organization sensor', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($organization)->create();
    $sensor = Sensor::factory()->for($organization)->create();

    $this->actingAs($admin)
        ->put(route('stage-safety.sensors.update', [
            'organization' => $organization,
            'stageSafetySensor' => $sensor,
        ]), stageSafetySensorPayload([
            'identifier' => $sensor->identifier,
            'name' => 'Updated Sensor',
        ]))
        ->assertRedirect(route('stage-safety.sensors.index', ['organization' => $organization]));

    expect($sensor->refresh()->name)->toBe('Updated Sensor');

    $this->actingAs($admin)
        ->delete(route('stage-safety.sensors.destroy', [
            'organization' => $organization,
            'stageSafetySensor' => $sensor,
        ]))
        ->assertRedirect(route('stage-safety.sensors.index', ['organization' => $organization]));

    $this->assertSoftDeleted('stage_safety_sensors', ['id' => $sensor->id]);
});

it('does not bind another organization sensor', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($organization)->create();
    $foreignSensor = Sensor::factory()->create();

    $this->actingAs($admin)
        ->get(route('stage-safety.sensors.edit', [
            'organization' => $organization,
            'stageSafetySensor' => $foreignSensor,
        ]))
        ->assertNotFound();
});

it('uses Stage Safety form requests and organization middleware', function () {
    test()->assertRouteUsesMiddleware(
        'stage-safety.sensors.index',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    foreach ([
        'index' => SensorIndexRequest::class,
        'create' => SensorCreateRequest::class,
        'store' => SensorStoreRequest::class,
        'edit' => SensorEditRequest::class,
        'update' => SensorUpdateRequest::class,
        'destroy' => SensorDestroyRequest::class,
    ] as $action => $requestClass) {
        test()->assertActionUsesFormRequest(SensorController::class, $action, $requestClass);
        test()->assertRouteUsesFormRequest('stage-safety.sensors.'.$action, $requestClass);
    }
});
