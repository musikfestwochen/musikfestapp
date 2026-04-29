<?php

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use MathPHP\Statistics\Descriptive;

it('tests consistency of setupPeoplecountBasic methos', function () {

    // call setupPeoplecountBasic
    $setup = setupPeoplecountBasic();

    // check aggregation interval in config
    expect(config('peoplecount.aggregation.granularity_minutes'))->toBe(10);

    // check organization
    $organization = Organization::query()->first();
    expect($organization)->not->toBeNull()
        ->and($organization->name)->toBe('Test Organization for Peoplecount')
        ->and($organization->id)->toBe($setup['organization']->id);

    // check event
    $event = Event::query()->where('name', 'Test Event 24h')->first();
    expect($event)->not->toBeNull()
        ->and($event->starts_at->toDateTimeString())->toBe('2025-08-02 10:00:00')
        ->and($event->ends_at->toDateTimeString())->toBe('2025-08-03 10:00:00')
        ->and($event->id)->toBe($setup['event']->id);

    // check area
    $area = Area::query()->where('name', 'Test Area')->first();
    expect($area)->not->toBeNull()
        ->and($area->event_id)->toBe($event->id)
        ->and($area->id)->toBe($setup['area']->id);

    // check sensors
    $sensors = Sensor::query()->where('organization_id', $organization->id)->get();
    expect($sensors)->toHaveCount(2);

    $sensor1 = $sensors->where('serial', 'TEST001')->first();
    expect($sensor1)->not->toBeNull()
        ->and($sensor1->vendor)->toBe('TestVendor')
        ->and($sensor1->model)->toBe('TestModel1')
        ->and($sensor1->api_token)->toBe('test_token_1')
        ->and($sensor1->id)->toBe($setup['sensors'][0]->id);

    $sensor2 = $sensors->where('serial', 'TEST002')->first();
    expect($sensor2)->not->toBeNull()
        ->and($sensor2->vendor)->toBe('TestVendor')
        ->and($sensor2->model)->toBe('TestModel2')
        ->and($sensor2->api_token)->toBe('test_token_2')
        ->and($sensor2->id)->toBe($setup['sensors'][1]->id);

    // check assignments
    $assignments = Assignment::query()->where('event_id', $event->id)->get();
    expect($assignments)->toHaveCount(2);

    $assignment1 = $assignments->where('sensor_id', $sensor1->id)->first();
    expect($assignment1)->not->toBeNull()
        ->and($assignment1->area_id)->toBe($area->id)
        ->and($assignment1->direction_flipped)->toBeFalse()
        ->and($assignment1->active_from->toDateTimeString())->toBe('2025-08-02 10:00:00')
        ->and($assignment1->active_to->toDateTimeString())->toBe('2025-08-03 10:00:00')
        ->and($assignment1->id)->toBe($setup['assignments'][0]->id);

    $assignment2 = $assignments->where('sensor_id', $sensor2->id)->first();
    expect($assignment2)->not->toBeNull()
        ->and($assignment2->area_id)->toBe($area->id)
        ->and($assignment2->direction_flipped)->toBeFalse()
        ->and($assignment2->active_from->toDateTimeString())->toBe('2025-08-02 10:00:00')
        ->and($assignment2->active_to->toDateTimeString())->toBe('2025-08-03 10:00:00')
        ->and($assignment2->id)->toBe($setup['assignments'][1]->id);

    // check interval counts - each sensor should have exactly 288 intervals (24h, 5 minutes each)
    $sensor1IntervalCount = IntervalCount::query()->where('sensor_id', $sensor1->id)->count();
    expect($sensor1IntervalCount)->toBe(288);

    $sensor2IntervalCount = IntervalCount::query()->where('sensor_id', $sensor2->id)->count();
    expect($sensor2IntervalCount)->toBe(288);

    // get each sensor's in and out counts
    $sensor1Counts = IntervalCount::query()->where('sensor_id', $sensor1->id)
        ->orderBy('ts_from')
        ->get(['count_in', 'count_out'])
        ->toArray();

    $sensor2Counts = IntervalCount::query()->where('sensor_id', $sensor2->id)
        ->orderBy('ts_from')
        ->get(['count_in', 'count_out'])
        ->toArray();

    // check counts
    expect(array_sum(array_column($sensor1Counts, 'count_in')))->toBe(2744)
        ->and(array_sum(array_column($sensor1Counts, 'count_out')))->toBe(2772)
        ->and(array_sum(array_column($sensor2Counts, 'count_in')))->toBe(2811)
        ->and(array_sum(array_column($sensor2Counts, 'count_out')))->toBe(2862)
        ->and(Descriptive::sd(array_column($sensor1Counts, 'count_in')))->toBeBetween(8.798, 8.799)
        ->and(Descriptive::sd(array_column($sensor1Counts, 'count_out')))->toBeBetween(9.203, 9.204)
        ->and(Descriptive::sd(array_column($sensor2Counts, 'count_in')))->toBeBetween(9.406, 9.407)
        ->and(Descriptive::sd(array_column($sensor2Counts, 'count_out')))->toBeBetween(9.525, 9.526)
        ->and(Descriptive::fiveNumberSummary(array_column($sensor1Counts, 'count_in')))->toBe([
            'min' => 2,
            'Q1' => 4.0,
            'median' => 6.0,
            'Q3' => 10.0,
            'max' => 33,
        ])
        ->and(Descriptive::fiveNumberSummary(array_column($sensor1Counts, 'count_out')))->toBe([
            'min' => 0,
            'Q1' => 3.0,
            'median' => 7.0,
            'Q3' => 12.0,
            'max' => 37,
        ])
        ->and(Descriptive::fiveNumberSummary(array_column($sensor2Counts, 'count_in')))->toBe([
            'min' => 1,
            'Q1' => 2.0,
            'median' => 9.0,
            'Q3' => 12.0,
            'max' => 37,
        ])
        ->and(Descriptive::fiveNumberSummary(array_column($sensor2Counts, 'count_out')))->toBe([
            'min' => 0,
            'Q1' => 3.0,
            'median' => 8.0,
            'Q3' => 13.0,
            'max' => 41,
        ]);

});
