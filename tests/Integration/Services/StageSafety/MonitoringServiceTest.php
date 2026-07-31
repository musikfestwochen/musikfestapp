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

it('returns every fresh sensor with independently resolved fresh readings', function () {
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
        'battery_low' => false,
        'rssi_dbm' => -90,
        'cv' => 70,
    ]);
    Reading::factory()->for($sensor)->create([
        'kind' => ReadingKind::WindGust,
        'value' => 7.25,
        'observed_at' => now()->subMinute(),
        'received_at' => now()->subMinute()->addSeconds(4),
        'window_seconds' => 10,
        'battery_low' => true,
        'rssi_dbm' => -65,
        'cv' => 105,
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
        ->and($payload['sensors'][0]['wind_average'])->toBeNull()
        ->and($payload['sensors'][0]['wind_gust']['value'])->toBe(7.25)
        ->and($payload['sensors'][0]['wind_gust']['window_seconds'])->toBe(10)
        ->and($payload['sensors'][0]['wind_gust']['receipt_delay_seconds'])->toBe(4)
        ->and($payload['sensors'][0]['radio_diagnostics'])->toBe([
            'battery_low' => true,
            'rssi_dbm' => -65,
            'cv' => 105,
        ]);
});

it('omits a stale gust while keeping a fresh average for the same sensor', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->for($organization)->create(['stale_after_seconds' => 300]);

    Reading::factory()->for($sensor)->create([
        'kind' => ReadingKind::WindGust,
        'value' => 8.2,
        'observed_at' => now()->subSeconds(301),
        'received_at' => now()->subSeconds(300),
    ]);
    Reading::factory()->for($sensor)->create([
        'kind' => ReadingKind::WindAverage,
        'value' => 4.1,
        'observed_at' => now()->subMinute(),
        'received_at' => now()->subMinute()->addSecond(),
    ]);

    $currentSensor = $this->service->currentWind($organization)['sensors'][0];

    expect($currentSensor['wind_gust'])->toBeNull()
        ->and($currentSensor['wind_average']['value'])->toBe(4.1)
        ->and($currentSensor['latest_observed_at'])->toBe('2026-07-25T11:59:00+00:00');
});

it('returns null radio diagnostics when a sensor has no readings', function () {
    $sensor = Sensor::factory()->create();
    $method = new ReflectionMethod($this->service, 'currentSensorPayload');

    /** @var array<string, mixed> $payload */
    $payload = $method->invoke($this->service, $sensor, now());

    expect($payload['radio_diagnostics'])->toBeNull();
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

it('returns ordered clamped LQI percentages from complete radio diagnostics', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->for($organization)->create();
    $archivedSensor = Sensor::factory()->for($organization)->create(['archived_at' => now()]);
    $foreignSensor = Sensor::factory()->create();

    foreach ([
        ['minutes' => 30, 'rssi_dbm' => -98, 'cv' => 0],
        ['minutes' => 20, 'rssi_dbm' => -90, 'cv' => 97],
        ['minutes' => 10, 'rssi_dbm' => -30, 'cv' => 127],
    ] as $sample) {
        Reading::factory()->for($sensor)->create([
            'observed_at' => now()->subMinutes($sample['minutes']),
            'received_at' => now()->subMinutes($sample['minutes']),
            'rssi_dbm' => $sample['rssi_dbm'],
            'cv' => $sample['cv'],
        ]);
    }

    Reading::factory()->for($sensor)->create([
        'observed_at' => now()->subMinutes(5),
        'received_at' => now()->subMinutes(5),
        'rssi_dbm' => -70,
        'cv' => null,
    ]);
    Reading::factory()->for($sensor)->create([
        'observed_at' => now()->subHours(2),
        'received_at' => now()->subHours(2),
        'rssi_dbm' => -70,
        'cv' => 100,
    ]);
    Reading::factory()->for($archivedSensor)->create(['rssi_dbm' => -70, 'cv' => 100]);
    Reading::factory()->for($foreignSensor)->create(['rssi_dbm' => -70, 'cv' => 100]);

    $payload = $this->service->lqiHistory($organization, now()->subHour(), now());

    expect($payload['sensors'])->toHaveCount(1)
        ->and($payload['sensors'][0]['sensor']['id'])->toBe($sensor->id)
        ->and($payload['sensors'][0]['samples'])->toHaveCount(3)
        ->and($payload['sensors'][0]['samples'][0]['lqi_percent'])->toBe(0.0)
        ->and($payload['sensors'][0]['samples'][1]['lqi_percent'])->toEqualWithDelta(50.8974358974, 0.000001)
        ->and($payload['sensors'][0]['samples'][2]['lqi_percent'])->toBe(100.0);
});
