<?php

use App\Http\Controllers\Widgets\PeoplecountMostActiveSensorsWidgetController;
use App\Http\Requests\Widgets\Peoplecount\MostActiveSensorsIndexRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('returns most active sensors payload for an organization', function () {
    Carbon::setTestNow('2025-08-09 18:00:00');
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    // Event in progress
    /** @var Event $event */
    $event = Event::factory()->withOrganization($org)->create([
        'starts_at' => Carbon::now()->subHour(),
        'ends_at' => Carbon::now()->addHour(),
    ]);

    // Area under the event
    /** @var Area $area */
    $area = Area::factory()->withEvent($event)->create(['name' => 'Main Gate']);

    // Sensor and assignment active now
    $sensor = Sensor::factory()->withOrganization($org)->create(['serial' => 'S-1', 'vendor' => 'Axis', 'model' => 'P8815-2']);
    Assignment::factory()->withArea($area)->withSensor($sensor)->create([
        'active_from' => Carbon::now()->subHour(),
        'active_to' => Carbon::now()->addHour(),
        'direction_flipped' => false,
    ]);

    // Interval counts in the last 10 minutes
    IntervalCount::factory()->create([
        'sensor_id' => $sensor->id,
        'ts_from' => Carbon::now()->subMinutes(5),
        'ts_to' => Carbon::now()->subMinutes(5)->addMinute(),
        'count_in' => 2,
        'count_out' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.most-active-sensors.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonStructure([
            [
                'id', 'name', 'event_name', 'sensors', 'last_updated',
            ],
        ])
        ->assertJson(fn ($json) => $json
            ->whereType('0.last_updated', 'string')
            ->where('0.sensors.0.serial', 'S-1')
            ->where('0.sensors.0.sums.10m.total', 3)
            ->etc()
        );

    Carbon::setTestNow();
});

it('respects direction flipping when summing', function () {
    Carbon::setTestNow('2025-08-09 18:00:00');
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $event = Event::factory()->withOrganization($org)->create([
        'starts_at' => Carbon::now()->subHour(),
        'ends_at' => Carbon::now()->addHour(),
    ]);
    $area = Area::factory()->withEvent($event)->create();

    $sensor = Sensor::factory()->withOrganization($org)->create(['serial' => 'S-2']);
    Assignment::factory()->withArea($area)->withSensor($sensor)->create([
        'active_from' => Carbon::now()->subHour(),
        'active_to' => Carbon::now()->addHour(),
        'direction_flipped' => true,
    ]);

    IntervalCount::factory()->create([
        'sensor_id' => $sensor->id,
        'ts_from' => Carbon::now()->subMinutes(8),
        'ts_to' => Carbon::now()->subMinutes(7),
        'count_in' => 5,
        'count_out' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.most-active-sensors.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonPath('0.sensors.0.sums.10m.in', 1)
        ->assertJsonPath('0.sensors.0.sums.10m.out', 5)
        ->assertJsonPath('0.sensors.0.sums.10m.total', 6);

    Carbon::setTestNow();
});

it('returns 403 when user does not have permission', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    $response = $this->actingAs($user)
        ->getJson(route('peoplecount.most-active-sensors.index', ['organization' => $org->slug]));

    $response->assertStatus(403);
});

it('uses the correct form request and middleware', function () {
    test()->assertRouteUsesMiddleware(
        'peoplecount.most-active-sensors.index',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    test()->assertActionUsesFormRequest(
        PeoplecountMostActiveSensorsWidgetController::class,
        'index',
        MostActiveSensorsIndexRequest::class,
    );

    test()->assertRouteUsesFormRequest(
        'peoplecount.most-active-sensors.index',
        MostActiveSensorsIndexRequest::class,
    );
});
