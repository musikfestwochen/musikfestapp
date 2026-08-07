import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import WidgetChartLegend from '../WidgetChartLegend.vue';

const series = [
    { key: 'average', label: 'Main Stage average', color: 'red' },
    { key: 'gust', label: 'Main Stage gust', color: 'red', dash: [6, 3] },
];

describe('WidgetChartLegend', () => {
    it('renders consistent visible and hidden series controls', () => {
        const wrapper = mount(WidgetChartLegend, {
            props: { series, hiddenSeriesKeys: new Set(['gust']) },
        });

        expect(wrapper.get('[data-series="average"]').attributes('aria-pressed')).toBe('true');
        expect(wrapper.get('[data-series="gust"]').attributes('aria-pressed')).toBe('false');
        expect(wrapper.get('[data-series="gust"] span').classes()).toContain('line-through');
        expect(wrapper.get('[data-series="gust"] line').attributes('stroke-dasharray')).toBe('6,3');
    });

    it('emits plain and additive selections', async () => {
        const wrapper = mount(WidgetChartLegend, {
            props: { series, hiddenSeriesKeys: new Set<string>() },
        });

        await wrapper.get('[data-series="average"]').trigger('click');
        await wrapper.get('[data-series="gust"]').trigger('click', { shiftKey: true });

        expect(wrapper.emitted('select')).toEqual([
            ['average', false],
            ['gust', true],
        ]);
    });

    it('renders formatted statistics for visible and hidden series', () => {
        const wrapper = mount(WidgetChartLegend, {
            props: {
                series,
                hiddenSeriesKeys: new Set(['gust']),
                statisticsEnabled: true,
                statistics: {
                    average: {
                        count: 2,
                        minimum: { date: new Date('2026-08-06T10:00:00Z'), value: 10 },
                        maximum: { date: new Date('2026-08-06T10:05:00Z'), value: 20 },
                        average: 15,
                    },
                    gust: null,
                },
                formatValue: (value: number) => `${value.toFixed(1)} km/h`,
            },
        });

        expect(wrapper.get('[data-series="average"]').text()).toContain('10.0 km/h');
        expect(wrapper.get('[data-series="average"]').text()).toContain('15.0 km/h');
        expect(wrapper.get('[data-series="average"]').text()).toContain('20.0 km/h');
        expect(wrapper.get('[data-series="gust"]').text()).toContain('N/A');
        expect(wrapper.get('[data-series="gust"]').attributes('aria-pressed')).toBe('false');
    });
});
