<?php

use App\Enums\Peoplecount\Direction;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\Sensor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

covers(Assignment::class);

it('has correct fillable attributes', function () {
    $model = new Assignment;
    expect($model->getFillable())->toEqualCanonicalizing([
        'event_id',
        'area_id',
        'sensor_id',
        'direction',
        'active_from',
        'active_to',
    ]);
});

it('has correct table name', function () {
    $model = new Assignment;
    expect($model->getTable())->toBe('peoplecount_assignments');
});

it('uses timestamps', function () {
    $model = new Assignment;
    expect($model->timestamps)->toBeTrue();
});

it('uses soft deletes', function () {
    $model = new Assignment;
    expect(in_array(SoftDeletes::class, class_uses_recursive($model)))->toBeTrue();
});

it('belongs to an event', function () {
    $model = new Assignment;
    $relation = $model->event();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Event::class);
});

it('belongs to an area', function () {
    $model = new Assignment;
    $relation = $model->area();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Area::class);
});

it('belongs to a sensor', function () {
    $model = new Assignment;
    $relation = $model->sensor();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Sensor::class);
});

it('has factory', function () {
    expect(Assignment::factory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Factories\Factory::class);
});

it('casts fields correctly', function () {
    $model = new Assignment;
    $casts = $model->getCasts();

    expect($casts)->toHaveKey('direction', Direction::class);
    expect($casts)->toHaveKey('active_from', 'datetime');
    expect($casts)->toHaveKey('active_to', 'datetime');
});

it('handles direction enum correctly', function () {
    // Create an assignment instance directly without using the factory
    $assignment = new Assignment;
    $assignment->direction = Direction::IN;

    // Check that the direction is a Direction enum instance
    expect($assignment->direction)->toBeInstanceOf(Direction::class);
    expect($assignment->direction)->toBe(Direction::IN);

    // Check that we can compare with enum values
    expect($assignment->direction === Direction::IN)->toBeTrue();
    expect($assignment->direction === Direction::OUT)->toBeFalse();

    // Check that the label method works
    expect($assignment->direction->label())->toBe('In');
});
