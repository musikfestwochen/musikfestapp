<?php

use App\Enums\StageSafety\SensorType;
use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use App\Services\StageSafety\SensorService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

covers(SensorService::class);

beforeEach(function () {
    $this->service = new SensorService;
    $this->organization = Organization::factory()->create();
});

it('lists active or archived sensors for the given organization', function () {
    $activeSensor = Sensor::factory()->for($this->organization)->create(['name' => 'Active']);
    $archivedSensor = Sensor::factory()->for($this->organization)->create([
        'name' => 'Archived',
        'archived_at' => now(),
    ]);
    Sensor::factory()->create();

    $active = $this->service->getSensors($this->organization);
    $archived = $this->service->getSensors($this->organization, onlyArchived: true);

    expect($active)->toBeInstanceOf(Collection::class)
        ->and($active)->toHaveCount(1)
        ->and($active->first()->is($activeSensor))->toBeTrue()
        ->and($archived)->toHaveCount(1)
        ->and($archived->first()->is($archivedSensor))->toBeTrue();
});

it('creates a sensor and returns the token secret', function () {
    $result = $this->service->createWithToken($this->organization, [
        'organization_id' => Organization::factory()->create()->id,
        'manufacturer' => SensorType::BroadweighBwWss->manufacturer(),
        'model' => SensorType::BroadweighBwWss->model(),
        'identifier' => 'FF1234',
        'name' => 'Main Stage Wind',
    ]);

    $accessToken = PersonalAccessToken::findToken($result['token']);

    expect($result['sensor'])->toBeInstanceOf(Sensor::class)
        ->and($result['sensor']->organization_id)->toBe($this->organization->id)
        ->and($result['token'])->not->toContain('|')
        ->and($accessToken)->not->toBeNull()
        ->and($accessToken->tokenable->is($result['sensor']))->toBeTrue()
        ->and($accessToken->name)->toBe(SensorService::SENSOR_TOKEN_NAME)
        ->and($accessToken->token)->toBe(hash('sha256', $result['token']));
});

it('updates sensor attributes', function () {
    $sensor = Sensor::factory()->for($this->organization)->create();

    $updated = $this->service->update($this->organization, $sensor, [
        'name' => 'Updated Wind Sensor',
        'stale_after_seconds' => 600,
    ]);

    expect($updated->organization_id)->toBe($this->organization->id)
        ->and($updated->name)->toBe('Updated Wind Sensor')
        ->and($updated->stale_after_seconds)->toBe(600);
});

it('regenerates the sensor token without deleting differently named tokens', function () {
    $sensor = Sensor::factory()->for($this->organization)->create();
    $oldToken = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;
    $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);
    $legacyToken = $sensor->createToken('legacy-token')->plainTextToken;

    $newToken = $this->service->createOrRegenerateToken($this->organization, $sensor);

    expect(PersonalAccessToken::findToken($oldToken))->toBeNull()
        ->and(PersonalAccessToken::findToken($legacyToken))->not->toBeNull()
        ->and($sensor->tokens()->where('name', SensorService::SENSOR_TOKEN_NAME)->count())->toBe(1)
        ->and(PersonalAccessToken::findToken($newToken))->not->toBeNull();
});

it('rejects token rotation for archived sensors', function () {
    $sensor = Sensor::factory()->for($this->organization)->create(['archived_at' => now()]);

    expect(fn () => $this->service->createOrRegenerateToken($this->organization, $sensor))
        ->toThrow(ValidationException::class, 'Archived sensors cannot receive API tokens.');
});

it('revokes every sensor token', function () {
    $sensor = Sensor::factory()->for($this->organization)->create();
    $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);
    $sensor->createToken('legacy-token');

    $this->service->revokeTokens($this->organization, $sensor);

    expect($sensor->tokens()->count())->toBe(0);
});

it('archives a sensor and revokes every token', function () {
    $sensor = Sensor::factory()->for($this->organization)->create();
    $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);
    $sensor->createToken('legacy-token');

    $archived = $this->service->archive($this->organization, $sensor);

    expect($archived->archived_at)->not->toBeNull()
        ->and($sensor->tokens()->count())->toBe(0);
});

it('restores a sensor without silently issuing a token', function () {
    $sensor = Sensor::factory()->for($this->organization)->create(['archived_at' => now()]);

    $restored = $this->service->restore($this->organization, $sensor);

    expect($restored->archived_at)->toBeNull()
        ->and($sensor->tokens()->count())->toBe(0);
});

it('soft deletes a sensor and revokes every token', function () {
    $sensor = Sensor::factory()->for($this->organization)->create();
    $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);

    $this->service->delete($this->organization, $sensor);

    $this->assertSoftDeleted('stage_safety_sensors', ['id' => $sensor->id]);
    expect($sensor->tokens()->count())->toBe(0);
});

it('rejects management of another organization sensor', function (string $operation) {
    $sensor = Sensor::factory()->create();

    expect(fn () => match ($operation) {
        'update' => $this->service->update($this->organization, $sensor, ['name' => 'Nope']),
        'regenerate' => $this->service->createOrRegenerateToken($this->organization, $sensor),
        'revoke' => $this->service->revokeTokens($this->organization, $sensor),
        'archive' => $this->service->archive($this->organization, $sensor),
        'restore' => $this->service->restore($this->organization, $sensor),
        'delete' => $this->service->delete($this->organization, $sensor),
    })->toThrow(AuthorizationException::class);
})->with(['update', 'regenerate', 'revoke', 'archive', 'restore', 'delete']);

it('reports whether each listed sensor has an active token', function () {
    $sensorWithToken = Sensor::factory()->for($this->organization)->create(['name' => 'A']);
    $sensorWithoutToken = Sensor::factory()->for($this->organization)->create(['name' => 'B']);
    $sensorWithToken->createToken(SensorService::SENSOR_TOKEN_NAME);

    $sensors = $this->service->getSensors($this->organization);

    expect($sensors->firstWhere('id', $sensorWithToken->id)?->has_active_token)->toBeTruthy()
        ->and($sensors->firstWhere('id', $sensorWithoutToken->id)?->has_active_token)->toBeFalsy();
});
