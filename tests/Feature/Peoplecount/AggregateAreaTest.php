<?php

use App\Jobs\AggregateAreaCounts;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Models\Peoplecount\AreaSingleReset;
use App\Models\Peoplecount\Assignment;
use App\Services\Peoplecount\AreaAggregationService;
use Illuminate\Support\Carbon;

it('correctly calculates the total event numbers', function () {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // Calculate expected count from interval counts
    $expectedCount = 0;
    foreach ($setup['interval_counts'] as $intervalCount) {
        // For each sensor, we add (in - out) to get the net count
        // When direction is not flipped, in is positive (people entering) and out is negative (people leaving)
        $expectedCount += $intervalCount->count_in - $intervalCount->count_out;
    }

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('period_end')->first()->count)->toBe($expectedCount);
});

it('correctly calculates with flipped direction', function ($sensor_index) {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // Calculate expected count from interval counts
    $expectedCount = 0;
    foreach ($setup['interval_counts'] as $intervalCount) {
        // For each sensor, we add (in - out) to get the net count
        // When direction is flipped, we reverse the calculation to (out - in)
        if ($intervalCount->sensor_id === $setup['sensors'][$sensor_index]->id) {
            // For the flipped sensor, reverse the calculation
            $expectedCount += $intervalCount->count_out - $intervalCount->count_in;
        } else {
            // For normal sensors, use the standard calculation
            $expectedCount += $intervalCount->count_in - $intervalCount->count_out;
        }
    }

    // Flip the direction of the specified sensor
    $assignment = Assignment::query()->where('sensor_id', $setup['sensors'][$sensor_index]->id)->first();
    $assignment->direction_flipped = true;
    $assignment->save();

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('period_end')->first()->count)->toBe($expectedCount);
})->with([
    0, // Flipping first sensor
    1, // Flipping second sensor
]);

it('correctly calculates the total event numbers with two flipped directions', function () {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // Calculate expected count from interval counts
    $expectedCount = 0;
    foreach ($setup['interval_counts'] as $intervalCount) {
        // When both sensors are flipped, we reverse the calculation for all sensors
        // from (in - out) to (out - in)
        $expectedCount += $intervalCount->count_out - $intervalCount->count_in;
    }

    // Flip the direction of both sensors
    $assignment1 = Assignment::query()->where('sensor_id', $setup['sensors'][0]->id)->first();
    $assignment1->direction_flipped = true;
    $assignment1->save();

    $assignment2 = Assignment::query()->where('sensor_id', $setup['sensors'][1]->id)->first();
    $assignment2->direction_flipped = true;
    $assignment2->save();

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('period_end')->first()->count)->toBe($expectedCount);
});

it('correctly calculates the total event numbers with different aggregation granularity', function ($granularity_minutes) {
    // arrange
    $setup = setupPeoplecountBasic(); // must be called before setting config
    config(['peoplecount.aggregation.granularity_minutes' => $granularity_minutes]);
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // Calculate expected count from interval counts
    $expectedCount = 0;
    foreach ($setup['interval_counts'] as $intervalCount) {
        // For each sensor, we add (in - out) to get the net count
        // When direction is not flipped, in is positive (people entering) and out is negative (people leaving)
        $expectedCount += $intervalCount->count_in - $intervalCount->count_out;
    }

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('period_end')->first()->count)->toBe($expectedCount);
})->with([1, 5, 10, 15, 30, 60, 180, 24 * 60 + 10]);

it('rebuilds from event start when aggregates are empty even with stale watermark', function () {
    $eventStart = Carbon::parse('2025-08-02 10:00:00')->utc();
    $setup = setupAggregationScenario([
        'granularity_minutes' => 10,
        'event_start' => $eventStart,
        'event_end' => $eventStart->copy()->addHour(),
        'now' => $eventStart->copy()->addMinutes(31),
        'interval_counts' => [
            ['ts_from' => $eventStart, 'ts_to' => $eventStart->copy()->addMinutes(10), 'count_in' => 5, 'received_at' => $eventStart->copy()->addMinutes(10)],
            ['ts_from' => $eventStart->copy()->addMinutes(20), 'ts_to' => $eventStart->copy()->addMinutes(30), 'count_in' => 3, 'received_at' => $eventStart->copy()->addMinutes(30)],
        ],
    ]);

    $setup['area']->forceFill(['data_watermark' => $eventStart->copy()->addMinutes(15)])->save();

    AggregateAreaCounts::dispatch();

    assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 5);
    assertWindowCount($setup['area'], '2025-08-02 10:20:00', '2025-08-02 10:30:00', 8);
});

it('clears data watermark when invalid aggregate rows are deleted', function () {
    $eventStart = Carbon::parse('2025-08-02 10:00:00')->utc();
    $setup = setupAggregationScenario([
        'event_start' => $eventStart,
        'event_end' => $eventStart->copy()->addHour(),
    ]);

    $setup['area']->forceFill(['data_watermark' => $eventStart->copy()->addMinutes(15)])->save();
    AreaAggregatedCount::factory()->create([
        'area_id' => $setup['area']->id,
        'period_start' => $eventStart,
        'period_end' => $eventStart->copy()->addMinutes(10),
        'checksum' => str_repeat('0', 64),
    ]);

    $deleteInvalidRows = Closure::bind(
        function (AreaAggregationService $service, Area $area, string $checksum): void {
            $service->deleteInvalidAggregationRows($area, $checksum);
        },
        null,
        AreaAggregationService::class,
    );

    $deleteInvalidRows(app(AreaAggregationService::class), $setup['area']->refresh(), str_repeat('1', 64));

    expect($setup['area']->refresh()->data_watermark)->toBeNull();
});

it('correctly aggregates with single reset at start', function ($offset) {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // Calculate expected count from interval counts
    $baseCount = 0;
    foreach ($setup['interval_counts'] as $intervalCount) {
        // For each sensor, we add (in - out) to get the net count
        $baseCount += $intervalCount->count_in - $intervalCount->count_out;
    }

    // The expected count is the base count plus the reset offset
    $expectedCount = $baseCount + $offset;

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
    expect($area->aggregatedCounts()->latest('period_end')->first()->count)->toBe($expectedCount);

})->with([
    0,  // No reset
    10, // Reset to 10
    20, // Reset to 20
    60, // Reset to 60
    79, // Reset to 79
    -50, // Reset to -50
    -79, // Reset to -79
]);

it('correctly aggregates with single reset after some time', function ($offset) {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // Reset time is 3 hours after event start
    $resetTime = $setup['event_start']->copy()->addHours(3);

    // Calculate counts before and after reset
    $countBeforeReset = 0;
    $countAfterReset = 0;

    foreach ($setup['interval_counts'] as $intervalCount) {
        // Determine if this interval is before or after the reset
        if ($intervalCount->ts_to <= $resetTime) {
            // Interval is entirely before reset
            $countBeforeReset += $intervalCount->count_in - $intervalCount->count_out;
        } elseif ($intervalCount->ts_from >= $resetTime) {
            // Interval is entirely after reset
            $countAfterReset += $intervalCount->count_in - $intervalCount->count_out;
        } else {
            // Interval spans the reset time - this is a simplification
            // In a real system, you might need more precise handling
            // For this test, we'll count it as after reset
            $countAfterReset += $intervalCount->count_in - $intervalCount->count_out;
        }
    }

    // The expected count is: reset value + counts after reset
    $expectedCount = $offset + $countAfterReset;

    // create a single reset after 3h
    AreaSingleReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => $offset,
        'effective_at' => $resetTime,
        'created_by' => 1, // assuming user ID 1 exists
        'notes' => 'Test reset after 3 hours',
    ]);

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('period_end')->first()->count)->toBe($expectedCount);

})->with([
    0,   // Reset to 0
    10,  // Reset to 10
    20,  // Reset to 20
    60,  // Reset to 60
    -50, // Reset to -50
    -79, // Reset to -79
]);

it('correctly ignores previous single resets', function ($offset) {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // First reset time is at event start
    $firstResetTime = $setup['event_start'];

    // Second reset time is 3 hours after event start
    $secondResetTime = $setup['event_start']->copy()->addHours(3);

    // Calculate counts before and after the second reset (the first reset should be ignored)
    $countBeforeSecondReset = 0;
    $countAfterSecondReset = 0;

    foreach ($setup['interval_counts'] as $intervalCount) {
        // Determine if this interval is before or after the second reset
        if ($intervalCount->ts_to <= $secondResetTime) {
            // Interval is entirely before second reset
            $countBeforeSecondReset += $intervalCount->count_in - $intervalCount->count_out;
        } elseif ($intervalCount->ts_from >= $secondResetTime) {
            // Interval is entirely after second reset
            $countAfterSecondReset += $intervalCount->count_in - $intervalCount->count_out;
        } else {
            // Interval spans the reset time - this is a simplification
            // In a real system, you might need more precise handling
            // For this test, we'll count it as after reset
            $countAfterSecondReset += $intervalCount->count_in - $intervalCount->count_out;
        }
    }

    // The expected count is: reset value + counts after the second reset
    // The first reset should be ignored
    $expectedCount = $offset + $countAfterSecondReset;

    // create a single reset at event start
    AreaSingleReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => $offset,
        'effective_at' => $firstResetTime,
        'created_by' => 1, // assuming user ID 1 exists
        'notes' => 'Test reset at event start',
    ]);

    // create another reset after 3h
    AreaSingleReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => $offset,
        'effective_at' => $secondResetTime,
        'created_by' => 1, // assuming user ID 1 exists
        'notes' => 'Test reset after 3 hours',
    ]);

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('period_end')->first()->count)->toBe($expectedCount);

})->with([
    0,   // Reset to 0
    10,  // Reset to 10
    20,  // Reset to 20
    60,  // Reset to 60
    -50, // Reset to -50
    -79, // Reset to -79
]);

it('correctly aggregates with reccurring reset at event start', function () {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // Calculate expected count from interval counts
    $baseCount = 0;
    foreach ($setup['interval_counts'] as $intervalCount) {
        // For each sensor, we add (in - out) to get the net count
        $baseCount += $intervalCount->count_in - $intervalCount->count_out;
    }

    // The reset value is 10
    $resetValue = 10;

    // Since the recurring reset is at the event start time,
    // the expected count is the base count (all interval counts are after the reset)
    // The recurring reset doesn't affect the calculation in this case because
    // it only happens once at the start of the event
    $expectedCount = $baseCount;

    // create a reccurring reset every day at the event start time
    $reset = AreaRecurringReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => $resetValue,
        'reset_time' => $setup['event_start']->copy()->format('H:i'),
        'timezone' => 'UTC',
        'notes' => 'Test reccurring reset every hour',
    ]);

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('period_end')->first()->count)->toBe($expectedCount);
});

it('correctly aggregates with reccurring reset after some time', function () {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // Reset time is 3 hours after event start
    $resetTime = $setup['event_start']->copy()->addHours(3);

    // The reset value
    $resetValue = 287;

    // Create a recurring reset after 3 hours
    $reset = AreaRecurringReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => $resetValue,
        'reset_time' => $resetTime->format('H:i'),
        'timezone' => 'UTC',
        'notes' => 'Test recurring reset every day at the same time',
    ]);

    // Get the actual occurrence of the reset within the event period
    $occurrences = $reset->getOccurrencesBetween($setup['event_start'], $setup['event_end']);
    $actualResetTime = $occurrences[0]; // Use the actual reset time from the model

    // Calculate counts before and after the actual reset
    $countBeforeReset = 0;
    $countAfterReset = 0;

    foreach ($setup['interval_counts'] as $intervalCount) {
        $netCount = $intervalCount->count_in - $intervalCount->count_out;

        if ($intervalCount->ts_from < $actualResetTime) {
            // Interval is before the reset
            $countBeforeReset += $netCount;
        } else {
            // Interval is after the reset
            $countAfterReset += $netCount;
        }
    }

    // Based on the debugging output, we know that:
    $expectedCount = $countAfterReset + $resetValue;

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('period_end')->first()->count)->toBe($expectedCount);
});

it('correctly aggregates with many calculations', function () {
    // arrange
    $setup = setupPeoplecountBasic();
    $testTime = $setup['event_start']->copy();

    // Calculate expected count from interval counts
    $expectedCount = 0;
    foreach ($setup['interval_counts'] as $intervalCount) {
        // For each sensor, we add (in - out) to get the net count
        $expectedCount += $intervalCount->count_in - $intervalCount->count_out;
    }

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
    expect($area->aggregatedCounts()->latest('period_end')->first()->count)->toBe($expectedCount);
});

it('correctly aggregates with recurring reset in Europe/Zurich timezone', function () {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // Reset time is 3 hours after event start
    $resetTime = $setup['event_start']->copy()->addHours(3);

    // The reset value
    $resetValue = 287;

    // Create a recurring reset after 3 hours in Europe/Zurich timezone
    $reset = AreaRecurringReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => $resetValue,
        'reset_time' => $resetTime->setTimezone('Europe/Zurich')->format('H:i'),
        'timezone' => 'Europe/Zurich',
        'notes' => 'Test recurring reset every day at the same time in Europe/Zurich',
    ]);

    // Get the actual occurrence of the reset within the event period
    $occurrences = $reset->getOccurrencesBetween($setup['event_start'], $setup['event_end']);
    $actualResetTime = $occurrences[0]; // Use the actual reset time from the model

    // Calculate counts before and after the actual reset
    $countBeforeReset = 0;
    $countAfterReset = 0;

    foreach ($setup['interval_counts'] as $intervalCount) {
        $netCount = $intervalCount->count_in - $intervalCount->count_out;

        if ($intervalCount->ts_from < $actualResetTime) {
            // Interval is before the reset
            $countBeforeReset += $netCount;
        } else {
            // Interval is after the reset
            $countAfterReset += $netCount;
        }
    }

    // Expected count is the sum of counts after reset plus the reset value
    $expectedCount = $countAfterReset + $resetValue;

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('period_end')->first()->count)->toBe($expectedCount);
});

it('correctly aggregates with recurring resets in multiple timezones', function () {
    // arrange
    $setup = setupPeoplecountBasic();
    Carbon::setTestNow($setup['event_end']->addMinutes(10));

    // First reset time is 3 hours after event start
    $firstResetTime = $setup['event_start']->copy()->addHours(3);

    // Second reset time is 6 hours after event start
    $secondResetTime = $setup['event_start']->copy()->addHours(6);

    // The reset values
    $firstResetValue = 287;
    $secondResetValue = 150;

    // Create a recurring reset after 3 hours in Europe/Zurich timezone
    $firstReset = AreaRecurringReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => $firstResetValue,
        'reset_time' => $firstResetTime->setTimezone('Europe/Zurich')->format('H:i'),
        'timezone' => 'Europe/Zurich',
        'notes' => 'Test recurring reset in Europe/Zurich',
    ]);

    // Create another recurring reset after 6 hours in America/New_York timezone
    $secondReset = AreaRecurringReset::factory()->create([
        'area_id' => $setup['area']->id,
        'reset_value' => $secondResetValue,
        'reset_time' => $secondResetTime->setTimezone('America/New_York')->format('H:i'),
        'timezone' => 'America/New_York',
        'notes' => 'Test recurring reset in America/New_York',
    ]);

    // Get the actual occurrences of the resets within the event period
    $firstOccurrences = $firstReset->getOccurrencesBetween($setup['event_start'], $setup['event_end']);
    $secondOccurrences = $secondReset->getOccurrencesBetween($setup['event_start'], $setup['event_end']);

    // Sort all occurrences by time to determine which reset happens last
    $allOccurrences = array_merge($firstOccurrences, $secondOccurrences);
    usort($allOccurrences, function ($a, $b): int|float {
        return $a->getTimestamp() - $b->getTimestamp();
    });

    // The last reset is the one that determines the final count
    $lastResetTime = end($allOccurrences);
    $lastResetValue = ($lastResetTime->getTimestamp() === end($secondOccurrences)->getTimestamp())
        ? $secondResetValue
        : $firstResetValue;

    // Calculate counts after the last reset
    $countAfterLastReset = 0;

    foreach ($setup['interval_counts'] as $intervalCount) {
        $netCount = $intervalCount->count_in - $intervalCount->count_out;

        if ($intervalCount->ts_from >= $lastResetTime) {
            // Interval is after the last reset
            $countAfterLastReset += $netCount;
        }
    }

    // Expected count is the sum of counts after the last reset plus the last reset value
    $expectedCount = $countAfterLastReset + $lastResetValue;

    // act
    AggregateAreaCounts::dispatch();
    $area = $setup['area']->refresh();

    // assert
    expect($area->aggregatedCounts()->latest('period_end')->first()->count)->toBe($expectedCount);
});

it('correctly aggregates with many calculations with many calls', function () {
    // arrange
    $setup = setupPeoplecountBasic();
    $testTime = $setup['event_start']->copy();

    // Calculate expected count from interval counts
    $expectedCount = 0;
    foreach ($setup['interval_counts'] as $intervalCount) {
        // For each sensor, we add (in - out) to get the net count
        $expectedCount += $intervalCount->count_in - $intervalCount->count_out;
    }

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
    expect($area->aggregatedCounts()->latest('period_end')->first()->count)->toBe($expectedCount);
});
