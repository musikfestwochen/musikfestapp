import type { StageSafetyWindHistoryPayload } from '@/types';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import WindHistoryChart from '../WindHistoryChart.vue';

vi.mock('@unovis/vue', () => ({
    VisXYContainer: { name: 'VisXYContainer', props: ['data'], template: '<div><slot /></div>' },
    VisLine: { name: 'VisLine', props: ['y', 'color', 'interpolateMissingData'], template: '<div class="series-line" />' },
    VisAxis: { template: '<div />' },
    VisTooltip: { template: '<div />' },
    VisCrosshair: { template: '<div />' },
}));

const chartStubs = {
    Select: {
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: '<select data-testid="range" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><slot /></select>',
    },
    SelectTrigger: { template: '<div><slot /></div>' },
    SelectValue: { template: '<div />' },
    SelectContent: { template: '<div><slot /></div>' },
    SelectItem: { props: ['value'], template: '<option :value="value"><slot /></option>' },
    ChartContainer: { template: '<div><slot /></div>' },
    ChartTooltip: { template: '<div />' },
    ChartCrosshair: { template: '<div />' },
};

const history: StageSafetyWindHistoryPayload = {
    generated_at: '2026-07-25T12:00:00Z',
    from: '2026-07-25T11:00:00Z',
    to: '2026-07-25T12:00:00Z',
    sensors: [
        {
            sensor: { id: 1, identifier: 'ABC123', name: 'Main Stage', location: 'Roof', stale_after_seconds: 300 },
            readings: [
                { kind: 'wind_average', value: 5, unit: 'm/s', observed_at: '2026-07-25T11:30:00Z', window_seconds: 10 },
                { kind: 'wind_average', value: 6, unit: 'm/s', observed_at: '2026-07-25T11:40:00Z', window_seconds: 10 },
                { kind: 'wind_gust', value: 8, unit: 'm/s', observed_at: '2026-07-25T11:35:00Z', window_seconds: 10 },
            ],
        },
        {
            sensor: { id: 2, identifier: 'DEF456', name: null, location: null, stale_after_seconds: 300 },
            readings: [{ kind: 'wind_average', value: 4, unit: 'm/s', observed_at: '2026-07-25T11:35:00Z', window_seconds: 10 }],
        },
    ],
};

describe('WindHistoryChart', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders one legend for all sensor average and gust series', () => {
        const wrapper = mount(WindHistoryChart, {
            props: { data: history, loading: false, error: null, timeRange: '1h' },
            global: { stubs: chartStubs },
        });

        expect(wrapper.text()).toContain('Roof average');
        expect(wrapper.text()).toContain('Roof gust');
        expect(wrapper.text()).toContain('DEF456 average');

        const rows = wrapper.findComponent({ name: 'VisXYContainer' }).props('data') as Array<Record<string, number | Date | undefined>>;
        const lines = wrapper.findAllComponents({ name: 'VisLine' });

        expect(lines).toHaveLength(3);
        expect(lines.every((line) => line.props('interpolateMissingData') === true)).toBe(true);
        expect(lines[0].props('color')).toBe('var(--color-chart-1)');
        expect(lines[0].props('y')(rows[0])).toBe(18);
        expect(lines[0].props('y')(rows[1])).toBeUndefined();
        expect(lines[1].props('y')(rows[1])).toBe(28.8);
    });

    it('shows only a clicked legend series and restores all when clicked again', async () => {
        const wrapper = mount(WindHistoryChart, {
            props: { data: history, loading: false, error: null, timeRange: '1h' },
            global: { stubs: chartStubs },
        });
        const legend = wrapper.find('[data-series="sensor_1_wind_average"]');

        await legend.trigger('click');
        expect(wrapper.findAllComponents({ name: 'VisLine' })).toHaveLength(1);
        expect(wrapper.find('[data-series="sensor_1_wind_gust"] span').classes()).toContain('line-through');

        await legend.trigger('click');
        expect(wrapper.findAllComponents({ name: 'VisLine' })).toHaveLength(3);
    });

    it('restores visibility when the isolated series disappears', async () => {
        const wrapper = mount(WindHistoryChart, {
            props: { data: history, loading: false, error: null, timeRange: '1h' },
            global: { stubs: chartStubs },
        });
        await wrapper.find('[data-series="sensor_1_wind_average"]').trigger('click');

        await wrapper.setProps({
            data: {
                ...history,
                sensors: [
                    {
                        ...history.sensors[0],
                        readings: history.sensors[0].readings.filter((reading) => reading.kind === 'wind_gust'),
                    },
                ],
            },
        });

        expect(wrapper.findAllComponents({ name: 'VisLine' })).toHaveLength(1);
        expect(wrapper.find('[data-series="sensor_1_wind_gust"] span').classes()).not.toContain('line-through');
    });

    it('emits selected time range', async () => {
        const wrapper = mount(WindHistoryChart, {
            props: { data: history, loading: false, error: null, timeRange: '1h' },
            global: { stubs: chartStubs },
        });

        await wrapper.find('[data-testid="range"]').setValue('6h');

        expect(wrapper.emitted('update:timeRange')).toEqual([['6h']]);
    });

    it('renders useful loading and empty states', () => {
        const loading = mount(WindHistoryChart, {
            props: { data: null, loading: true, error: null, timeRange: '1h' },
            global: { stubs: chartStubs },
        });
        const empty = mount(WindHistoryChart, {
            props: { data: { ...history, sensors: [] }, loading: false, error: null, timeRange: '1h' },
            global: { stubs: chartStubs },
        });

        expect(loading.find('.animate-pulse').exists()).toBe(true);
        expect(empty.text()).toContain('No wind readings');
    });
});
