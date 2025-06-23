<?php

use App\Http\Controllers\Peoplecount\SensorController;
use App\Http\Requests\Peoplecount\SensorCreateRequest;
use App\Http\Requests\Peoplecount\SensorDestroyRequest;
use App\Http\Requests\Peoplecount\SensorEditRequest;
use App\Http\Requests\Peoplecount\SensorIndexRequest;
use App\Http\Requests\Peoplecount\SensorShowRequest;
use App\Http\Requests\Peoplecount\SensorStoreRequest;
use App\Http\Requests\Peoplecount\SensorUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('can list sensors for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $sensors = Sensor::factory()->count(3)->for($org)->create();

    $this->actingAs($admin)
        ->get(route('peoplecount.sensors.index', ['organization' => $org->slug]))
        ->assertInertia(fn ($page) => $page
            ->component('peoplecount/Sensors')
            ->where('organization.id', $org->id)
            ->has('sensors', 3)
            ->where('sensors.0.id', $sensors[0]->id)
            ->where('sensors.1.id', $sensors[1]->id)
            ->where('sensors.2.id', $sensors[2]->id)
        );
});

it('shows the create sensor form for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $this->actingAs($admin)
        ->get(route('peoplecount.sensors.create', ['organization' => $org->slug]))
        ->assertInertia(fn ($page) => $page
            ->component('peoplecount/NewSensor')
            ->where('organization.id', $org->id)
        );
});

it('can create a sensor for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $sensorData = Sensor::factory()->make()->toArray();
    unset($sensorData['organization_id']); // Will be set by controller

    $response = $this->actingAs($admin)
        ->post(route('peoplecount.sensors.store', ['organization' => $org->slug]), $sensorData);
    $response->assertRedirect(route('peoplecount.sensors.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('peoplecount_sensors', [
        'vendor' => $sensorData['vendor'],
        'model' => $sensorData['model'],
        'serial' => $sensorData['serial'],
        'organization_id' => $org->id,
        'deleted_at' => null,
    ]);
});

it('shows the edit sensor form for an organization sensor', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $sensor = Sensor::factory()->for($org)->create();

    $this->actingAs($admin)
        ->get(route('peoplecount.sensors.edit', ['organization' => $org->slug, 'sensor' => $sensor->id]))
        ->assertInertia(fn ($page) => $page
            ->component('peoplecount/EditSensor')
            ->where('organization.id', $org->id)
            ->where('sensor.id', $sensor->id)
        );
});

it('can update a sensor for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $sensor = Sensor::factory()->for($org)->create();
    $newModel = 'UpdatedModel';

    $response = $this->actingAs($admin)
        ->put(route('peoplecount.sensors.update', ['organization' => $org->slug, 'sensor' => $sensor->id]), [
            'vendor' => $sensor->vendor,
            'model' => $newModel,
            'serial' => $sensor->serial,
        ]);
    $response->assertRedirect(route('peoplecount.sensors.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('peoplecount_sensors', [
        'id' => $sensor->id,
        'model' => $newModel,
        'deleted_at' => null,
    ]);
});

it('can delete a sensor for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $sensor = Sensor::factory()->for($org)->create(['serial' => 'delete-me-serial']);

    $response = $this->actingAs($admin)
        ->delete(route('peoplecount.sensors.destroy', ['organization' => $org->slug, 'sensor' => $sensor->id]));
    $response->assertRedirect(route('peoplecount.sensors.index', ['organization' => $org->slug]));
    $this->assertSoftDeleted('peoplecount_sensors', [
        'id' => $sensor->id,
        'serial' => 'delete-me-serial',
    ]);
});

it('redirects show to edit for an organization sensor', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $sensor = Sensor::factory()->for($org)->create();

    $response = $this->actingAs($admin)
        ->get(route('peoplecount.sensors.show', ['organization' => $org->slug, 'sensor' => $sensor->id]));
    $response->assertRedirect(route('peoplecount.sensors.edit', ['organization' => $org->slug, 'sensor' => $sensor->id]));
});

it('uses the correct form requests', function () {
    // middleware
    test()->assertRouteUsesMiddleware(
        'peoplecount.sensors.index',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    // create
    test()->assertActionUsesFormRequest(
        SensorController::class,
        'create',
        SensorCreateRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.sensors.create',
        SensorCreateRequest::class);

    // destroy
    test()->assertActionUsesFormRequest(
        SensorController::class,
        'destroy',
        SensorDestroyRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.sensors.destroy',
        SensorDestroyRequest::class);

    // edit
    test()->assertActionUsesFormRequest(
        SensorController::class,
        'edit',
        SensorEditRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.sensors.edit',
        SensorEditRequest::class);

    // index
    test()->assertActionUsesFormRequest(
        SensorController::class,
        'index',
        SensorIndexRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.sensors.index',
        SensorIndexRequest::class);

    // show
    test()->assertActionUsesFormRequest(
        SensorController::class,
        'show',
        SensorShowRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.sensors.show',
        SensorShowRequest::class);

    // store
    test()->assertActionUsesFormRequest(
        SensorController::class,
        'store',
        SensorStoreRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.sensors.store',
        SensorStoreRequest::class);

    // update
    test()->assertActionUsesFormRequest(
        SensorController::class,
        'update',
        SensorUpdateRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.sensors.update',
        SensorUpdateRequest::class);
});
