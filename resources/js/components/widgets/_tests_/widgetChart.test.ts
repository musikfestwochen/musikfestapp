import { calculateWidgetChartStatistics } from '@/components/widgets/widgetChart';
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
