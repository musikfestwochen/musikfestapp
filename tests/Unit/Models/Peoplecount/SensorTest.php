<?php

use App\Models\Peoplecount\Sensor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

covers(Sensor::class);

it('has correct fillable attributes', function () {
    $sensor = new Sensor;
    expect($sensor->getFillable())->toEqualCanonicalizing([
        'vendor',
        'model',
        'serial',
        'api_token',
        'organization_id',
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

it('has correct table name', function () {
    $sensor = new Sensor;
    expect($sensor->getTable())->toBe('peoplecount_sensors');
});
