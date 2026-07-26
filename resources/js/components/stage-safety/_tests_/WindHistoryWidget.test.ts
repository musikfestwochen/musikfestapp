import type { StageSafetyWindHistoryPayload } from '@/types';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import WindHistoryWidget from '../WindHistoryWidget.vue';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    request: { get: vi.fn(), from: '', to: '' },
    useIntervalFn: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => mocks.request,
}));

vi.mock('@vueuse/core', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@vueuse/core')>()),
    useIntervalFn: mocks.useIntervalFn,
}));

vi.mock('@unovis/vue', () => ({
    VisXYContainer: { name: 'VisXYContainer', props: ['data'], template: '<div><slot /></div>' },
    VisLine: {
        name: 'VisLine',
        props: ['y', 'color', 'interpolateMissingData', 'lineDashArray'],
        template: '<div class="series-line" />',
    },
    VisAxis: { template: '<div />' },
    VisTooltip: { template: '<div />' },
    VisCrosshair: { name: 'VisCrosshair', props: ['color'], template: '<div />' },
}));

const organization = { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' };
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

const stubs = {
    Select: { template: '<div><slot /></div>' },
    SelectTrigger: { template: '<div><slot /></div>' },
    SelectValue: { template: '<div />' },
    SelectContent: { template: '<div><slot /></div>' },
    SelectItem: { template: '<div><slot /></div>' },
    ChartContainer: { name: 'ChartContainer', props: ['config'], template: '<div><slot /></div>' },
    ChartTooltip: { template: '<div />' },
    ChartCrosshair: { name: 'ChartCrosshair', props: ['color'], template: '<div />' },
};

describe('WindHistoryWidget', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-07-25T12:00:00Z'));
        mocks.request.get = mocks.get;
        mocks.request.from = '';
        mocks.request.to = '';
        mocks.useIntervalFn.mockImplementation((callback: () => Promise<void>) => {
            void callback();
            return { pause: vi.fn(), resume: vi.fn(), isActive: true };
        });
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => name),
        );
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders normalized sensor colors and measurement dash styles', async () => {
        mocks.get.mockResolvedValue(history);
        const wrapper = mount(WindHistoryWidget, { props: { organization }, global: { stubs } });
        await flushPromises();

        expect(wrapper.text()).toContain('Roof average');
        expect(wrapper.text()).toContain('Roof gust');
        expect(wrapper.text()).toContain('DEF456 average');

        const rows = wrapper.findComponent({ name: 'VisXYContainer' }).props('data') as Array<Record<string, number | Date | undefined>>;
        const lines = wrapper.findAllComponents({ name: 'VisLine' });

        expect(lines).toHaveLength(3);
        expect(lines[0].props('color')).toBe('var(--color-chart-1)');
        expect(lines[1].props('color')).toBe('var(--color-chart-1)');
        expect(lines[1].props('lineDashArray')).toEqual([6, 3]);
        expect(lines[2].props('color')).toBe('var(--color-chart-2)');
        expect(lines.every((line) => line.props('interpolateMissingData') === true)).toBe(true);
        expect(lines[0].props('y')(rows[0])).toBe(18);
        expect(lines[1].props('y')(rows[1])).toBe(28.8);
    });

    it('isolates a legend series and restores all when selected again', async () => {
        mocks.get.mockResolvedValue(history);
        const wrapper = mount(WindHistoryWidget, { props: { organization }, global: { stubs } });
        await flushPromises();
        const legend = wrapper.get('[data-series="sensor_1_wind_average"]');

        await legend.trigger('click');
        expect(wrapper.findAllComponents({ name: 'VisLine' })).toHaveLength(1);
        expect(Object.keys(wrapper.findComponent({ name: 'ChartContainer' }).props('config'))).toEqual(['sensor_1_wind_average']);
        expect(wrapper.findComponent({ name: 'VisCrosshair' }).props('color')).toEqual(['var(--color-chart-1)']);

        await legend.trigger('click');
        expect(wrapper.findAllComponents({ name: 'VisLine' })).toHaveLength(3);
    });

    it('restores visibility when the isolated series disappears after refresh', async () => {
        mocks.get.mockResolvedValueOnce(history).mockResolvedValueOnce({
            ...history,
            sensors: [
                {
                    ...history.sensors[0],
                    readings: history.sensors[0].readings.filter((reading) => reading.kind === 'wind_gust'),
                },
            ],
        });
        const wrapper = mount(WindHistoryWidget, { props: { organization }, global: { stubs } });
        await flushPromises();
        await wrapper.get('[data-series="sensor_1_wind_average"]').trigger('click');

        const refresh = mocks.useIntervalFn.mock.calls[0][0] as () => Promise<void>;
        await refresh();
        await flushPromises();

        expect(wrapper.findAllComponents({ name: 'VisLine' })).toHaveLength(1);
        expect(wrapper.get('[data-series="sensor_1_wind_gust"]').attributes('aria-pressed')).toBe('true');
    });
});
