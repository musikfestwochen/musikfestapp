<?php

declare(strict_types=1);

namespace App\Services\Peoplecount;

use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Facades\Log;

class IntervalCountService
{
    /**
     * Receive and process interval count data for a sensor.
     *
     * @param  Sensor  $sensor  The sensor to process data for.
     * @param  array<mixed>  $data  The data to process, expected to be in a specific format.
     * @return int number of persisted IntervalCount records
     *
     * @throws Exception if the data does not match expected structure or values.
     */
    public function processIntervalCount(Sensor $sensor, array $data): int
    {
        // parse the data depending on the sensor type
        switch ($sensor->vendor) {
            case 'Axis':
                return $this->processAxisIntervalCount($sensor, $data);

            default:
                // Handle other vendors or throw an exception
                throw new Exception('Unsupported sensor vendor: '.$sensor->vendor);
        }
    }

    /**
     * Process interval count data specifically for Axis sensors.
     *
     * @param  Sensor  $sensor  The Axis sensor to process data for.
     * @param  array<mixed>  $data  The data to process, expected to be in Axis format.
     * @return int number of persisted IntervalCount
     *
     * @throws Exception if the data does not match expected structure or values.
     */
    public function processAxisIntervalCount(Sensor $sensor, array $data): int
    {
        $timezone = (string) config('app.timezone');

        // Validate API name and version
        throw_if(($data['apiName'] ?? null) !== 'Axis Retail Data' || ($data['apiVersion'] ?? null) !== '0.4', Exception::class, 'Unsupported Axis API version or name.');

        // Validate sensor serial
        $providedSerial = $data['sensor']['serial'] ?? 'missing';
        throw_if($providedSerial !== $sensor->serial, Exception::class, 'Sensor serial mismatch: expected '.$sensor->serial.', got '.$providedSerial);

        // Get measurements array
        $measurements = $data['data']['measurements'] ?? [];
        throw_if(! is_array($measurements), Exception::class, 'Invalid Axis data structure: measurements must be an array.');

        $numPersisted = 0;
        $receivedAt = CarbonImmutable::now($timezone);

        // Process each measurement
        foreach ($measurements as $measurement) {
            // Only process people-counts measurements, dismiss all others
            if (($measurement['kind'] ?? null) !== 'people-counts') {
                continue;
            }

            // Validate measurement-level timestamps
            throw_if(! isset($measurement['utcFrom']) || ! isset($measurement['utcTo']), Exception::class, 'Missing required UTC timestamps in measurement data.');

            $tsFrom = $this->parseToAppTimezone($measurement['utcFrom'], $timezone, 'utcFrom');
            $tsTo = $this->parseToAppTimezone($measurement['utcTo'], $timezone, 'utcTo');
            $items = $measurement['items'] ?? [];

            // Extract counts using helper
            $counts = $this->extractCountsFromItems($items);
            $countIn = $counts['countIn'];
            $countOut = $counts['countOut'];

            // Create new IntervalCount
            IntervalCount::query()->create([
                'ts_from' => $tsFrom,
                'ts_to' => $tsTo,
                'received_at' => $receivedAt,
                'count_in' => $countIn,
                'count_out' => $countOut,
                'sensor_id' => $sensor->id,
            ]);

            $this->warnIfLateArrival($sensor, $tsTo, $receivedAt);

            $numPersisted++;
        }

        // Return the number of persisted IntervalCount records
        return $numPersisted;
    }

    /**
     * Extracts the in/out counts from Axis measurement items.
     *
     * @param  array<mixed>  $items  The items from the Axis measurement.
     * @return array{countIn: int, countOut: int}
     */
    protected function extractCountsFromItems(array $items): array
    {
        $countIn = 0;
        $countOut = 0;
        foreach ($items as $item) {
            $direction = $item['direction'] ?? null;
            if ($direction === 'in') {
                $countIn = $item['count'] ?? 0;
            } elseif ($direction === 'out') {
                $countOut = $item['count'] ?? 0;
            }
        }

        return ['countIn' => $countIn, 'countOut' => $countOut];
    }

    /**
     * @throws Exception
     */
    protected function parseToAppTimezone(mixed $timestamp, string $timezone, string $field): CarbonImmutable
    {
        if (! is_string($timestamp) || trim($timestamp) === '') {
            throw new Exception(sprintf('Invalid %s timestamp in measurement data.', $field));
        }

        if (! $this->hasExplicitTimezone($timestamp)) {
            throw new Exception(sprintf('Invalid %s timestamp in measurement data.', $field));
        }

        try {
            return CarbonImmutable::parse($timestamp, 'UTC')->setTimezone($timezone);
        } catch (\Throwable $throwable) {
            throw new Exception(sprintf('Invalid %s timestamp in measurement data.', $field), $throwable->getCode(), previous: $throwable);
        }
    }

    protected function hasExplicitTimezone(string $timestamp): bool
    {
        $trimmed = trim($timestamp);

        return (bool) preg_match('/(Z|[+\-]\d{2}:?\d{2})$/', $trimmed);
    }

    protected function warnIfLateArrival(Sensor $sensor, CarbonImmutable $tsTo, CarbonImmutable $receivedAt): void
    {
        if (! $receivedAt->greaterThan($tsTo->addMinute())) {
            return;
        }

        Log::warning('Late interval count arrival detected.', [
            'sensor_id' => $sensor->id,
            'sensor_serial' => $sensor->serial,
            'ts_to' => $tsTo->toIso8601String(),
            'received_at' => $receivedAt->toIso8601String(),
            'delay_seconds' => $tsTo->diffInSeconds($receivedAt),
        ]);
    }
}
