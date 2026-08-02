<?php

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

covers(Area::class);

it('has correct fillable attributes', function () {
    $model = new Area;
    expect($model->getFillable())->toEqualCanonicalizing([
        'name',
        'event_id',
        'data_watermark',
    ]);
});

it('has correct table name', function () {
    $model = new Area;
    expect($model->getTable())->toBe('peoplecount_areas');
});

it('uses timestamps', function () {
    $model = new Area;
    expect($model->timestamps)->toBeTrue();
});

it('uses soft deletes', function () {
    $model = new Area;
    expect(class_uses_recursive($model))->toContain(SoftDeletes::class);
});

it('belongs to an event', function () {
    $model = new Area;
    $relation = $model->event();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Event::class);
});

it('has many alerts', function () {
    $model = new Area;
    $relation = $model->alerts();

    expect($relation)->toBeInstanceOf(HasMany::class);
});

it('has many assignments', function () {
    $model = new Area;
    $relation = $model->assignments();

    expect($relation)->toBeInstanceOf(HasMany::class);
});

it('has many single resets', function () {
    $model = new Area;
    $relation = $model->areaSingleResets();

    expect($relation)->toBeInstanceOf(HasMany::class);
});

it('has many recurring resets', function () {
    $model = new Area;
    $relation = $model->areaRecurringResets();

    expect($relation)->toBeInstanceOf(HasMany::class);
});

it('has factory', function () {
    expect(Area::factory())->toBeInstanceOf(Factory::class);
});
