<?php

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaRecurringReset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

covers(AreaRecurringReset::class);

it('has correct fillable attributes', function () {
    $model = new AreaRecurringReset;
    expect($model->getFillable())->toEqualCanonicalizing([
        'area_id',
        'reset_value',
        'reset_time',
        'timezone',
        'notes',
    ]);
});

it('has correct table name', function () {
    $model = new AreaRecurringReset;
    expect($model->getTable())->toBe('peoplecount_area_recurring_resets');
});

it('uses timestamps', function () {
    $model = new AreaRecurringReset;
    expect($model->timestamps)->toBeTrue();
});

it('does not use soft deletes', function () {
    $model = new AreaRecurringReset;
    expect(in_array(SoftDeletes::class, class_uses_recursive($model)))->toBeFalse();
});

it('has correct casts', function () {
    $model = new AreaRecurringReset;
    $casts = $model->getCasts();

    expect($casts)->toHaveKey('reset_value')
        ->and($casts['reset_value'])->toBe('integer')
        ->and($casts)->toHaveKey('reset_time')
        ->and($casts['reset_time'])->toBe('string');
});

it('belongs to an area', function () {
    $model = new AreaRecurringReset;
    $relation = $model->area();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Area::class);
});

it('gets next daily occurrence', function () {
    // Freeze time at 06:00 UTC (07:00 in Europe/Zurich during standard time)
    Carbon::setTestNow('2024-01-15 06:00:00');

    $model = new AreaRecurringReset;
    $model->reset_time = '08:00';
    $model->timezone = 'Europe/Zurich';

    $nextOccurrence = $model->getNextDailyOccurrence();
    expect($nextOccurrence)->toBeInstanceOf(Carbon::class)
        ->and($nextOccurrence->format('H:i'))->toBe('07:00') // Time in UTC (08:00 in Europe/Zurich)
        ->and($nextOccurrence->format('Y-m-d'))->toBe('2024-01-15'); // Should be today since 08:00 hasn't passed yet

    // Reset time mocking
    Carbon::setTestNow();
});

it('gets next daily occurrence for tomorrow if time has passed today', function () {
    // Freeze time at 10:30 UTC - well after midnight, so next occurrence should be tomorrow
    Carbon::setTestNow('2024-01-15 10:30:00');

    $model = new AreaRecurringReset;
    $model->reset_time = '00:00'; // Midnight - already passed today
    $model->timezone = 'UTC';

    $nextOccurrence = $model->getNextDailyOccurrence();
    expect($nextOccurrence)->toBeInstanceOf(Carbon::class)
        ->and($nextOccurrence->format('H:i'))->toBe('00:00')
        ->and($nextOccurrence->format('Y-m-d'))->toBe('2024-01-16'); // Should be tomorrow since midnight has passed

    // Reset time mocking
    Carbon::setTestNow();
});

it('gets previous daily occurrence', function () {
    // Freeze time at 10:00 UTC (11:00 in Europe/Zurich during standard time) - after 08:00 reset time
    Carbon::setTestNow('2024-01-15 10:00:00');

    $model = new AreaRecurringReset;
    $model->reset_time = '08:00';
    $model->timezone = 'Europe/Zurich';

    $previousOccurrence = $model->getPreviousDailyOccurrence();

    expect($previousOccurrence)->toBeInstanceOf(Carbon::class)
        ->and($previousOccurrence->format('H:i'))->toBe('07:00') // Time in UTC (08:00 in Europe/Zurich)
        ->and($previousOccurrence->format('Y-m-d'))->toBe('2024-01-15') // Should be today since 08:00 has already passed
        ->and($previousOccurrence->isBefore(Carbon::parse('2024-01-15 10:00:00', 'Europe/Zurich')))->toBeTrue();

    // Reset time mocking
    Carbon::setTestNow();
});

it('gets previous daily occurrence for yesterday if time has not yet occurred today', function () {
    // Freeze time at 10:30 UTC - well before 23:59, so previous occurrence should be yesterday
    Carbon::setTestNow('2024-01-15 10:30:00');

    $model = new AreaRecurringReset;
    $model->reset_time = '23:59'; // Late time, has not yet occurred today
    $model->timezone = 'UTC';

    $previousOccurrence = $model->getPreviousDailyOccurrence();

    expect($previousOccurrence)->toBeInstanceOf(Carbon::class)
        ->and($previousOccurrence->format('H:i'))->toBe('23:59')
        ->and($previousOccurrence->format('Y-m-d'))->toBe('2024-01-14') // Should be yesterday since 23:59 hasn't occurred today
        ->and($previousOccurrence->isBefore(Carbon::parse('2024-01-15 10:30:00', 'UTC')))->toBeTrue();

    // Reset time mocking
    Carbon::setTestNow();
});

it('handles different timezones for next daily occurrence', function () {
    // Freeze time at 10:00 UTC (06:00 in America/New_York during standard time) - before 14:30 reset time
    Carbon::setTestNow('2024-01-15 10:00:00');

    $model = new AreaRecurringReset;
    $model->reset_time = '14:30';
    $model->timezone = 'America/New_York';

    $nextOccurrence = $model->getNextDailyOccurrence();
    expect($nextOccurrence)->toBeInstanceOf(Carbon::class)
        ->and($nextOccurrence->format('H:i'))->toBe('19:30') // Time in UTC (14:30 in America/New_York)
        ->and($nextOccurrence->timezone->getName())->toBe('UTC') // Timezone is now UTC
        ->and($nextOccurrence->format('Y-m-d'))->toBe('2024-01-15'); // Should be today since 14:30 hasn't passed yet in NY timezone

    // Reset time mocking
    Carbon::setTestNow();
});

it('gets next daily occurrence when current time equals reset time', function () {
    // Freeze time at exactly 08:00 UTC - same as reset time
    Carbon::setTestNow('2024-01-15 08:00:00');

    $model = new AreaRecurringReset;
    $model->reset_time = '08:00';
    $model->timezone = 'UTC';

    $nextOccurrence = $model->getNextDailyOccurrence();
    expect($nextOccurrence)->toBeInstanceOf(Carbon::class)
        ->and($nextOccurrence->format('H:i'))->toBe('08:00')
        ->and($nextOccurrence->format('Y-m-d'))->toBe('2024-01-16'); // Should be tomorrow since current time >= reset time

    // Reset time mocking
    Carbon::setTestNow();
});

it('gets previous daily occurrence when current time equals reset time', function () {
    // Freeze time at exactly 08:00 UTC - same as reset time
    Carbon::setTestNow('2024-01-15 08:00:00');

    $model = new AreaRecurringReset;
    $model->reset_time = '08:00';
    $model->timezone = 'UTC';

    $previousOccurrence = $model->getPreviousDailyOccurrence();
    expect($previousOccurrence)->toBeInstanceOf(Carbon::class)
        ->and($previousOccurrence->format('H:i'))->toBe('08:00')
        ->and($previousOccurrence->format('Y-m-d'))->toBe('2024-01-15'); // Should be today since current time is not < reset time

    // Reset time mocking
    Carbon::setTestNow();
});

it('gets next daily occurrence with while loop when reset time is before provided datetime', function () {
    // This test covers line 70 - the while loop in getNextDailyOccurrence
    // Create a scenario where the reset time needs to be advanced multiple times
    Carbon::setTestNow('2024-01-15 10:00:00');

    $model = new AreaRecurringReset;
    $model->reset_time = '08:00';
    $model->timezone = 'UTC';

    // Pass a datetime that's after today's reset time but before tomorrow's
    $fromTime = Carbon::parse('2024-01-15 09:00:00', 'UTC');

    $nextOccurrence = $model->getNextDailyOccurrence($fromTime);
    expect($nextOccurrence)->toBeInstanceOf(Carbon::class)
        ->and($nextOccurrence->format('H:i'))->toBe('08:00')
        ->and($nextOccurrence->format('Y-m-d'))->toBe('2024-01-16'); // Should be tomorrow

    Carbon::setTestNow();
});

it('gets occurrences between two dates', function () {
    $model = new AreaRecurringReset;
    $model->reset_time = '12:00:00';
    $model->timezone = 'UTC';

    $start = Carbon::parse('2024-01-15 10:00:00', 'UTC');
    $end = Carbon::parse('2024-01-17 14:00:00', 'UTC');

    $occurrences = $model->getOccurrencesBetween($start, $end);

    $expectedOccurrences = [
        Carbon::parse('2024-01-15 12:00:00', 'UTC'),
        Carbon::parse('2024-01-16 12:00:00', 'UTC'),
        Carbon::parse('2024-01-17 12:00:00', 'UTC'),
    ];

    expect($occurrences)->toBeArray()
        ->and(count($occurrences))->toBe(3)
        ->and($occurrences)->toEqualCanonicalizing($expectedOccurrences);
});

it('gets occurrences between two dates in different timezones', function () {
    $model = new AreaRecurringReset;
    $model->reset_time = '15:30:00';
    $model->timezone = 'Europe/Zurich';

    $start = Carbon::parse('2024-01-15 10:00:00', 'UTC');
    $end = Carbon::parse('2024-01-17 19:00:00', 'UTC');

    $occurrences = $model->getOccurrencesBetween($start, $end);

    // Convert expected occurrences to UTC for comparison
    $expectedOccurrences = [
        Carbon::parse('2024-01-15 14:30:00', 'UTC'),
        Carbon::parse('2024-01-16 14:30:00', 'UTC'),
        Carbon::parse('2024-01-17 14:30:00', 'UTC'),
    ];

    expect($occurrences)->toBeArray()
        ->and(count($occurrences))->toBe(3)
        ->and($occurrences)->toEqualCanonicalizing($expectedOccurrences);
});

it('gets occurrences between dates with single occurrence', function () {

    $model = new AreaRecurringReset;
    $model->reset_time = '15:30';
    $model->timezone = 'UTC';

    $start = Carbon::parse('2024-01-15 10:00:00', 'UTC');
    $end = Carbon::parse('2024-01-15 20:00:00', 'UTC');

    $occurrences = $model->getOccurrencesBetween($start, $end);

    expect($occurrences)->toBeArray()
        ->and(count($occurrences))->toBe(1) // Implementation now only returns occurrences within the range
        ->and($occurrences[0]->format('Y-m-d H:i'))->toBe('2024-01-15 15:30') // Time in UTC
        ->and($occurrences[0]->timezone->getName())->toBe('UTC'); // Timezone is UTC

});

it('gets previous daily occurrence with explicit Carbon parameter', function () {
    // This test covers the $from instanceof Carbon branch in getPreviousDailyOccurrence
    Carbon::setTestNow('2024-01-15 10:00:00');

    $model = new AreaRecurringReset;
    $model->reset_time = '08:00';
    $model->timezone = 'UTC';

    // Create an explicit Carbon instance to pass as parameter
    $explicitTime = Carbon::parse('2024-01-15 07:00:00', 'UTC');

    $previousOccurrence = $model->getPreviousDailyOccurrence($explicitTime);

    // Since 07:00 is before the reset time (08:00), the previous occurrence should be from the day before
    expect($previousOccurrence)->toBeInstanceOf(Carbon::class)
        ->and($previousOccurrence->format('H:i'))->toBe('08:00')
        ->and($previousOccurrence->format('Y-m-d'))->toBe('2024-01-14');

    Carbon::setTestNow();
});

it('verifies date part is correctly set in previous daily occurrence', function () {
    // This test verifies that the date part of resetTime is correctly set in getPreviousDailyOccurrence
    // We'll use a specific date that's different from the default to ensure the setDate method is working

    // Create a mock Carbon instance for testing
    $mockNow = Carbon::parse('2023-12-25 10:00:00', 'UTC'); // Christmas day

    $model = new AreaRecurringReset;
    $model->reset_time = '08:00';
    $model->timezone = 'UTC';

    // Call the method with our mock date
    $previousOccurrence = $model->getPreviousDailyOccurrence($mockNow);

    // Verify that the date part matches our mock date (since 10:00 is after 08:00)
    expect($previousOccurrence->format('Y-m-d'))->toBe('2023-12-25')
        ->and($previousOccurrence->format('H:i'))->toBe('08:00')
        // Most importantly, verify that the year, month, and day match our mock date
        ->and($previousOccurrence->year)->toBe($mockNow->year)
        ->and($previousOccurrence->month)->toBe($mockNow->month)
        ->and($previousOccurrence->day)->toBe($mockNow->day);

    // Now test with a time before the reset time
    $mockNow = Carbon::parse('2023-12-25 07:00:00', 'UTC'); // Christmas day, before reset

    // Call the method with our mock date
    $previousOccurrence = $model->getPreviousDailyOccurrence($mockNow);

    // Verify that the date part is the day before our mock date (since 07:00 is before 08:00)
    expect($previousOccurrence->format('Y-m-d'))->toBe('2023-12-24')
        ->and($previousOccurrence->format('H:i'))->toBe('08:00')
        // Verify that the year, month, and day are correctly set to the day before
        ->and($previousOccurrence->year)->toBe($mockNow->copy()->subDay()->year)
        ->and($previousOccurrence->month)->toBe($mockNow->copy()->subDay()->month)
        ->and($previousOccurrence->day)->toBe($mockNow->copy()->subDay()->day);
});

it('verifies occurrences are only added if within range', function () {
    // This test verifies that occurrences are only added if they are within the range
    Carbon::setTestNow('2024-01-15 08:00:00');

    $model = new AreaRecurringReset;
    $model->reset_time = '12:00';
    $model->timezone = 'UTC';

    // Test with a next occurrence that's outside the range
    $start = Carbon::parse('2024-01-16 13:00:00', 'UTC'); // After the reset time on Jan 16
    $end = Carbon::parse('2024-01-17 11:00:00', 'UTC');   // Before the reset time on Jan 17

    $occurrences = $model->getOccurrencesBetween($start, $end);

    // There should be no occurrences since the next occurrence after start (Jan 17 12:00)
    // is after the end time (Jan 17 11:00)
    expect($occurrences)->toBeArray()
        ->and(count($occurrences))->toBe(0);

    // Now test with a range that includes exactly one occurrence
    $start = Carbon::parse('2024-01-16 10:00:00', 'UTC'); // Before the reset time on Jan 16
    $end = Carbon::parse('2024-01-16 14:00:00', 'UTC');   // After the reset time on Jan 16

    $occurrences = $model->getOccurrencesBetween($start, $end);

    expect($occurrences)->toBeArray()
        ->and(count($occurrences))->toBe(1)
        ->and($occurrences[0]->format('Y-m-d H:i'))->toBe('2024-01-16 12:00');

    Carbon::setTestNow();
});

it('has factory', function () {
    expect(AreaRecurringReset::factory())->toBeInstanceOf(Factory::class);
});

it('throws on invalid timezone when getting next daily occurrence', function () {
    $model = new AreaRecurringReset;
    $model->reset_time = '08:00';
    $model->timezone = 'Not/AZone';

    expect(fn (): Carbon => $model->getNextDailyOccurrence())->toThrow(Exception::class);
});

it('does not mutate the provided $from Carbon instance and returns UTC timezone', function () {
    $from = Carbon::parse('2024-01-15 23:30:00', 'UTC');

    $model = new AreaRecurringReset;
    $model->reset_time = '08:00';
    $model->timezone = 'America/New_York';

    $clone = $from->copy();
    $next = $model->getNextDailyOccurrence($from);

    // The provided instance should remain unchanged
    expect($from->toIso8601String())->toBe($clone->toIso8601String());
    // Returned occurrence should be in UTC
    expect($next->timezone->getName())->toBe('UTC');
});

it('includes occurrences equal to start and end boundaries', function () {
    $model = new AreaRecurringReset;
    $model->reset_time = '12:00';
    $model->timezone = 'UTC';

    $start = Carbon::parse('2024-01-15 12:00:00', 'UTC');
    $end = Carbon::parse('2024-01-16 12:00:00', 'UTC');

    $occ = $model->getOccurrencesBetween($start, $end);

    expect($occ)->toBeArray()
        ->and(count($occ))->toBe(2)
        ->and($occ[0]->equalTo($start))->toBeTrue()
        ->and($occ[1]->equalTo($end))->toBeTrue()
        ->and($occ[0]->timezone->getName())->toBe('UTC')
        ->and($occ[1]->timezone->getName())->toBe('UTC');
});

it('returns strictly increasing daily occurrences without duplicates across DST window', function () {
    $model = new AreaRecurringReset;
    $model->reset_time = '03:00';
    $model->timezone = 'Europe/Zurich';

    // Window that spans the EU spring DST transition in 2024 (2024-03-31)
    $start = Carbon::parse('2024-03-29 00:00:00', 'UTC');
    $end = Carbon::parse('2024-04-03 23:59:59', 'UTC');

    $occ = $model->getOccurrencesBetween($start, $end);

    // Expect one occurrence per day inclusive: 29,30,31,1,2,3 => 6
    expect($occ)->toBeArray()->and(count($occ))->toBe(6);
    $counter = count($occ);

    for ($i = 0; $i < $counter; $i++) {
        // Ensure all returned timestamps are in UTC
        expect($occ[$i]->timezone->getName())->toBe('UTC');
        // Ensure each occurrence is within the requested range
        expect($occ[$i]->gte($start))->toBeTrue();
        expect($occ[$i]->lte($end))->toBeTrue();
        if ($i > 0) {
            // Strictly increasing
            expect($occ[$i]->gt($occ[$i - 1]))->toBeTrue();
        }
    }
});
