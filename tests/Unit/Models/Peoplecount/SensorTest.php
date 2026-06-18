<?php

use App\Models\Peoplecount\Sensor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

covers(Sensor::class);

it('has correct fillable attributes', function () {
    $sensor = new Sensor;
    expect($sensor->getFillable())->toEqualCanonicalizing([
        'vendor',
        'model',
        'serial',
        'api_token',
        'organization_id',
        'archived_at',
    ]);
});

it('has correct hidden attributes', function () {
    $sensor = new Sensor;
    expect($sensor->getHidden())->toBeEmpty();
});

it('organization relationship returns belongsTo', function () {
    $reflection = new ReflectionMethod(Sensor::class, 'organization');
    $returnType = $reflection->getReturnType();

    expect($returnType)
        ->not()->toBeNull()
        ->and($returnType->getName())->toBe(BelongsTo::class);
});

it('BelongsTo an organization', function () {
    $sensor = new Sensor;
    $relation = $sensor->organization();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
});

it('has many interval counts', function () {
    $sensor = new Sensor;
    $relation = $sensor->intervalCounts();

    expect($relation)->toBeInstanceOf(HasMany::class);
});

it('has many assignments', function () {
    $sensor = new Sensor;
    $relation = $sensor->assignments();

    expect($relation)->toBeInstanceOf(HasMany::class);
});

it('has many shares', function () {
    $sensor = new Sensor;
    $relation = $sensor->shares();

    expect($relation)->toBeInstanceOf(HasMany::class);
});

it('has correct table name', function () {
    $sensor = new Sensor;
    expect($sensor->getTable())->toBe('peoplecount_sensors');
});
