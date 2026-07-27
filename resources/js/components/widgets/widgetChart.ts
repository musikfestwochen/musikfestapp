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

export const WIDGET_CHART_COLORS = [
    'var(--color-chart-1)',
    'var(--color-chart-2)',
    'var(--color-chart-3)',
    'var(--color-chart-4)',
    'var(--color-chart-5)',
];
