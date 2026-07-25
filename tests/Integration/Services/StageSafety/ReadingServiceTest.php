<?php

use App\Models\StageSafety\Reading;
use App\Models\StageSafety\Sensor;
use App\Services\StageSafety\ReadingService;
use Illuminate\Validation\ValidationException;

covers(ReadingService::class);

it('persists a supported wind reading', function () {
    $sensor = Sensor::factory()->create();

    (new ReadingService)->process($sensor, [
        'sensor_identifier' => $sensor->identifier,
        'observed_at' => '2026-07-23T12:00:00Z',
        'payload' => [
            'kind' => 'wind_average',
            'value' => 2.5,
            'unit' => 'm/s',
            'window_seconds' => 60,
            'battery_low' => false,
        ],
    ]);

    $reading = Reading::query()->sole();

    expect($reading->sensor_id)->toBe($sensor->id)
        ->and($reading->value)->toBe(2.5)
        ->and($reading->unit)->toBe('m/s');
});

it('rejects unsupported sensor models', function () {
    $sensor = Sensor::factory()->create(['model' => 'unsupported']);

    expect(fn () => (new ReadingService)->process($sensor, []))
        ->toThrow(ValidationException::class, 'The authenticated sensor model is not supported.');
});

it('rejects a mismatched sensor identifier', function () {
    $sensor = Sensor::factory()->create(['identifier' => 'DEF456']);

    expect(fn () => (new ReadingService)->process($sensor, ['sensor_identifier' => 'ABC123']))
        ->toThrow(ValidationException::class, 'The sensor identifier does not match the authenticated sensor.');
});
