<?php

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Models\Peoplecount\Event;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

covers(AreaRecurringReset::class);

it('has correct fillable attributes', function () {
    $model = new AreaRecurringReset;
    expect($model->getFillable())->toEqualCanonicalizing([
        'area_id',
        'event_id',
        'reset_value',
        'rrule',
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

    expect($casts)->toHaveKey('reset_value');
    expect($casts['reset_value'])->toBe('integer');
});

it('belongs to an area', function () {
    $model = new AreaRecurringReset;
    $relation = $model->area();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Area::class);
});

it('belongs to an event', function () {
    $model = new AreaRecurringReset;
    $relation = $model->event();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Event::class);
});

it('validates valid RRULE', function () {
    $model = new AreaRecurringReset;
    $model->rrule = 'FREQ=DAILY;INTERVAL=1';

    expect($model->validateRRule())->toBeTrue();
});

it('validates invalid RRULE', function () {
    $model = new AreaRecurringReset;
    $model->rrule = 'INVALID_RRULE';

    expect($model->validateRRule())->toBeFalse();
});

it('parses valid RRULE', function () {
    $model = new AreaRecurringReset;
    $model->rrule = 'FREQ=DAILY;INTERVAL=1';

    $rrule = $model->parseRRule();
    expect($rrule)->toBeInstanceOf(\RRule\RRule::class);
});

it('gets next occurrences', function () {
    $model = new AreaRecurringReset;
    $model->rrule = 'FREQ=DAILY;INTERVAL=1';

    $occurrences = $model->getNextOccurrences(3);
    expect($occurrences)->toBeArray();
    expect($occurrences)->toHaveCount(3);

    foreach ($occurrences as $occurrence) {
        expect($occurrence)->toBeInstanceOf(\DateTime::class);
    }
});

it('limits next occurrences to specified count', function () {
    $model = new AreaRecurringReset;
    $model->rrule = 'FREQ=DAILY;INTERVAL=1';

    $occurrences = $model->getNextOccurrences(2);
    expect($occurrences)->toHaveCount(2);
});

it('has factory', function () {
    expect(AreaRecurringReset::factory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Factories\Factory::class);
});
