export type WidgetTimeRange = '30m' | '1h' | '3h' | '6h' | '12h' | '24h';

export const WIDGET_TIME_RANGE_MINUTES: Record<WidgetTimeRange, number> = {
    '30m': 30,
    '1h': 60,
    '3h': 180,
    '6h': 360,
    '12h': 720,
    '24h': 1440,
};

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

export const WIDGET_CHART_COLORS = [
    'var(--color-chart-1)',
    'var(--color-chart-2)',
    'var(--color-chart-3)',
    'var(--color-chart-4)',
    'var(--color-chart-5)',
];
