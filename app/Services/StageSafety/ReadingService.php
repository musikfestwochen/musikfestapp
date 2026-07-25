<?php

declare(strict_types=1);

namespace App\Services\StageSafety;

use App\Enums\StageSafety\SensorType;
use App\Models\StageSafety\Reading;
use App\Models\StageSafety\Sensor;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ReadingService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function process(Sensor $sensor, array $data): void
    {
        if (SensorType::fromPair($sensor->manufacturer, $sensor->model) !== SensorType::BroadweighBwWss) {
            throw ValidationException::withMessages([
                'sensor' => ['The authenticated sensor model is not supported.'],
            ]);
        }

        if ($data['sensor_identifier'] !== $sensor->identifier) {
            throw ValidationException::withMessages([
                'sensor_identifier' => ['The sensor identifier does not match the authenticated sensor.'],
            ]);
        }

        /** @var array<string, mixed> $payload */
        $payload = $data['payload'];
        $observedAt = CarbonImmutable::parse((string) $data['observed_at'])->utc();
        $receivedAt = CarbonImmutable::now('UTC');

        Reading::query()->upsert([
            [
                'sensor_id' => $sensor->id,
                'kind' => $payload['kind'],
                'value' => $payload['value'],
                'unit' => $payload['unit'],
                'observed_at' => $observedAt->format('Y-m-d H:i:s'),
                'received_at' => $receivedAt->format('Y-m-d H:i:s'),
                'window_seconds' => $payload['window_seconds'],
                'battery_low' => $payload['battery_low'],
                'rssi_dbm' => $payload['rssi_dbm'] ?? null,
                'cv' => $payload['cv'] ?? null,
            ],
        ], ['sensor_id', 'kind', 'observed_at'], [
            'value',
            'unit',
            'received_at',
            'window_seconds',
            'battery_low',
            'rssi_dbm',
            'cv',
        ]);
    }
}
