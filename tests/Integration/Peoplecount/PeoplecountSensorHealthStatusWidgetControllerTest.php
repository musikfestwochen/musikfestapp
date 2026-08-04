<?php

use App\Http\Controllers\Widgets\PeoplecountSensorHealthStatusWidgetController;
use App\Http\Requests\Widgets\Peoplecount\SensorHealthIndexRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use App\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('returns sensor health payload for an organization', function () {
    Date::setTestNow('2025-08-09 18:00:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    // Create sensors in org
    $healthySensor = Sensor::factory()->withOrganization($org)->create([
        'vendor' => 'Axis', 'model' => 'P8815-2', 'serial' => 'H-1',
    ]);
    $suspiciousSensor = Sensor::factory()->withOrganization($org)->create([
        'vendor' => 'Axis', 'model' => 'P8815-2', 'serial' => 'S-1',
    ]);
    $unhealthySensor = Sensor::factory()->withOrganization($org)->create([
        'vendor' => 'Axis', 'model' => 'P8815-2', 'serial' => 'U-1',
    ]);

    // Assignments active now
    Assignment::factory()->withSensor($healthySensor)->create([
        'active_from' => Date::now()->subHour(),
        'active_to' => Date::now()->addHour(),
    ]);
    Assignment::factory()->withSensor($suspiciousSensor)->create([
        'label' => 'Main Entrance',
        'active_from' => Date::now()->subHour(),
        'active_to' => Date::now()->addHour(),
    ]);
    Assignment::factory()->withSensor($unhealthySensor)->create([
        'active_from' => Date::now()->subHour(),
        'active_to' => Date::now()->addHour(),
    ]);

    // Interval counts: healthy = recent and some non-zero
    IntervalCount::factory()->create([
        'sensor_id' => $healthySensor->id,
        'ts_from' => Date::now()->subMinutes(1)->subSeconds(30),
        'ts_to' => Date::now()->subMinute(),
        'count_in' => 2,
        'count_out' => 0,
    ]);

    // suspicious = recent but last 10 all zeros
    foreach (range(1, 5) as $i) {
        IntervalCount::factory()->create([
            'sensor_id' => $suspiciousSensor->id,
            'ts_from' => Date::now()->subMinutes(1)->subSeconds(50 - $i),
            'ts_to' => Date::now()->subMinutes(1)->subSeconds(45 - $i),
            'count_in' => 0,
            'count_out' => 0,
        ]);
    }

    // unhealthy = not recent (older than 2 minutes)
    IntervalCount::factory()->create([
        'sensor_id' => $unhealthySensor->id,
        'ts_from' => Date::now()->subMinutes(5),
        'ts_to' => Date::now()->subMinutes(3),
        'count_in' => 10,
        'count_out' => 10,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.sensor-health.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'last_updated', 'total', 'all_healthy', 'healthy', 'suspicious', 'unhealthy',
        ])
        ->assertJsonPath('total', 3)
        ->assertJson(fn ($json) => $json
            ->whereType('last_updated', 'string')
            ->where('all_healthy', false)
            ->where('healthy.0.serial', 'H-1')
            ->where('suspicious.0.serial', 'S-1')
            ->where('suspicious.0.label', 'Main Entrance')
            ->where('unhealthy.0.serial', 'U-1')
            ->etc()
        );

    Date::setTestNow();
});

it('returns empty health payload when no active assignments exist', function () {
    Date::setTestNow('2025-08-09 18:00:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    // Sensor but no active assignment in window
    Sensor::factory()->withOrganization($org)->create();

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.sensor-health.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJson([
            'total' => 0,
            'healthy' => [],
            'suspicious' => [],
            'unhealthy' => [],
        ]);

    Date::setTestNow();
});

it('returns 403 when user does not have permission', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    $response = $this->actingAs($user)
        ->getJson(route('peoplecount.sensor-health.index', ['organization' => $org->slug]));

    $response->assertStatus(403);
});

it('uses the correct form request and middleware', function () {
    test()->assertRouteUsesMiddleware(
        'peoplecount.sensor-health.index',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    test()->assertActionUsesFormRequest(
        PeoplecountSensorHealthStatusWidgetController::class,
        'index',
        SensorHealthIndexRequest::class,
    );

    test()->assertRouteUsesFormRequest(
        'peoplecount.sensor-health.index',
        SensorHealthIndexRequest::class,
    );
});
