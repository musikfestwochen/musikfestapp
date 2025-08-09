<?php

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\SensorActivityService;
use Illuminate\Support\Carbon;

covers(SensorActivityService::class);

beforeEach(function () {
    $this->service = new SensorActivityService;
});

it('computes sums for multiple windows and respects assignment window and flipping', function () {
    Carbon::setTestNow('2025-08-09 18:00:00');

    $org = Organization::factory()->create();

    // Event and area in progress
    /** @var Event $event */
    $event = Event::factory()->withOrganization($org)->create([
        'starts_at' => Carbon::now()->subHours(3),
        'ends_at' => Carbon::now()->addHours(3),
    ]);
    /** @var Area $area */
    $area = Area::factory()->withEvent($event)->create();

    // Two sensors
    $sensorA = Sensor::factory()->withOrganization($org)->create(['serial' => 'A']);
    $sensorB = Sensor::factory()->withOrganization($org)->create(['serial' => 'B']);

    // Assign A active whole period, not flipped
    Assignment::factory()->withArea($area)->withSensor($sensorA)->create([
        'active_from' => Carbon::now()->subHours(2),
        'active_to' => Carbon::now()->addHours(2),
        'direction_flipped' => false,
    ]);
    // Assign B active only last 20m, flipped
    Assignment::factory()->withArea($area)->withSensor($sensorB)->create([
        'active_from' => Carbon::now()->subMinutes(20),
        'active_to' => Carbon::now()->addHours(1),
        'direction_flipped' => true,
    ]);

    // Counts for A across windows
    IntervalCount::factory()->create([
        'sensor_id' => $sensorA->id,
        'ts_from' => Carbon::now()->subMinutes(9),
        'ts_to' => Carbon::now()->subMinutes(8),
        'count_in' => 3,
        'count_out' => 1,
    ]); // contributes to 10m,30m,1h,2h

    IntervalCount::factory()->create([
        'sensor_id' => $sensorA->id,
        'ts_from' => Carbon::now()->subMinutes(25),
        'ts_to' => Carbon::now()->subMinutes(24),
        'count_in' => 2,
        'count_out' => 2,
    ]); // contributes to 30m,1h,2h

    // Counts for B before its assignment starts (should be ignored)
    IntervalCount::factory()->create([
        'sensor_id' => $sensorB->id,
        'ts_from' => Carbon::now()->subMinutes(30),
        'ts_to' => Carbon::now()->subMinutes(29),
        'count_in' => 10,
        'count_out' => 0,
    ]);

    // Counts for B within assignment (flipped => in/out swap)
    IntervalCount::factory()->create([
        'sensor_id' => $sensorB->id,
        'ts_from' => Carbon::now()->subMinutes(10),
        'ts_to' => Carbon::now()->subMinutes(9),
        'count_in' => 1,
        'count_out' => 4,
    ]); // flipped => in=4, out=1

    $payload = $this->service->getMostActiveSensorsPerArea($org);

    // Find area data
    expect($payload)->toHaveCount(1);
    $areaData = $payload[0];
    expect($areaData['sensors'])->toHaveCount(2);

    $sensorAData = collect($areaData['sensors'])->firstWhere('serial', 'A');
    $sensorBData = collect($areaData['sensors'])->firstWhere('serial', 'B');

    // Sensor A sums
    expect($sensorAData['sums']['10m'])
        ->toMatchArray(['in' => 3, 'out' => 1, 'total' => 4]);
    expect($sensorAData['sums']['30m'])
        ->toMatchArray(['in' => 5, 'out' => 3, 'total' => 8]);

    // Sensor B sums (only last 20m) and flipped
    expect($sensorBData['sums']['10m'])
        ->toMatchArray(['in' => 4, 'out' => 1, 'total' => 5]);
    expect($sensorBData['sums']['30m'])
        ->toMatchArray(['in' => 4, 'out' => 1, 'total' => 5]);

    Carbon::setTestNow();
});

it('returns empty array when no active events/areas', function () {
    $org = Organization::factory()->create();
    $payload = $this->service->getMostActiveSensorsPerArea($org);
    expect($payload)->toBeArray()->and($payload)->toHaveCount(0);
});
