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
});
