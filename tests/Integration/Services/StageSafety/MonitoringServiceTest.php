<?php

use App\Enums\StageSafety\ReadingKind;
use App\Enums\StageSafety\SensorHealthStatus;
use App\Models\Organization;
use App\Models\StageSafety\Reading;
use App\Models\StageSafety\Sensor;
use App\Services\StageSafety\MonitoringService;
use Illuminate\Support\Carbon;

covers(MonitoringService::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-25 12:00:00 UTC');
    $this->service = new MonitoringService;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns every fresh sensor with independently resolved latest readings', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->for($organization)->create([
        'name' => 'Main Stage',
        'stale_after_seconds' => 300,
    ]);

    Reading::factory()->for($sensor)->create([
        'kind' => ReadingKind::WindAverage,
        'value' => 3.2,
        'observed_at' => now()->subMinutes(7),
        'received_at' => now()->subMinutes(7)->addSeconds(2),
    ]);
    Reading::factory()->for($sensor)->create([
        'kind' => ReadingKind::WindAverage,
        'value' => 4.5,
        'observed_at' => now()->subMinutes(6),
        'received_at' => now()->subMinutes(6)->addSeconds(3),
    ]);
    Reading::factory()->for($sensor)->create([
        'kind' => ReadingKind::WindGust,
        'value' => 7.25,
        'observed_at' => now()->subMinute(),
        'received_at' => now()->subMinute()->addSeconds(4),
        'window_seconds' => 10,
    ]);

    $staleSensor = Sensor::factory()->for($organization)->create(['stale_after_seconds' => 300]);
    Reading::factory()->for($staleSensor)->create([
        'observed_at' => now()->subSeconds(301),
        'received_at' => now()->subSeconds(300),
    ]);

    $archivedSensor = Sensor::factory()->for($organization)->create(['archived_at' => now()]);
    Reading::factory()->for($archivedSensor)->create([
        'observed_at' => now(),
        'received_at' => now(),
    ]);

    $foreignSensor = Sensor::factory()->create();
    Reading::factory()->for($foreignSensor)->create([
        'observed_at' => now(),
        'received_at' => now(),
    ]);

    $payload = $this->service->currentWind($organization);

    expect($payload['generated_at'])->toBe('2026-07-25T12:00:00+00:00')
        ->and($payload['sensors'])->toHaveCount(1)
        ->and($payload['sensors'][0]['sensor']['id'])->toBe($sensor->id)
        ->and($payload['sensors'][0]['status'])->toBe('fresh')
        ->and($payload['sensors'][0]['wind_average']['value'])->toBe(4.5)
        ->and($payload['sensors'][0]['wind_average']['status'])->toBe('stale')
        ->and($payload['sensors'][0]['wind_average']['receipt_delay_seconds'])->toBe(3)
        ->and($payload['sensors'][0]['wind_gust']['value'])->toBe(7.25)
        ->and($payload['sensors'][0]['wind_gust']['window_seconds'])->toBe(10);
});

it('classifies sensor health at the stale boundary and excludes archived sensors', function () {
    $organization = Organization::factory()->create();
    $freshSensor = Sensor::factory()->for($organization)->create(['stale_after_seconds' => 300]);
    $staleSensor = Sensor::factory()->for($organization)->create(['stale_after_seconds' => 300]);
    $neverSeenSensor = Sensor::factory()->for($organization)->create();
    $archivedSensor = Sensor::factory()->for($organization)->create(['archived_at' => now()]);

    Reading::factory()->for($freshSensor)->create([
        'observed_at' => now()->subSeconds(300),
        'received_at' => now()->subSeconds(299),
    ]);
    Reading::factory()->for($staleSensor)->create([
        'observed_at' => now()->subSeconds(301),
        'received_at' => now()->subSeconds(300),
    ]);

    $payload = $this->service->sensorHealth($organization);

    expect($payload['total'])->toBe(3)
        ->and($payload['all_fresh'])->toBeFalse()
        ->and($payload['fresh'])->toHaveCount(1)
        ->and($payload['fresh'][0]['id'])->toBe($freshSensor->id)
        ->and($payload['stale'])->toHaveCount(1)
        ->and($payload['stale'][0]['id'])->toBe($staleSensor->id)
        ->and($payload['never_seen'])->toHaveCount(1)
        ->and($payload['never_seen'][0]['id'])->toBe($neverSeenSensor->id)
        ->and($this->service->status($archivedSensor))->toBe(SensorHealthStatus::Archived);
});

it('does not treat a future observation as current', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->for($organization)->create(['stale_after_seconds' => 300]);

    Reading::factory()->for($sensor)->create([
        'observed_at' => now()->addHour(),
        'received_at' => now(),
    ]);

    expect($this->service->status($sensor))->toBe(SensorHealthStatus::Stale)
        ->and($this->service->currentWind($organization)['sensors'])->toBe([]);
});

it('uses the newer average observation as latest sensor activity', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->for($organization)->create();

    Reading::factory()->for($sensor)->create([
        'kind' => ReadingKind::WindAverage,
        'observed_at' => now()->subMinute(),
        'received_at' => now()->subMinute(),
    ]);
    Reading::factory()->for($sensor)->create([
        'kind' => ReadingKind::WindGust,
        'observed_at' => now()->subMinutes(2),
        'received_at' => now()->subMinutes(2),
    ]);

    expect($this->service->sensorHealth($organization)['fresh'][0]['latest_observed_at'])
        ->toBe('2026-07-25T11:59:00+00:00');
});

it('returns bounded ordered history without archived or foreign sensors', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->for($organization)->create();
    $emptySensor = Sensor::factory()->for($organization)->create();
    $archivedSensor = Sensor::factory()->for($organization)->create(['archived_at' => now()]);
    $foreignSensor = Sensor::factory()->create();

    Reading::factory()->for($sensor)->create([
        'kind' => ReadingKind::WindGust,
        'value' => 8.0,
        'observed_at' => now()->subMinutes(10),
        'received_at' => now()->subMinutes(10),
    ]);
    Reading::factory()->for($sensor)->create([
        'kind' => ReadingKind::WindAverage,
        'value' => 4.0,
        'observed_at' => now()->subMinutes(20),
        'received_at' => now()->subMinutes(20),
    ]);
    Reading::factory()->for($sensor)->create([
        'value' => 99.0,
        'observed_at' => now()->subHours(2),
        'received_at' => now()->subHours(2),
    ]);
    Reading::factory()->for($archivedSensor)->create([
        'observed_at' => now()->subMinutes(5),
        'received_at' => now()->subMinutes(5),
    ]);
    Reading::factory()->for($foreignSensor)->create([
        'observed_at' => now()->subMinutes(5),
        'received_at' => now()->subMinutes(5),
    ]);

    $payload = $this->service->windHistory($organization, now()->subHour(), now());

    expect($payload['sensors'])->toHaveCount(1)
        ->and($payload['sensors'][0]['sensor']['id'])->toBe($sensor->id)
        ->and($payload['sensors'][0]['readings'])->toHaveCount(2)
        ->and($payload['sensors'][0]['readings'][0]['value'])->toBe(4.0)
        ->and($payload['sensors'][0]['readings'][1]['value'])->toBe(8.0)
        ->and($emptySensor->exists)->toBeTrue();
});
