import {
    calculateWidgetChartStatistics,
    widgetChartStatisticMarkers,
    widgetTimeRangeParams,
    widgetTimeRangeShowsDate,
} from '@/components/widgets/widgetChart';
import { Position } from '@unovis/ts';
import { describe, expect, it } from 'vitest';

describe('calculateWidgetChartStatistics', () => {
    it('calculates extrema and arithmetic average without rounding', () => {
        const statistics = calculateWidgetChartStatistics([
            { date: new Date('2026-08-06T10:00:00Z'), value: -2 },
            { date: new Date('2026-08-06T10:05:00Z'), value: 0 },
            { date: new Date('2026-08-06T10:10:00Z'), value: 3 },
        ]);

        expect(statistics).toMatchObject({ count: 3, average: 1 / 3 });
        expect(statistics?.minimum.value).toBe(-2);
        expect(statistics?.maximum.value).toBe(3);
    });

    it('uses earliest minimum and latest maximum when values tie', () => {
        const statistics = calculateWidgetChartStatistics([
            { date: new Date('2026-08-06T10:10:00Z'), value: 5 },
            { date: new Date('2026-08-06T10:00:00Z'), value: 5 },
            { date: new Date('2026-08-06T10:20:00Z'), value: 5 },
        ]);

        expect(statistics?.minimum.date.toISOString()).toBe('2026-08-06T10:00:00.000Z');
        expect(statistics?.maximum.date.toISOString()).toBe('2026-08-06T10:20:00.000Z');
    });

    it('returns null when no valid samples exist', () => {
        expect(calculateWidgetChartStatistics([])).toBeNull();
        expect(calculateWidgetChartStatistics([{ date: new Date('invalid'), value: Number.NaN }])).toBeNull();
    });
});

describe('widgetChartStatisticMarkers', () => {
    it('formats extrema labels and positions', () => {
        const markers = widgetChartStatisticMarkers(
            {
                count: 2,
                minimum: { date: new Date('2026-08-06T10:00:00Z'), value: 10 },
                maximum: { date: new Date('2026-08-06T10:05:00Z'), value: 20 },
                average: 15,
            },
            (value) => `${value.toFixed(1)} km/h`,
        );

        expect(markers).toMatchObject([
            { value: 10, position: Position.Top },
            { value: 20, position: Position.Bottom },
        ]);
        expect(markers.map((marker) => marker.label)).toEqual([
            expect.stringMatching(/^Min 10\.0 km\/h  \|  .+$/),
            expect.stringMatching(/^Max 20\.0 km\/h  \|  .+$/),
        ]);
    });

    it('combines extrema at the same timestamp', () => {
        const date = new Date('2026-08-06T10:00:00Z');
        const markers = widgetChartStatisticMarkers(
            {
                count: 1,
                minimum: { date, value: 10 },
                maximum: { date, value: 10 },
                average: 10,
            },
            String,
        );

        expect(markers).toHaveLength(1);
        expect(markers[0]).toMatchObject({ value: 10, position: Position.Top });
        expect(markers[0].label).toMatch(/^Min \/ max 10  \|  .+$/);
        expect(widgetChartStatisticMarkers(null, String)).toEqual([]);
    });
});

describe('widgetTimeRangeParams', () => {
    it('calculates relative ranges', () => {
        expect(widgetTimeRangeParams('1h', new Date('2026-08-06T15:00:00Z'))).toEqual({
            from: '2026-08-06T14:00:00.000Z',
            to: '2026-08-06T15:00:00.000Z',
        });
    });

    it('calculates yesterday from local calendar midnights', () => {
        const range = widgetTimeRangeParams('yesterday', new Date(2026, 7, 6, 15));
        const from = new Date(range.from);
        const to = new Date(range.to);

        expect([from.getFullYear(), from.getMonth(), from.getDate(), from.getHours()]).toEqual([2026, 7, 5, 0]);
        expect([to.getFullYear(), to.getMonth(), to.getDate(), to.getHours()]).toEqual([2026, 7, 6, 0]);
        expect(widgetTimeRangeShowsDate('yesterday')).toBe(true);
    });

    it.each([
        ['today', 6, 0, 6, 15],
        ['day-before-yesterday', 4, 0, 5, 0],
        ['this-day-last-week', 30, 0, 31, 0],
    ] as const)('calculates the %s calendar range', (range, fromDay, fromHour, toDay, toHour) => {
        const params = widgetTimeRangeParams(range, new Date(2026, 7, 6, 15));
        const from = new Date(params.from);
        const to = new Date(params.to);

        expect([from.getDate(), from.getHours()]).toEqual([fromDay, fromHour]);
        expect([to.getDate(), to.getHours()]).toEqual([toDay, toHour]);
        expect(widgetTimeRangeShowsDate(range)).toBe(true);
    });
});
