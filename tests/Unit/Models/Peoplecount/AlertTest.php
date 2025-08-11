<?php

use App\Models\Peoplecount\Alert;
use App\Models\Peoplecount\Area;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

covers(Alert::class);

it('has correct fillable attributes', function () {
    $model = new Alert;
    expect($model->getFillable())->toEqualCanonicalizing([
        'area_id',
        'type',
        'channel',
        'cooldown_seconds',
        'created_by',
        'occupancy_alert_threshold',
    ]);
});

it('has correct table name', function () {
    $model = new Alert;
    expect($model->getTable())->toBe('peoplecount_alerts');
});

it('uses timestamps', function () {
    $model = new Alert;
    expect($model->timestamps)->toBeTrue();
});

it('belongs to an area', function () {
    $model = new Alert;
    $relation = $model->area();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Area::class);
});

it('belongs to a creator (user)', function () {
    $model = new Alert;
    $relation = $model->creator();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
});

it('has many recipients (users) via pivot', function () {
    $model = new Alert;
    $relation = $model->recipients();

    expect($relation)->toBeInstanceOf(BelongsToMany::class);
});

it('has factory', function () {
    expect(Alert::factory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Factories\Factory::class);
});
