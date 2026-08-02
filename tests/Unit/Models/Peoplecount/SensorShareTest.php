<?php

use App\Models\Organization;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Sensor;
use App\Models\Peoplecount\SensorShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

covers(SensorShare::class);

it('has correct fillable attributes', function () {
    $model = new SensorShare;

    expect($model->getFillable())->toEqualCanonicalizing([
        'sensor_id',
        'owner_organization_id',
        'borrower_organization_id',
        'created_by',
        'starts_at',
        'ends_at',
    ]);
});

it('has correct table name', function () {
    $model = new SensorShare;

    expect($model->getTable())->toBe('peoplecount_sensor_shares');
});

it('uses timestamps', function () {
    $model = new SensorShare;

    expect($model->timestamps)->toBeTrue();
});

it('does not use soft deletes', function () {
    $model = new SensorShare;

    expect(class_uses_recursive($model))->not->toContain(SoftDeletes::class);
});

it('belongs to a sensor', function () {
    $model = new SensorShare;
    $relation = $model->sensor();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Sensor::class);
});

it('belongs to an owner organization', function () {
    $model = new SensorShare;
    $relation = $model->ownerOrganization();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Organization::class);
});

it('belongs to a borrower organization', function () {
    $model = new SensorShare;
    $relation = $model->borrowerOrganization();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Organization::class);
});

it('belongs to a creator', function () {
    $model = new SensorShare;
    $relation = $model->createdBy();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(User::class);
});

it('has many assignments', function () {
    $model = new SensorShare;
    $relation = $model->assignments();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Assignment::class);
});

it('has factory', function () {
    expect(SensorShare::factory())->toBeInstanceOf(Factory::class);
});

it('casts fields correctly', function () {
    $model = new SensorShare;
    $casts = $model->getCasts();

    expect($casts)->toHaveKey('starts_at', 'datetime')
        ->toHaveKey('ends_at', 'datetime');
});
