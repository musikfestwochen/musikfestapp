<?php

use App\Jobs\AggregateAreaCounts;
use App\Models\Peoplecount\IntervalCount;
use Illuminate\Support\Carbon;

dataset('granularities', [
    '5min' => 5,
    '10min' => 10,
    '15min' => 15,
    '30min' => 30,
]);

describe('window construction', function () {
    it('generates correct number of windows', function (int $granularity) {
        $start = '2025-08-02 10:00:00';
        $end = '2025-08-02 11:00:00';
        $expectedWindows = intdiv(60, $granularity);

        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse($start)->utc(),
            'event_end' => Carbon::parse($end)->utc(),
            'granularity_minutes' => $granularity,
            'now' => Carbon::parse($end)->addMinutes(5),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => $start, 'ts_to' => Carbon::parse($start)->addMinutes(5), 'count_in' => 3, 'count_out' => 1],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        expect($setup['area']->refresh()->aggregatedCounts->count())->toBe($expectedWindows);
    })->with('granularities');

    it('clips last window to event end when event does not align with granularity', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:35:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:40:00'),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 3, 'count_out' => 1],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        $lastWindow = $setup['area']->refresh()->aggregatedCounts()->latest('period_end')->first();
        expect($lastWindow->period_end->format('H:i:s'))->toBe('10:35:00');
    });
});

describe('interval inclusion', function () {
    it('ignores pre-assignment data', function () {
        $start = '2025-08-02 10:00:00';
        $assignmentStart = '2025-08-02 10:10:00';

        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse($start)->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:30:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:35:00'),
            'sensors' => [['direction_flipped' => false, 'active_from' => Carbon::parse($assignmentStart)->utc()]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 20, 'count_out' => 5],
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:10:00', 'ts_to' => '2025-08-02 10:15:00', 'count_in' => 3, 'count_out' => 1],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 0);
        assertWindowCount($setup['area'], '2025-08-02 10:10:00', '2025-08-02 10:20:00', 2);
    });

    it('ignores intervals starting before assignment even if assignment overlaps window', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:20:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:25:00'),
            'sensors' => [['direction_flipped' => false, 'active_from' => Carbon::parse('2025-08-02 10:07:00')->utc()]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 10, 'count_out' => 2],
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:10:00', 'ts_to' => '2025-08-02 10:15:00', 'count_in' => 5, 'count_out' => 1],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 0);
        assertWindowCount($setup['area'], '2025-08-02 10:10:00', '2025-08-02 10:20:00', 4);
    });

    it('includes intervals at and after assignment start within overlapping window', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:30:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:35:00'),
            'sensors' => [['direction_flipped' => false, 'active_from' => Carbon::parse('2025-08-02 10:07:00')->utc()]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 20, 'count_out' => 5],
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:10:00', 'ts_to' => '2025-08-02 10:15:00', 'count_in' => 6, 'count_out' => 2],
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:20:00', 'ts_to' => '2025-08-02 10:25:00', 'count_in' => 4, 'count_out' => 1],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 0);
        assertWindowCount($setup['area'], '2025-08-02 10:10:00', '2025-08-02 10:20:00', 4);
        assertWindowCount($setup['area'], '2025-08-02 10:20:00', '2025-08-02 10:30:00', 7);
    });

    it('includes intervals starting before assignment end', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:30:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:35:00'),
            'sensors' => [['direction_flipped' => false, 'active_to' => Carbon::parse('2025-08-02 10:25:00')->utc()]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:20:00', 'ts_to' => '2025-08-02 10:25:00', 'count_in' => 8, 'count_out' => 3],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:20:00', '2025-08-02 10:30:00', 5);
    });

    it('ignores intervals starting exactly at assignment end', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:30:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:35:00'),
            'sensors' => [['direction_flipped' => false, 'active_to' => Carbon::parse('2025-08-02 10:25:00')->utc()]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:20:00', 'ts_to' => '2025-08-02 10:25:00', 'count_in' => 8, 'count_out' => 3],
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:25:00', 'ts_to' => '2025-08-02 10:30:00', 'count_in' => 50, 'count_out' => 10],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:20:00', '2025-08-02 10:30:00', 5);
    });

    it('produces zero net for sparse or missing data', function (int $granularity) {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:30:00')->utc(),
            'granularity_minutes' => $granularity,
            'now' => Carbon::parse('2025-08-02 10:35:00'),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [],
        ]);

        AggregateAreaCounts::dispatch();

        $counts = $setup['area']->refresh()->aggregatedCounts;
        foreach ($counts as $count) {
            expect($count->count)->toBe(0);
        }
    })->with('granularities');
});

describe('net contribution', function () {
    it('calculates net as count_in minus count_out', function (int $granularity) {
        $start = '2025-08-02 10:00:00';
        $intervalEnd = Carbon::parse($start)->addMinutes(5);

        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse($start)->utc(),
            'event_end' => Carbon::parse($start)->addMinutes($granularity)->utc(),
            'granularity_minutes' => $granularity,
            'now' => Carbon::parse($start)->addMinutes($granularity + 5),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => $start, 'ts_to' => $intervalEnd, 'count_in' => 10, 'count_out' => 3],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], $start, Carbon::parse($start)->addMinutes($granularity)->utc()->format('Y-m-d H:i:s'), 7);
    })->with('granularities');

    it('negates net when direction is flipped', function (int $granularity) {
        $start = '2025-08-02 10:00:00';
        $intervalEnd = Carbon::parse($start)->addMinutes(5);

        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse($start)->utc(),
            'event_end' => Carbon::parse($start)->addMinutes($granularity)->utc(),
            'granularity_minutes' => $granularity,
            'now' => Carbon::parse($start)->addMinutes($granularity + 5),
            'sensors' => [['direction_flipped' => true]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => $start, 'ts_to' => $intervalEnd, 'count_in' => 10, 'count_out' => 3],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], $start, Carbon::parse($start)->addMinutes($granularity)->utc()->format('Y-m-d H:i:s'), -7);
    })->with('granularities');
});

describe('cumulative count', function () {
    it('carries count forward across windows', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:30:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:35:00'),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 10, 'count_out' => 4],
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:10:00', 'ts_to' => '2025-08-02 10:15:00', 'count_in' => 6, 'count_out' => 1],
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:20:00', 'ts_to' => '2025-08-02 10:25:00', 'count_in' => 3, 'count_out' => 0],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 6);
        assertWindowCount($setup['area'], '2025-08-02 10:10:00', '2025-08-02 10:20:00', 11);
        assertWindowCount($setup['area'], '2025-08-02 10:20:00', '2025-08-02 10:30:00', 14);
    });
});

describe('reset handling', function () {
    it('starts at zero by default at event start', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:20:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:25:00'),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 5, 'count_out' => 2],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 3);
    });

    it('overrides zero with single reset at event start', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:20:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:25:00'),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 5, 'count_out' => 2],
            ],
            'single_resets' => [
                ['reset_value' => 100, 'effective_at' => Carbon::parse('2025-08-02 10:00:00')],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 103);
    });

    it('applies single reset mid-event as new starting count', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:30:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:35:00'),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 5, 'count_out' => 1],
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:10:00', 'ts_to' => '2025-08-02 10:15:00', 'count_in' => 3, 'count_out' => 1],
            ],
            'single_resets' => [
                ['reset_value' => 100, 'effective_at' => Carbon::parse('2025-08-02 10:10:00')],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 4);
        assertWindowCount($setup['area'], '2025-08-02 10:10:00', '2025-08-02 10:20:00', 102);
    });

    it('splits window when reset lands inside natural window', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:30:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:35:00'),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 5, 'count_out' => 1],
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:10:00', 'ts_to' => '2025-08-02 10:15:00', 'count_in' => 3, 'count_out' => 1],
            ],
            'single_resets' => [
                ['reset_value' => 42, 'effective_at' => Carbon::parse('2025-08-02 10:05:00')],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        $windows = $setup['area']->refresh()->aggregatedCounts()->orderBy('period_start')->get();

        $beforeReset = $windows->first(fn ($w) => $w->period_start->format('H:i') === '10:00'
            && $w->period_end->format('H:i') === '10:05');
        expect($beforeReset)->not->toBeNull('Expected a split window 10:00-10:05');
        expect($beforeReset->count)->toBe(4);

        $afterReset = $windows->first(fn ($w) => $w->period_start->format('H:i') === '10:05'
            && $w->period_end->format('H:i') === '10:10');
        expect($afterReset)->not->toBeNull('Expected a split window 10:05-10:10');
        expect($afterReset->count)->toBe(42);
    })->skip('not yet implemented: current algorithm does not split windows at reset points');

    it('applies recurring reset at configured time', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:30:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:35:00'),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 5, 'count_out' => 2],
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:10:00', 'ts_to' => '2025-08-02 10:15:00', 'count_in' => 3, 'count_out' => 1],
            ],
            'recurring_resets' => [
                ['reset_value' => 50, 'reset_time' => '10:10', 'timezone' => 'UTC'],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 3);
        assertWindowCount($setup['area'], '2025-08-02 10:10:00', '2025-08-02 10:20:00', 52);
    });

    it('prioritizes single reset over event start when both at same timestamp', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:20:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:25:00'),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 5, 'count_out' => 2],
            ],
            'single_resets' => [
                ['reset_value' => 100, 'effective_at' => Carbon::parse('2025-08-02 10:00:00')],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 103);
    });
});

describe('multiple sensors', function () {
    it('sums nets from all assignments', function (int $granularity) {
        $start = '2025-08-02 10:00:00';
        $intervalEnd = Carbon::parse($start)->addMinutes(5);

        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse($start)->utc(),
            'event_end' => Carbon::parse($start)->addMinutes($granularity)->utc(),
            'granularity_minutes' => $granularity,
            'now' => Carbon::parse($start)->addMinutes($granularity + 5),
            'sensors' => [
                ['direction_flipped' => false],
                ['direction_flipped' => false],
            ],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => $start, 'ts_to' => $intervalEnd, 'count_in' => 10, 'count_out' => 3],
                ['sensor' => 1, 'ts_from' => $start, 'ts_to' => $intervalEnd, 'count_in' => 8, 'count_out' => 5],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        $endFormatted = Carbon::parse($start)->addMinutes($granularity)->utc()->format('Y-m-d H:i:s');
        assertWindowCount($setup['area'], $start, $endFormatted, 10);
    })->with('granularities');
});

describe('late-arriving data', function () {
    it('recalculates affected windows when data arrives late', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 11:00:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:35:00'),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 5, 'count_out' => 2],
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:10:00', 'ts_to' => '2025-08-02 10:15:00', 'count_in' => 4, 'count_out' => 1],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 3);
        assertWindowCount($setup['area'], '2025-08-02 10:10:00', '2025-08-02 10:20:00', 6);

        IntervalCount::factory()->create([
            'sensor_id' => $setup['sensors'][0]->id,
            'ts_from' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'ts_to' => Carbon::parse('2025-08-02 10:05:00')->utc(),
            'count_in' => 10,
            'count_out' => 3,
            'received_at' => Carbon::parse('2025-08-02 10:45:00')->utc(),
        ]);

        Carbon::setTestNow('2025-08-02 10:50:00');
        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 7);
    })->skip('not yet implemented');

    it('updates data watermark after aggregation pass', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 10:20:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:25:00'),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 5, 'count_out' => 2],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        $area = $setup['area']->refresh();
        expect($area->data_watermark)->not->toBeNull();
    })->skip('not yet implemented');

    it('extends recalculation range to earliest affected window', function () {
        $setup = setupAggregationScenario([
            'event_start' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'event_end' => Carbon::parse('2025-08-02 11:00:00')->utc(),
            'granularity_minutes' => 10,
            'now' => Carbon::parse('2025-08-02 10:35:00'),
            'sensors' => [['direction_flipped' => false]],
            'interval_counts' => [
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:00:00', 'ts_to' => '2025-08-02 10:05:00', 'count_in' => 3, 'count_out' => 1],
                ['sensor' => 0, 'ts_from' => '2025-08-02 10:10:00', 'ts_to' => '2025-08-02 10:15:00', 'count_in' => 2, 'count_out' => 0],
            ],
        ]);

        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 2);
        assertWindowCount($setup['area'], '2025-08-02 10:10:00', '2025-08-02 10:20:00', 4);

        IntervalCount::factory()->create([
            'sensor_id' => $setup['sensors'][0]->id,
            'ts_from' => Carbon::parse('2025-08-02 10:00:00')->utc(),
            'ts_to' => Carbon::parse('2025-08-02 10:05:00')->utc(),
            'count_in' => 10,
            'count_out' => 1,
            'received_at' => Carbon::parse('2025-08-02 10:45:00')->utc(),
        ]);

        Carbon::setTestNow('2025-08-02 10:50:00');
        AggregateAreaCounts::dispatch();

        assertWindowCount($setup['area'], '2025-08-02 10:00:00', '2025-08-02 10:10:00', 9);
        assertWindowCount($setup['area'], '2025-08-02 10:10:00', '2025-08-02 10:20:00', 11);
    })->skip('not yet implemented');
});
