import { calculateWidgetChartStatistics, widgetTimeRangeParams, widgetTimeRangeShowsDate } from '@/components/widgets/widgetChart';
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
