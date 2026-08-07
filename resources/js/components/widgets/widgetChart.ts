type CalendarWidgetTimeRange = 'today' | 'yesterday' | 'day-before-yesterday' | 'this-day-last-week';
export type WidgetTimeRange = '30m' | '1h' | '3h' | '6h' | '12h' | '24h' | CalendarWidgetTimeRange;
type RelativeWidgetTimeRange = Exclude<WidgetTimeRange, CalendarWidgetTimeRange>;

const WIDGET_TIME_RANGE_MINUTES: Record<RelativeWidgetTimeRange, number> = {
    '30m': 30,
    '1h': 60,
    '3h': 180,
    '6h': 360,
    '12h': 720,
    '24h': 1440,
};

export function widgetTimeRangeParams(range: WidgetTimeRange, now = new Date()): { from: string; to: string } {
    if (range === 'today') {
        const from = new Date(now);
        from.setHours(0, 0, 0, 0);

        return { from: from.toISOString(), to: now.toISOString() };
    }

    if (range === 'yesterday' || range === 'day-before-yesterday' || range === 'this-day-last-week') {
        const daysAgo = range === 'yesterday' ? 1 : range === 'day-before-yesterday' ? 2 : 7;
        const from = new Date(now);
        from.setHours(0, 0, 0, 0);
        from.setDate(from.getDate() - daysAgo);
        const to = new Date(from);
        to.setDate(to.getDate() + 1);

        return { from: from.toISOString(), to: to.toISOString() };
    }

    const to = new Date(now);
    const from = new Date(to.getTime() - WIDGET_TIME_RANGE_MINUTES[range] * 60 * 1000);

    return { from: from.toISOString(), to: to.toISOString() };
}

export function widgetTimeRangeShowsDate(range: WidgetTimeRange): boolean {
    return range === '12h' || range === '24h' || !Object.hasOwn(WIDGET_TIME_RANGE_MINUTES, range);
}

export interface WidgetChartSeries {
    key: string;
    label: string;
    color: string;
    dash?: number[];
}

export interface WidgetChartValue {
    date: Date;
    value: number;
}

export interface WidgetChartStatistics {
    count: number;
    minimum: WidgetChartValue;
    maximum: WidgetChartValue;
    average: number;
}

export interface WidgetChartStatisticMarker extends WidgetChartValue {
    label: string;
    position: Position;
}

export function calculateWidgetChartStatistics(values: WidgetChartValue[]): WidgetChartStatistics | null {
    const validValues = values.filter((point) => Number.isFinite(point.value) && Number.isFinite(point.date.getTime()));

    if (validValues.length === 0) {
        return null;
    }

    let minimum = validValues[0];
    let maximum = validValues[0];
    let total = 0;

    for (const point of validValues) {
        if (point.value < minimum.value || (point.value === minimum.value && point.date < minimum.date)) {
            minimum = point;
        }

        if (point.value > maximum.value || (point.value === maximum.value && point.date > maximum.date)) {
            maximum = point;
        }

        total += point.value;
    }

    return {
        count: validValues.length,
        minimum,
        maximum,
        average: total / validValues.length,
    };
}

export function widgetChartStatisticMarkers(
    statistics: WidgetChartStatistics | null,
    formatValue: (value: number) => string,
): WidgetChartStatisticMarker[] {
    if (!statistics) {
        return [];
    }

    const label = (kind: string, point: WidgetChartValue): string =>
        `${kind} ${formatValue(point.value)}  |  ${formatChartTooltip(point.date)}`;

    if (statistics.minimum.date.getTime() === statistics.maximum.date.getTime()) {
        return [{ ...statistics.minimum, label: label('Min / max', statistics.minimum), position: Position.Top }];
    }

    return [
        { ...statistics.minimum, label: label('Min', statistics.minimum), position: Position.Top },
        { ...statistics.maximum, label: label('Max', statistics.maximum), position: Position.Bottom },
    ];
}

export const WIDGET_CHART_COLORS = [
    'var(--color-chart-1)',
    'var(--color-chart-2)',
    'var(--color-chart-3)',
    'var(--color-chart-4)',
    'var(--color-chart-5)',
];
import { formatChartTooltip } from '@/utils/dateTimeHelpers';
import { Position } from '@unovis/ts';
