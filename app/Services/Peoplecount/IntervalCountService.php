<?php

namespace App\Services\Peoplecount;

use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use Exception;

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
        // Validate API name and version
        throw_if(($data['apiName'] ?? null) !== 'Axis Retail Data' || ($data['apiVersion'] ?? null) !== '0.4', Exception::class, 'Unsupported Axis API version or name.');

        // Validate sensor serial
        throw_if(($data['sensor']['serial'] ?? null) !== $sensor->serial, Exception::class, 'Sensor serial mismatch: expected '.$sensor->serial.', got '.$data['sensor']['serial']);

        // Get measurements array
        $measurements = $data['data']['measurements'] ?? [];
        throw_if(! is_array($measurements), Exception::class, 'Invalid Axis data structure: measurements must be an array.');

        $numPersisted = 0;

        // Process each measurement
        foreach ($measurements as $measurement) {
            // Only process people-counts measurements, dismiss all others
            if (($measurement['kind'] ?? null) !== 'people-counts') {
                continue;
            }

            // Validate measurement-level timestamps
            throw_if(! isset($measurement['utcFrom']) || ! isset($measurement['utcTo']), Exception::class, 'Missing required UTC timestamps in measurement data.');

            $items = $measurement['items'] ?? [];

            // Extract counts using helper
            $counts = $this->extractCountsFromItems($items);
            $countIn = $counts['countIn'];
            $countOut = $counts['countOut'];

            // Create new IntervalCount
            IntervalCount::query()->create([
                'ts_from' => $measurement['utcFrom'],
                'ts_to' => $measurement['utcTo'],
                'count_in' => $countIn,
                'count_out' => $countOut,
                'sensor_id' => $sensor->id,
            ]);

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
}
