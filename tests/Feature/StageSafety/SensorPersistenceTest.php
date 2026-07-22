<?php

use App\Enums\StageSafety\SensorType;
use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use Illuminate\Database\QueryException;

it('persists a supported Stage Safety sensor with defaults', function () {
    $sensor = Sensor::factory()->create([
        'name' => null,
        'location' => null,
    ]);

    expect($sensor->manufacturer)->toBe(SensorType::BroadweighBwWss->manufacturer())
        ->and($sensor->model)->toBe(SensorType::BroadweighBwWss->model())
        ->and($sensor->stale_after_seconds)->toBe(300)
        ->and($sensor->name)->toBeNull()
        ->and($sensor->location)->toBeNull()
        ->and($sensor->organization)->toBeInstanceOf(Organization::class);
});

it('prevents duplicate manufacturer and serial identities within an organization', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->for($organization)->create();

    expect(fn () => Sensor::factory()->for($organization)->create([
        'manufacturer' => $sensor->manufacturer,
        'model' => $sensor->model,
        'serial' => $sensor->serial,
    ]))->toThrow(QueryException::class);
});

it('allows the same manufacturer and serial identity in different organizations', function () {
    $sensor = Sensor::factory()->create();
    $otherSensor = Sensor::factory()->create([
        'manufacturer' => $sensor->manufacturer,
        'model' => $sensor->model,
        'serial' => $sensor->serial,
    ]);

    expect($otherSensor->organization_id)->not->toBe($sensor->organization_id);
});
