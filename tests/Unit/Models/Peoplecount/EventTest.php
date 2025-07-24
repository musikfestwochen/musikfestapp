<?php

use App\Models\Peoplecount\Event;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

covers(Event::class);

it('has correct fillable attributes', function () {
    $model = new Event;
    expect($model->getFillable())->toEqualCanonicalizing([
        'name',
        'organization_id',
        'starts_at',
        'ends_at',
    ]);
});

it('has correct table name', function () {
    $model = new Event;
    expect($model->getTable())->toBe('peoplecount_events');
});

it('uses timestamps', function () {
    $model = new Event;
    expect($model->timestamps)->toBeTrue();
});

it('uses soft deletes', function () {
    $model = new Event;
    expect(in_array(SoftDeletes::class, class_uses_recursive($model)))->toBeTrue();
});

it('casts attributes correctly', function () {
    $model = new Event;
    expect($model->getCasts())->toMatchArray([
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ]);
});

it('belongs to an organization', function () {
    $model = new Event;
    $relation = $model->organization();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
});
