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
        ->and($nextOccurrence->format('H:i'))->toBe('08:00')
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
        ->and($previousOccurrence->format('H:i'))->toBe('08:00')
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
        ->and($nextOccurrence->format('H:i'))->toBe('14:30')
        ->and($nextOccurrence->timezone->getName())->toBe('America/New_York')
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

it('has factory', function () {
    expect(AreaRecurringReset::factory())->toBeInstanceOf(Factory::class);
});
