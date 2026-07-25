<?php

use App\Enums\StageSafety\ReadingKind;
use App\Models\StageSafety\Reading;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

covers(Reading::class);

it('defines its table, fillable fields, casts, and sensor relationship', function () {
    $reading = new Reading;

    expect($reading->getTable())->toBe('stage_safety_readings')
        ->and($reading->usesTimestamps())->toBeFalse()
        ->and($reading->getFillable())->toEqualCanonicalizing([
            'sensor_id',
            'kind',
            'value',
            'unit',
            'observed_at',
            'received_at',
            'window_seconds',
            'battery_low',
            'rssi_dbm',
            'cv',
        ])
        ->and($reading->getCasts())->toMatchArray([
            'kind' => ReadingKind::class,
            'value' => 'float',
            'observed_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'window_seconds' => 'integer',
            'battery_low' => 'boolean',
            'rssi_dbm' => 'integer',
            'cv' => 'integer',
        ])
        ->and($reading->sensor())->toBeInstanceOf(BelongsTo::class);
});
