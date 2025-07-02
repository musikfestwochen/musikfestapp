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
     *
     * @throws Exception if the data does not match expected structure or values.
     */
    public function processIntervalCount(Sensor $sensor, array $data): void
    {
        // parse the data depending on the sensor type
        switch ($sensor->vendor) {
            case 'Axis':
                $this->processAxisIntervalCount($sensor, $data);
                break;

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
     *
     * @throws Exception if the data does not match expected structure or values.
     */
    public function processAxisIntervalCount(Sensor $sensor, array $data): void
    {
        // Validate API name and version
        throw_if(($data['apiName'] ?? null) !== 'Axis Retail Data' || ($data['apiVersion'] ?? null) !== '0.4', new Exception('Unsupported Axis API version or name.'));

        // Validate sensor serial
        throw_if(($data['sensor']['serial'] ?? null) !== $sensor->serial, new Exception('Sensor serial mismatch: expected '.$sensor->serial.', got '.$data['sensor']['serial']));

        // Validate timestamp
        throw_if(! isset($data['data']['utcFrom']) || ! isset($data['data']['utcTo']), new Exception('Missing required UTC timestamps in Axis data.'));

        // Validate measurement structure
        $measurements = $data['data']['measurements'] ?? null;
        throw_if(! is_array($measurements) || count($measurements) !== 1 || ($measurements[0]['kind'] ?? null) !== 'people-counts', new Exception('Invalid Axis data structure: expected exactly one people-counts measurement.'));

        $measurement = $measurements[0];
        $items = $measurement['items'] ?? [];

        // Extract counts using helper
        $counts = $this->extractCountsFromItems($items);
        $countIn = $counts['countIn'];
        $countOut = $counts['countOut'];

        // Create new IntervalCount
        IntervalCount::query()->create([
            'ts_from' => $data['data']['utcFrom'],
            'ts_to' => $data['data']['utcTo'],
            'count_in' => $countIn,
            'count_out' => $countOut,
            'sensor_id' => $sensor->id,
        ]);
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
