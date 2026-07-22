<?php

use App\Models\StageSafety\Sensor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

covers(Sensor::class);

it('uses the Stage Safety sensors table', function () {
    expect((new Sensor)->getTable())->toBe('stage_safety_sensors');
});

it('has the expected fillable attributes and defaults', function () {
    $sensor = new Sensor;

    expect($sensor->getFillable())->toEqualCanonicalizing([
        'organization_id',
        'manufacturer',
        'model',
        'serial',
        'name',
        'location',
        'stale_after_seconds',
        'archived_at',
    ])->and($sensor->stale_after_seconds)->toBe(300);
});

it('uses API tokens and soft deletes', function () {
    $traits = class_uses_recursive(new Sensor);

    expect($traits)->toContain(HasApiTokens::class, SoftDeletes::class);
});

it('belongs to an organization', function () {
    expect((new Sensor)->organization())->toBeInstanceOf(BelongsTo::class);
});
