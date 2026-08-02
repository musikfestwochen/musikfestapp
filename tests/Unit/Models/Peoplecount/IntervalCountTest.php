<?php

use App\Models\Peoplecount\IntervalCount;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

covers(IntervalCount::class);

it('has correct fillable attributes', function () {
    $model = new IntervalCount;
    expect($model->getFillable())->toEqualCanonicalizing([
        'sensor_id',
        'ts_from',
        'ts_to',
        'received_at',
        'count_in',
        'count_out',
    ]);
});

it('has correct table name', function () {
    $model = new IntervalCount;
    expect($model->getTable())->toBe('peoplecount_interval_counts');
});

it('does not use timestamps', function () {
    $model = new IntervalCount;
    expect($model->timestamps)->toBeFalse();
});

it('casts all attributes correctly', function () {
    $model = new IntervalCount;
    expect($model->getCasts())->toMatchArray([
        'ts_from' => 'immutable_datetime',
        'ts_to' => 'immutable_datetime',
        'received_at' => 'immutable_datetime',
        'count_in' => 'integer',
        'count_out' => 'integer',
    ]);
});

it('casts count_in to integer', function () {
    $intervalCount = new IntervalCount([
        'count_in' => '42',
        'count_out' => '15',
    ]);

    expect($intervalCount->count_in)->toBeInt()
        ->toBe(42);
});

it('casts count_out to integer', function () {
    $intervalCount = new IntervalCount([
        'count_in' => '10',
        'count_out' => '25',
    ]);

    expect($intervalCount->count_out)->toBeInt()
        ->toBe(25);
});

it('belongs to a sensor', function () {
    $model = new IntervalCount;
    $relation = $model->sensor();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
});
