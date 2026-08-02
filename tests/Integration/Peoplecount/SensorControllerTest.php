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
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\Sensor;
use App\Models\Peoplecount\SensorShare;
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

it('can list archived sensors using string query values', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    Sensor::factory()->for($org)->create();
    $archivedSensor = Sensor::factory()->for($org)->create(['archived_at' => now()]);

    $this->actingAs($admin)
        ->get(route('peoplecount.sensors.index', ['organization' => $org->slug, 'archived' => '1']))
        ->assertInertia(fn ($page) => $page
            ->component('peoplecount/Sensors')
            ->has('sensors', 1)
            ->where('sensors.0.id', $archivedSensor->id)
            ->where('showArchived', true)
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

it('does not show edit form for another organization sensor', function () {
    $org = Organization::factory()->create();
    $foreignOrg = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($org)->create();
    $foreignSensor = Sensor::factory()->for($foreignOrg)->create();

    $this->actingAs($admin)
        ->get(route('peoplecount.sensors.edit', ['organization' => $org->slug, 'sensor' => $foreignSensor->id]))
        ->assertNotFound();
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

it('rejects unvalidated sensor update fields', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $foreignOrg = Organization::factory()->create();
    $sensor = Sensor::factory()->for($org)->create([
        'api_token' => 'original-token',
        'archived_at' => null,
    ]);

    $this->actingAs($admin)
        ->put(route('peoplecount.sensors.update', ['organization' => $org->slug, 'sensor' => $sensor->id]), [
            'vendor' => 'UpdatedVendor',
            'model' => 'UpdatedModel',
            'serial' => 'UpdatedSerial',
            'organization_id' => $foreignOrg->id,
            'api_token' => 'changed-token',
            'archived_at' => now()->toDateTimeString(),
        ])
        ->assertSessionHasErrors(['organization_id', 'api_token', 'archived_at']);

    $sensor->refresh();

    expect($sensor->organization_id)->toBe($org->id)
        ->and($sensor->api_token)->toBe('original-token')
        ->and($sensor->archived_at)->toBeNull();
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

it('can delete a sensor with unused shares', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $borrower = Organization::factory()->create();
    $sensor = Sensor::factory()->for($org)->create();
    SensorShare::factory()->withSensor($sensor)->withBorrowerOrganization($borrower)->create();

    $this->actingAs($admin)
        ->delete(route('peoplecount.sensors.destroy', ['organization' => $org->slug, 'sensor' => $sensor->id]))
        ->assertRedirect(route('peoplecount.sensors.index', ['organization' => $org->slug]));

    $this->assertSoftDeleted('peoplecount_sensors', ['id' => $sensor->id]);
});

it('returns validation errors when deleting a sensor used by a shared assignment', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $borrower = Organization::factory()->create();
    $sensor = Sensor::factory()->for($org)->create();
    $share = SensorShare::factory()->withSensor($sensor)->withBorrowerOrganization($borrower)->create();
    $event = Event::factory()->for($borrower, 'organization')->create();
    $area = Area::factory()->for($event)->create();
    Assignment::factory()
        ->for($event)
        ->for($area)
        ->for($sensor)
        ->create(['sensor_share_id' => $share->id]);

    $this->actingAs($admin)
        ->from(route('peoplecount.sensors.edit', ['organization' => $org->slug, 'sensor' => $sensor->id]))
        ->delete(route('peoplecount.sensors.destroy', ['organization' => $org->slug, 'sensor' => $sensor->id]))
        ->assertRedirect(route('peoplecount.sensors.edit', ['organization' => $org->slug, 'sensor' => $sensor->id]))
        ->assertSessionHasErrors('sensor_id');
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
