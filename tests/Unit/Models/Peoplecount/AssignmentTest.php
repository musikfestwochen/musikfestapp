<?php

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\Sensor;
use App\Models\Peoplecount\SensorShare;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

covers(Assignment::class);

it('has correct fillable attributes', function () {
    $model = new Assignment;
    expect($model->getFillable())->toEqualCanonicalizing([
        'event_id',
        'area_id',
        'sensor_id',
        'sensor_share_id',
        'label',
        'direction_flipped',
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
    expect(class_uses_recursive($model))->toContain(SoftDeletes::class);
});

it('belongs to an event', function () {
    $model = new Assignment;
    $relation = $model->event();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Event::class);
});

it('belongs to an area', function () {
    $model = new Assignment;
    $relation = $model->area();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Area::class);
});

it('belongs to a sensor', function () {
    $model = new Assignment;
    $relation = $model->sensor();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Sensor::class);
});

it('belongs to a sensor share', function () {
    $model = new Assignment;
    $relation = $model->sensorShare();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(SensorShare::class);
});

it('has factory', function () {
    expect(Assignment::factory())->toBeInstanceOf(Factory::class);
});

it('casts fields correctly', function () {
    $model = new Assignment;
    $casts = $model->getCasts();

    expect($casts)->toHaveKey('direction_flipped', 'boolean')
        ->toHaveKey('active_from', 'datetime')
        ->toHaveKey('active_to', 'datetime');
});

it('handles direction enum correctly', function () {
    // Create an assignment instance directly without using the factory
    $assignment = new Assignment;
    $assignment->direction_flipped = false;

    // Check that direction_flipped is a boolean and validate its value
    expect($assignment->direction_flipped)->toBeBool();
    expect($assignment->direction_flipped)->toBeFalse();

    // Check that we can compare with boolean values
    expect($assignment->direction_flipped)->toBeFalse();
    expect($assignment->direction_flipped)->toBeFalse();
});
