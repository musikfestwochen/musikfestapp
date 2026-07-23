<?php

use App\Enums\StageSafety\SensorType;

it('defines the Broadweigh BW-WSS sensor type', function () {
    $sensorType = SensorType::BroadweighBwWss;

    expect($sensorType->manufacturer())->toBe('broadweigh')
        ->and($sensorType->model())->toBe('BW-WSS')
        ->and($sensorType->displayName())->toBe('BroadWeigh BW-WSS');
});

it('resolves only supported manufacturer and model pairs', function () {
    expect(SensorType::fromPair('broadweigh', 'BW-WSS'))->toBe(SensorType::BroadweighBwWss)
        ->and(SensorType::fromPair('broadweigh', 'unsupported'))->toBeNull()
        ->and(SensorType::fromPair('unsupported', 'BW-WSS'))->toBeNull();
});
