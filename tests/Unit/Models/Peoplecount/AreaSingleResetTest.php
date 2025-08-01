<?php

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaSingleReset;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

covers(AreaSingleReset::class);

it('has correct fillable attributes', function () {
    $model = new AreaSingleReset;
    expect($model->getFillable())->toEqualCanonicalizing([
        'area_id',
        'reset_value',
        'effective_at',
        'created_by',
        'notes',
    ]);
});

it('has correct table name', function () {
    $model = new AreaSingleReset;
    expect($model->getTable())->toBe('peoplecount_area_single_resets');
});

it('uses timestamps', function () {
    $model = new AreaSingleReset;
    expect($model->timestamps)->toBeTrue();
});

it('does not use soft deletes', function () {
    $model = new AreaSingleReset;
    expect(in_array(SoftDeletes::class, class_uses_recursive($model)))->toBeFalse();
});

it('has correct casts', function () {
    $model = new AreaSingleReset;
    $casts = $model->getCasts();

    expect($casts)->toHaveKey('effective_at');
    expect($casts['effective_at'])->toBe('datetime');
});

it('belongs to an area', function () {
    $model = new AreaSingleReset;
    $relation = $model->area();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Area::class);
});

it('belongs to a user (created by)', function () {
    $model = new AreaSingleReset;
    $relation = $model->createdBy();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
    expect($relation->getForeignKeyName())->toBe('created_by');
});

it('has factory', function () {
    expect(AreaSingleReset::factory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Factories\Factory::class);
});
