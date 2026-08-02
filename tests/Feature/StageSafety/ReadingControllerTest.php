<?php

use App\Enums\StageSafety\ReadingKind;
use App\Models\Organization;
use App\Models\Peoplecount\Sensor as PeoplecountSensor;
use App\Models\StageSafety\Reading;
use App\Models\StageSafety\Sensor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Factory;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function stageSafetyReadingPayload(array $overrides = [], string $sensorIdentifier = 'ABC123'): array
{
    return array_replace_recursive([
        'schema_version' => 1,
        'sensor_identifier' => $sensorIdentifier,
        'observed_at' => '2026-07-23T12:00:00Z',
        'payload' => [
            'kind' => 'wind_gust',
            'value' => 2.6744547,
            'unit' => 'm/s',
            'window_seconds' => 10,
            'battery_low' => false,
            'rssi_dbm' => -70,
            'cv' => 103,
        ],
    ], $overrides);
}

function stageSafetyReadingToken(Sensor $sensor, array $abilities = ['*']): string
{
    return $sensor->createToken('stage-safety-reading-test', $abilities)->plainTextToken;
}

function stageSafetyReadingHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

it('persists a token-bound m/s reading with client and server timestamps', function () {
    CarbonImmutable::setTestNow('2026-07-23T12:01:00Z');
    $sensor = Sensor::factory()->create();

    $this->postJson(
        route('stage-safety.readings.store'),
        stageSafetyReadingPayload(['observed_at' => '2026-07-23T14:00:00+02:00'], $sensor->identifier),
        stageSafetyReadingHeaders(stageSafetyReadingToken($sensor)),
    )->assertOk()->assertExactJson([
        'message' => 'Wind readings processed successfully.',
        'count' => 1,
    ]);

    $reading = Reading::query()->sole();

    expect($reading->sensor->is($sensor))->toBeTrue()
        ->and($sensor->readings()->sole()->is($reading))->toBeTrue()
        ->and($reading->kind)->toBe(ReadingKind::WindGust)
        ->and($reading->value)->toBe(2.6744547)
        ->and($reading->unit)->toBe('m/s')
        ->and($reading->observed_at->format('Y-m-d H:i:s'))->toBe('2026-07-23 12:00:00')
        ->and($reading->received_at->format('Y-m-d H:i:s'))->toBe('2026-07-23 12:01:00')
        ->and($reading->window_seconds)->toBe(10)
        ->and($reading->battery_low)->toBeFalse()
        ->and($reading->rssi_dbm)->toBe(-70)
        ->and($reading->cv)->toBe(103);
});

it('rejects extra identity and payload fields', function () {
    $authenticatedSensor = Sensor::factory()->create();
    $otherSensor = Sensor::factory()->for(Organization::factory())->create();
    $payload = stageSafetyReadingPayload([
        'sensor_id' => $otherSensor->id,
        'organization_id' => $otherSensor->organization_id,
        'ignored' => 'value',
        'payload' => [
            'sensor_id' => $otherSensor->id,
            'organization_id' => $otherSensor->organization_id,
            'ignored' => 'value',
        ],
    ], $authenticatedSensor->identifier);

    $this->postJson(
        route('stage-safety.readings.store'),
        $payload,
        stageSafetyReadingHeaders(stageSafetyReadingToken($authenticatedSensor)),
    )->assertUnprocessable()
        ->assertJsonValidationErrors(['sensor_id', 'organization_id', 'ignored']);

    expect(Reading::query()->count())->toBe(0);
});

it('stores average and gust frames independently', function () {
    $sensor = Sensor::factory()->create();
    $headers = stageSafetyReadingHeaders(stageSafetyReadingToken($sensor));

    $this->postJson(route('stage-safety.readings.store'), stageSafetyReadingPayload([
        'payload' => ['kind' => ReadingKind::WindAverage->value, 'window_seconds' => 60],
    ], $sensor->identifier), $headers)->assertOk();
    $this->postJson(route('stage-safety.readings.store'), stageSafetyReadingPayload(sensorIdentifier: $sensor->identifier), $headers)->assertOk();

    expect($sensor->readings()->count())->toBe(2)
        ->and($sensor->readings()->pluck('kind')->all())
        ->toEqualCanonicalizing([ReadingKind::WindAverage, ReadingKind::WindGust]);
});

it('allows measurement-specific metadata to be absent in shared persistence', function () {
    $reading = Reading::factory()->create([
        'window_seconds' => null,
        'battery_low' => null,
    ]);

    expect($reading->window_seconds)->toBeNull()
        ->and($reading->battery_low)->toBeNull();
});

it('idempotently updates a replayed frame', function () {
    CarbonImmutable::setTestNow('2026-07-23T12:01:00Z');
    $sensor = Sensor::factory()->create();
    $headers = stageSafetyReadingHeaders(stageSafetyReadingToken($sensor));

    $this->postJson(route('stage-safety.readings.store'), stageSafetyReadingPayload(sensorIdentifier: $sensor->identifier), $headers)->assertOk();

    CarbonImmutable::setTestNow('2026-07-23T12:02:00Z');
    $replay = stageSafetyReadingPayload([
        'payload' => [
            'value' => 3.5,
            'window_seconds' => 20,
            'battery_low' => true,
            'rssi_dbm' => null,
            'cv' => null,
        ],
    ], $sensor->identifier);
    unset($replay['payload']['rssi_dbm'], $replay['payload']['cv']);

    $this->postJson(route('stage-safety.readings.store'), $replay, $headers)->assertOk();

    $reading = Reading::query()->sole();

    expect($reading->value)->toBe(3.5)
        ->and($reading->window_seconds)->toBe(20)
        ->and($reading->battery_low)->toBeTrue()
        ->and($reading->rssi_dbm)->toBeNull()
        ->and($reading->cv)->toBeNull()
        ->and($reading->received_at->format('Y-m-d H:i:s'))->toBe('2026-07-23 12:02:00');
});

it('keeps identical frame identities separate across sensors', function () {
    $firstSensor = Sensor::factory()->create();
    $secondSensor = Sensor::factory()->create();

    foreach ([$firstSensor, $secondSensor] as $sensor) {
        $this->postJson(
            route('stage-safety.readings.store'),
            stageSafetyReadingPayload(sensorIdentifier: $sensor->identifier),
            stageSafetyReadingHeaders(stageSafetyReadingToken($sensor)),
        )->assertOk();
        $this->app->make(Factory::class)->forgetGuards();
    }

    expect(Reading::query()->count())->toBe(2);
});

it('rejects missing and invalid authentication', function () {
    $this->postJson(route('stage-safety.readings.store'), stageSafetyReadingPayload())
        ->assertUnauthorized();
    $this->postJson(
        route('stage-safety.readings.store'),
        stageSafetyReadingPayload(),
        stageSafetyReadingHeaders('invalid-token'),
    )->assertUnauthorized();

    expect(Reading::query()->exists())->toBeFalse();
});

it('rejects non-Stage-Safety principals', function () {
    $peoplecountSensor = PeoplecountSensor::factory()->create();
    $user = User::factory()->create();

    $this->postJson(
        route('stage-safety.readings.store'),
        stageSafetyReadingPayload(),
        stageSafetyReadingHeaders($peoplecountSensor->createToken('test')->plainTextToken),
    )->assertForbidden();
    $this->app->make(Factory::class)->forgetGuards();
    $this->actingAs($user)
        ->postJson(route('stage-safety.readings.store'), stageSafetyReadingPayload())
        ->assertForbidden();

    expect(Reading::query()->exists())->toBeFalse();
});

it('rejects archived sensors and tokens without full ability', function () {
    $archivedSensor = Sensor::factory()->create();
    $archivedToken = stageSafetyReadingToken($archivedSensor);
    $archivedSensor->update(['archived_at' => now()]);
    $limitedSensor = Sensor::factory()->create();

    $this->postJson(
        route('stage-safety.readings.store'),
        stageSafetyReadingPayload(sensorIdentifier: $archivedSensor->identifier),
        stageSafetyReadingHeaders($archivedToken),
    )->assertForbidden();
    $this->app->make(Factory::class)->forgetGuards();
    $this->postJson(
        route('stage-safety.readings.store'),
        stageSafetyReadingPayload(sensorIdentifier: $limitedSensor->identifier),
        stageSafetyReadingHeaders(stageSafetyReadingToken($limitedSensor, ['readings:write'])),
    )->assertForbidden();

    expect(Reading::query()->exists())->toBeFalse();
});

it('rejects unsupported authenticated sensor models', function () {
    $sensor = Sensor::factory()->create(['model' => 'unsupported']);

    $this->postJson(
        route('stage-safety.readings.store'),
        stageSafetyReadingPayload(sensorIdentifier: $sensor->identifier),
        stageSafetyReadingHeaders(stageSafetyReadingToken($sensor)),
    )->assertUnprocessable()->assertJsonValidationErrors('sensor');
});

it('rejects an identifier that does not match the token-bound sensor', function () {
    $sensor = Sensor::factory()->create(['identifier' => 'DEF456']);

    $this->postJson(
        route('stage-safety.readings.store'),
        stageSafetyReadingPayload(sensorIdentifier: 'ABC123'),
        stageSafetyReadingHeaders(stageSafetyReadingToken($sensor)),
    )->assertUnprocessable()->assertJsonValidationErrors('sensor_identifier');

    expect(Reading::query()->exists())->toBeFalse();
});

it('rejects malformed reading fields', function (string $field, mixed $value) {
    $sensor = Sensor::factory()->create();
    $payload = stageSafetyReadingPayload(sensorIdentifier: $sensor->identifier);
    data_set($payload, $field, $value);

    $this->postJson(
        route('stage-safety.readings.store'),
        $payload,
        stageSafetyReadingHeaders(stageSafetyReadingToken($sensor)),
    )->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(Reading::query()->exists())->toBeFalse();
})->with([
    'schema version' => ['schema_version', 2],
    'sensor identifier' => ['sensor_identifier', 'abc123'],
    'timestamp without timezone' => ['observed_at', '2026-07-23 12:00:00'],
    'human-readable timestamp' => ['observed_at', 'July 23 2026 12:00:00Z'],
    'timestamp offset without colon' => ['observed_at', '2026-07-23T12:00:00+0200'],
    'invalid timestamp' => ['observed_at', 'not-a-dateZ'],
    'fractional timestamp' => ['observed_at', '2026-07-23T12:00:00.123Z'],
    'reading kind' => ['payload.kind', 'peak'],
    'numeric string' => ['payload.value', '2.5'],
    'negative value' => ['payload.value', -0.1],
    'non-canonical unit' => ['payload.unit', 'km/h'],
    'negative window' => ['payload.window_seconds', -1],
    'non-integer window' => ['payload.window_seconds', 1.5],
    'non-boolean battery state' => ['payload.battery_low', 1],
    'non-integer RSSI' => ['payload.rssi_dbm', -70.5],
    'negative CV' => ['payload.cv', -1],
]);

it('applies standard route throttling', function () {
    $sensor = Sensor::factory()->create();
    $headers = stageSafetyReadingHeaders(stageSafetyReadingToken($sensor));

    foreach (range(1, 60) as $request) {
        $this->postJson(route('stage-safety.readings.store'), stageSafetyReadingPayload([
            'observed_at' => sprintf('2026-07-23T12:00:%02dZ', $request - 1),
        ], $sensor->identifier), $headers)->assertOk();
    }

    $this->postJson(route('stage-safety.readings.store'), stageSafetyReadingPayload(sensorIdentifier: $sensor->identifier), $headers)
        ->assertTooManyRequests();
});

it('gives each sensor token an independent rate limit', function () {
    $firstSensor = Sensor::factory()->create();
    $secondSensor = Sensor::factory()->create();

    $this->postJson(
        route('stage-safety.readings.store'),
        stageSafetyReadingPayload(sensorIdentifier: $firstSensor->identifier),
        stageSafetyReadingHeaders(stageSafetyReadingToken($firstSensor)),
    )->assertOk()->assertHeader('X-RateLimit-Remaining', '59');

    $this->app->make(Factory::class)->forgetGuards();

    $this->postJson(
        route('stage-safety.readings.store'),
        stageSafetyReadingPayload(sensorIdentifier: $secondSensor->identifier),
        stageSafetyReadingHeaders(stageSafetyReadingToken($secondSensor)),
    )->assertOk()->assertHeader('X-RateLimit-Remaining', '59');
});

it('uses authentication and standard throttle middleware', function () {
    test()->assertRouteUsesMiddleware(
        'stage-safety.readings.store',
        ['auth:sanctum', 'throttle:stage-safety-readings'],
    );
});
