<?php

use App\Casts\BinaryHexCast;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

covers(AreaAggregatedCount::class);

it('has correct fillable attributes', function () {
    $model = new AreaAggregatedCount;
    expect($model->getFillable())->toEqualCanonicalizing([
        'area_id',
        'count',
        'from',
        'to',
        'checksum',
    ]);
});

it('has correct table name', function () {
    $model = new AreaAggregatedCount;
    expect($model->getTable())->toBe('peoplecount_area_aggregated_counts');
});

it('does not use timestamps', function () {
    $model = new AreaAggregatedCount;
    expect($model->timestamps)->toBeFalse();
});

it('belongs to an area', function () {
    $model = new AreaAggregatedCount;
    $relation = $model->area();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Area::class);
});

it('casts fields correctly', function () {
    $model = new AreaAggregatedCount;
    $casts = $model->getCasts();

    expect($casts)->toHaveKey('from', 'datetime');
    expect($casts)->toHaveKey('to', 'datetime');
    expect($casts)->toHaveKey('checksum', BinaryHexCast::class);
});
