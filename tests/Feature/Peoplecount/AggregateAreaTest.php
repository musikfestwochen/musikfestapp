<?php

use App\Jobs\AggregateAreaCounts;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Models\Peoplecount\AreaSingleReset;
use Illuminate\Support\Carbon;

it('correctly calculates the total event numbers', function () {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('to')->first()->count)->toBe(-79);
});

it('correctly calculates with flipped direction', function ($sensor_index, $expectedCount) {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // Flip the direction of the first sensor
    $assignment = \App\Models\Peoplecount\Assignment::query()->where('sensor_id', $setup['sensors'][$sensor_index]->id)->first();
    $assignment->direction_flipped = true;
    $assignment->save();

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('to')->first()->count)->toBe($expectedCount);
})->with([
    [0, -23], // Flipping first sensor
    [1, 23], // Flipping second sensor
]);

it('correctly calculates the total event numbers with two flipped directions', function () {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // Flip the direction of both sensors
    $assignment1 = \App\Models\Peoplecount\Assignment::query()->where('sensor_id', $setup['sensors'][0]->id)->first();
    $assignment1->direction_flipped = true;
    $assignment1->save();

    $assignment2 = \App\Models\Peoplecount\Assignment::query()->where('sensor_id', $setup['sensors'][1]->id)->first();
    $assignment2->direction_flipped = true;
    $assignment2->save();

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('to')->first()->count)->toBe(79);
});

it('correctly calculates the total event numbers with different aggregation granularity', function ($granularity_minutes) {
    // arrange
    $setup = setupPeoplecountBasic(); // mus be called before setting config
    config(['peoplecount.aggregation.granularity_minutes' => $granularity_minutes]);
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('to')->first()->count)->toBe(-79);
})->with([1, 5, 10, 15, 30, 60, 180, 24 * 60 + 10]);

it('correctly aggregates with single reset at start', function ($offset, $expectedCount) {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // create a single reset at event start
    AreaSingleReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => $offset,
        'effective_at' => $setup['event_start'],
        'created_by' => 1, // assuming user ID 1 exists
        'notes' => 'Test reset at event start',
    ]);

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('to')->first()->count)->toBe($expectedCount);

})->with([
    [0, -79], // No reset, should be -79
    [10, -69], // Reset at start, should be -69
    [20, -59], // Reset at start + 20 minutes, should be -59
    [60, -19], // Reset at start + 60 minutes, should be -19
    [79, 0], // Reset at start + 79 minutes, should be 0
    [-50, -129], // Reset at start - 50 minutes, should be -129
    [-79, -158], // Reset at start - 79 minutes, should be -158
]);

it('correctly aggregates with single reset after some time', function ($offset, $expectedCount) {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // create a single reset after 3h
    AreaSingleReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => $offset,
        'effective_at' => $setup['event_start']->copy()->addHours(3),
        'created_by' => 1, // assuming user ID 1 exists
        'notes' => 'Test reset after 3 hours',
    ]);

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('to')->first()->count)->toBe($expectedCount);

})->with([
    [0, -90],
    [10, -80],
    [20, -70],
    [60, -30],
    [-50, -140],
    [-79, -169],
]);

it('correctly ignores previous single resets', function ($offset, $expectedCount) {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // create a single reset at event start
    AreaSingleReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => $offset,
        'effective_at' => $setup['event_start'],
        'created_by' => 1, // assuming user ID 1 exists
        'notes' => 'Test reset at event start',
    ]);

    // create another reset after 3h
    AreaSingleReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => $offset,
        'effective_at' => $setup['event_start']->copy()->addHours(3),
        'created_by' => 1, // assuming user ID 1 exists
        'notes' => 'Test reset after 3 hours',
    ]);

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('to')->first()->count)->toBe($expectedCount);

})->with([
    [0, -90],
    [10, -80],
    [20, -70],
    [60, -30],
    [-50, -140],
    [-79, -169],
]);

it('correctly aggregates with reccurring reset at event start', function () {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // create a reccurring reset every 2 hours
    $reset = AreaRecurringReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => 10,
        'reset_time' => $setup['event_start']->copy()->format('H:i'),
        'timezone' => 'UTC',
        'notes' => 'Test reccurring reset every hour',
    ]);

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('to')->first()->count)->toBe(-79);
});

it('correctly aggregates with reccurring reset after some time', function ($offset, $expectedCount) {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // create a reccurring reset after 3 hours
    $reset = AreaRecurringReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => 10,
        'reset_time' => $setup['event_start']->copy()->addHours(3)->format('H:i'),
        'timezone' => 'UTC',
        'notes' => 'Test reccurring reset every hour after 3 hours',
    ]);

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('to')->first()->count)->toBe(-79);
})->with([
    [0, -90],
    [10, -80],
    [20, -70],
    [60, -30],
    [-50, -140],
    [-79, -169],
]);

it('correctly aggregates with many calculations', function () {
    // arrange
    $setup = setupPeoplecountBasic();
    $testTime = $setup['event_start']->copy();

    // act
    do {
        $testTime = $testTime->addHours(1);
        Carbon::setTestNow($testTime);
        AggregateAreaCounts::dispatch();
    } while ($testTime->lt($setup['event_end']));

    Carbon::setTestNow($setup['event_end']->addMinutes(10));
    AggregateAreaCounts::dispatch();

    // assert
    $area = $setup['area']->refresh();
    expect($area->aggregatedCounts()->latest('to')->first()->count)->toBe(-79);
});

it('correctly aggregates with many calculations with many calls', function () {
    // arrange
    $setup = setupPeoplecountBasic();
    $testTime = $setup['event_start']->copy();

    // act
    do {
        $testTime = $testTime->addHours(1);
        Carbon::setTestNow($testTime);
        AggregateAreaCounts::dispatch();
        AggregateAreaCounts::dispatch();
        AggregateAreaCounts::dispatch();
    } while ($testTime->lt($setup['event_end']));

    Carbon::setTestNow($setup['event_end']->addMinutes(10));
    AggregateAreaCounts::dispatch();
    AggregateAreaCounts::dispatch();
    AggregateAreaCounts::dispatch();

    // assert
    $area = $setup['area']->refresh();
    expect($area->aggregatedCounts()->latest('to')->first()->count)->toBe(-79);
});
