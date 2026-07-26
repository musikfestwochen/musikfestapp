export type WidgetTimeRange = '1h' | '3h' | '6h' | '12h' | '24h';

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
