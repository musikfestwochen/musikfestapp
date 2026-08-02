<?php

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Models\Peoplecount\AreaSingleReset;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use App\Models\User;
use Illuminate\Support\Facades\Date;

function setupPeoplecountBasic(): array
{
    // Set the default aggregation granularity for testing
    config(['peoplecount.aggregation.granularity_minutes' => 10]);

    // Create an organization
    $organization = Organization::factory()->create([
        'name' => 'Test Organization for Peoplecount',
    ]);

    // Create an event with 24h duration (hardcoded times)
    $eventStart = Date::parse('2025-08-02 10:00:00')->utc();
    $eventEnd = $eventStart->copy()->addHours(24);

    $event = Event::factory()->create([
        'name' => 'Test Event 24h',
        'organization_id' => $organization->id,
        'starts_at' => $eventStart,
        'ends_at' => $eventEnd,
    ]);

    // Create one area for the event
    $area = Area::factory()->create([
        'name' => 'Test Area',
        'event_id' => $event->id,
    ]);

    // Create two sensors
    $sensor1 = Sensor::factory()->create([
        'vendor' => 'TestVendor',
        'model' => 'TestModel1',
        'serial' => 'TEST001',
        'organization_id' => $organization->id,
        'api_token' => 'test_token_1',
    ]);

    $sensor2 = Sensor::factory()->create([
        'vendor' => 'TestVendor',
        'model' => 'TestModel2',
        'serial' => 'TEST002',
        'organization_id' => $organization->id,
        'api_token' => 'test_token_2',
    ]);

    // Create assignments for both sensors without flipping during the whole event period
    $assignment1 = Assignment::factory()->create([
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor1->id,
        'direction_flipped' => false,
        'active_from' => $eventStart,
        'active_to' => $eventEnd,
    ]);

    $assignment2 = Assignment::factory()->create([
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor2->id,
        'direction_flipped' => false,
        'active_from' => $eventStart,
        'active_to' => $eventEnd,
    ]);

    // Create interval counts for both sensors with predictive data
    // Generate counts for every 10 minutes during the 24h period (144 intervals total per sensor)
    $intervalMinutes = 5;
    $totalIntervals = 24 * 60 / $intervalMinutes; // 144 intervals

    $intervalCounts = [];

    for ($i = 0; $i < $totalIntervals; $i++) {
        $intervalStart = $eventStart->copy()->addMinutes($i * $intervalMinutes);
        $intervalEnd = $intervalStart->copy()->addMinutes($intervalMinutes);

        // Create predictive patterns for sensor 1 (morning peak, evening peak)
        $hour = $intervalStart->hour;

        // Sensor 1: Higher activity during morning (8-10) and evening (18-20)
        // Use deterministic values based on interval index and hour for consistent results
        $baseVariation1 = ($i % 7) - 3; // Creates variation from -3 to +3 based on interval
        $sensor1CountIn = match (true) {
            $hour >= 8 && $hour < 10 => 25 + $baseVariation1, // Morning peak: 22-28
            $hour >= 18 && $hour < 20 => 30 + $baseVariation1, // Evening peak: 27-33
            $hour >= 12 && $hour < 14 => 13 + $baseVariation1, // Lunch time: 10-16
            $hour >= 22 || $hour < 6 => 2 + max(0, $baseVariation1), // Night time: 2-5
            default => 7 + $baseVariation1, // Regular hours: 4-10
        };

        $sensor1CountOut = $sensor1CountIn + (($i % 11) - 5); // Deterministic variation from -5 to +5
        $sensor1CountOut = max(0, $sensor1CountOut); // Ensure non-negative

        // Sensor 2: Different pattern - more consistent throughout day with lunch peak
        $baseVariation2 = ($i % 5) - 2; // Creates variation from -2 to +2 based on interval
        $sensor2CountIn = match (true) {
            $hour >= 12 && $hour < 14 => 35 + $baseVariation2, // Lunch peak: 33-37
            $hour >= 16 && $hour < 18 => 20 + $baseVariation2, // Afternoon activity: 18-22
            $hour >= 22 || $hour < 7 => 1 + max(0, $baseVariation2), // Night time: 1-3
            default => 10 + $baseVariation2, // Regular hours: 8-12
        };

        $sensor2CountOut = $sensor2CountIn + (($i % 9) - 4); // Deterministic variation from -4 to +4
        $sensor2CountOut = max(0, $sensor2CountOut); // Ensure non-negative

        // Create interval counts for sensor 1
        $intervalCount1 = IntervalCount::factory()->create([
            'sensor_id' => $sensor1->id,
            'ts_from' => $intervalStart,
            'ts_to' => $intervalEnd,
            'count_in' => $sensor1CountIn,
            'count_out' => $sensor1CountOut,
        ]);
        $intervalCounts[] = $intervalCount1;

        // Create interval counts for sensor 2
        $intervalCount2 = IntervalCount::factory()->create([
            'sensor_id' => $sensor2->id,
            'ts_from' => $intervalStart,
            'ts_to' => $intervalEnd,
            'count_in' => $sensor2CountIn,
            'count_out' => $sensor2CountOut,
        ]);
        $intervalCounts[] = $intervalCount2;
    }

    return [
        'organization' => $organization,
        'event' => $event,
        'area' => $area,
        'sensors' => [$sensor1, $sensor2],
        'assignments' => [$assignment1, $assignment2],
        'event_start' => $eventStart,
        'event_end' => $eventEnd,
        'interval_counts' => $intervalCounts,
    ];
}

function setupAggregationScenario(array $config = []): array
{
    $granularityMinutes = $config['granularity_minutes'] ?? 10;
    config(['peoplecount.aggregation.granularity_minutes' => $granularityMinutes]);

    $organization = Organization::factory()->create();

    $eventStart = $config['event_start'] ?? Date::parse('2025-08-02 10:00:00')->utc();
    $eventEnd = $config['event_end'] ?? $eventStart->copy()->addHours(1);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'starts_at' => $eventStart,
        'ends_at' => $eventEnd,
    ]);

    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    $sensors = [];
    $assignments = [];

    foreach ($config['sensors'] ?? [['direction_flipped' => false]] as $sensorConfig) {
        $sensor = Sensor::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $activeFrom = $sensorConfig['active_from'] ?? $eventStart;
        $activeTo = $sensorConfig['active_to'] ?? $eventEnd;

        $assignment = Assignment::factory()->create([
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'direction_flipped' => $sensorConfig['direction_flipped'] ?? false,
            'active_from' => $activeFrom,
            'active_to' => $activeTo,
        ]);

        $sensors[] = $sensor;
        $assignments[] = $assignment;
    }

    $intervalCounts = [];
    foreach ($config['interval_counts'] ?? [] as $ic) {
        $sensorIndex = $ic['sensor'] ?? 0;

        $intervalCounts[] = IntervalCount::factory()->create([
            'sensor_id' => $sensors[$sensorIndex]->id,
            'ts_from' => $ic['ts_from'],
            'ts_to' => $ic['ts_to'],
            'count_in' => $ic['count_in'] ?? 0,
            'count_out' => $ic['count_out'] ?? 0,
            'received_at' => $ic['received_at'] ?? $ic['ts_to'],
        ]);
    }

    foreach ($config['single_resets'] ?? [] as $reset) {
        AreaSingleReset::factory()->create([
            'area_id' => $area->id,
            'reset_value' => $reset['reset_value'],
            'effective_at' => $reset['effective_at'],
            'created_by' => User::factory()->create()->id,
        ]);
    }

    foreach ($config['recurring_resets'] ?? [] as $reset) {
        AreaRecurringReset::factory()->create([
            'area_id' => $area->id,
            'reset_value' => $reset['reset_value'],
            'reset_time' => $reset['reset_time'],
            'timezone' => $reset['timezone'] ?? 'UTC',
        ]);
    }

    if (isset($config['now'])) {
        Date::setTestNow($config['now']);
    }

    return [
        'organization' => $organization,
        'event' => $event,
        'area' => $area,
        'sensors' => $sensors,
        'assignments' => $assignments,
        'event_start' => $eventStart,
        'event_end' => $eventEnd,
        'interval_counts' => $intervalCounts,
    ];
}

function assertWindowCount(Area $area, string $periodStart, string $periodEnd, int $expectedCount): void
{
    $found = $area->aggregatedCounts()
        ->where('period_start', Date::parse($periodStart)->utc())
        ->where('period_end', Date::parse($periodEnd)->utc())
        ->first();

    expect($found)->not->toBeNull(sprintf('Expected aggregated count window %s to %s to exist', $periodStart, $periodEnd))
        ->and($found->count)->toBe($expectedCount, sprintf('Expected cumulative count of %d for window %s to %s, got %d', $expectedCount, $periodStart, $periodEnd, $found->count));
}
