<?php

use App\Http\Requests\StageSafety\ReadingStoreRequest;
use App\Models\StageSafety\Sensor;
use Illuminate\Validation\Rules\In;
use Laravel\Sanctum\PersonalAccessToken;

covers(ReadingStoreRequest::class);

it('defines the BW-WSS envelope and payload rules', function () {
    $rules = (new ReadingStoreRequest)->rules();

    expect($rules)->toHaveKeys([
        'schema_version',
        'sensor_identifier',
        'observed_at',
        'payload',
        'payload.kind',
        'payload.value',
        'payload.unit',
        'payload.window_seconds',
        'payload.battery_low',
        'payload.rssi_dbm',
        'payload.cv',
    ])->and($rules['schema_version'][2])->toBeInstanceOf(In::class)
        ->and($rules['sensor_identifier'])->toBe(['required', 'string', 'regex:/\A[0-9A-F]{6}\z/'])
        ->and($rules['payload.kind'][2])->toBeInstanceOf(In::class)
        ->and($rules['payload.value'])->toBe(['required', 'numeric:strict', 'min:0'])
        ->and($rules['payload.unit'][2])->toBeInstanceOf(In::class)
        ->and($rules['payload.window_seconds'])->toBe(['required', 'integer', 'min:0'])
        ->and($rules['payload.battery_low'])->toBe(['required', 'boolean:strict']);
});

it('authorizes only active Stage Safety sensors with full token ability', function (object $principal, bool $expected) {
    $request = new ReadingStoreRequest;
    $request->setUserResolver(fn (?string $guard = null): object => $principal);

    expect($request->authorize())->toBe($expected);
})->with([
    'active full token' => fn (): array => [stageSafetyRequestSensor(null, ['*']), true],
    'archived sensor' => fn (): array => [stageSafetyRequestSensor(now(), ['*']), false],
    'limited token' => fn (): array => [stageSafetyRequestSensor(null, ['readings:write']), false],
    'wrong principal' => fn (): array => [new stdClass, false],
]);

function stageSafetyRequestSensor(mixed $archivedAt, array $abilities): Sensor
{
    $token = new PersonalAccessToken;
    $token->abilities = $abilities;

    $sensor = new Sensor;
    $sensor->archived_at = $archivedAt;

    return $sensor->withAccessToken($token);
}
