import type { StageSafetyLqiHistoryPayload } from '@/types';
import { CurveType } from '@unovis/ts';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, onMounted, ref } from 'vue';
import LqiHistoryWidget from '../LqiHistoryWidget.vue';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    request: { get: vi.fn(), from: '', to: '' },
    useIntervalFn: vi.fn(),
    crosshair: { setData: vi.fn() },
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => mocks.request,
}));

vi.mock('@vueuse/core', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@vueuse/core')>()),
    useIntervalFn: mocks.useIntervalFn,
}));

vi.mock('@unovis/vue', () => ({
    VisXYContainer: { name: 'VisXYContainer', props: ['data', 'yDomain'], template: '<div><slot /></div>' },
    VisLine: {
        name: 'VisLine',
        props: ['data', 'y', 'color', 'curveType'],
        template: '<div class="series-line" />',
    },
    VisAxis: { template: '<div />' },
    VisPlotline: { name: 'VisPlotline', props: ['value', 'labelText'], template: '<div />' },
    VisScatter: { name: 'VisScatter', props: ['data', 'label', 'color'], template: '<div />' },
    VisTooltip: { template: '<div />' },
    VisCrosshair: {
        name: 'VisCrosshair',
        props: ['color', 'template'],
        setup: (_props: unknown, { expose }: { expose: (value: unknown) => void }) => {
            const component = ref<typeof mocks.crosshair>();
            expose({ component });
            onMounted(() => void nextTick(() => (component.value = mocks.crosshair)));
        },
        template: '<div />',
    },
}));

const organization = { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' };
const history: StageSafetyLqiHistoryPayload = {
    generated_at: '2026-07-25T12:00:00Z',
    from: '2026-07-25T11:00:00Z',
    to: '2026-07-25T12:00:00Z',
    sensors: [
        {
            sensor: { id: 1, identifier: 'ABC123', name: 'Main Stage', location: 'Roof', stale_after_seconds: 300 },
            samples: [
                { observed_at: '2026-07-25T11:30:00Z', lqi_percent: 0 },
                { observed_at: '2026-07-25T11:40:00Z', lqi_percent: 50.8974358974 },
            ],
        },
        {
            sensor: { id: 2, identifier: 'DEF456', name: null, location: null, stale_after_seconds: 300 },
            samples: [{ observed_at: '2026-07-25T11:35:00Z', lqi_percent: 100 }],
        },
    ],
};

const stubs = {
    Select: {
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template:
            '<select data-testid="range-select" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><slot /></select>',
    },
    SelectTrigger: { template: '<div><slot /></div>' },
    SelectValue: { template: '<div />' },
    SelectContent: { template: '<div><slot /></div>' },
    SelectItem: { props: ['value'], template: '<option :value="value"><slot /></option>' },
    ChartContainer: { name: 'ChartContainer', props: ['config'], template: '<div><slot /></div>' },
    ChartTooltip: { template: '<div />' },
    ChartCrosshair: { name: 'ChartCrosshair', props: ['color'], template: '<div />' },
    Switch: {
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template:
            '<button aria-label="Show chart statistics" :aria-pressed="modelValue" @click="$emit(\'update:modelValue\', !modelValue)">Statistics</button>',
    },
};

describe('LqiHistoryWidget', () => {
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

    it('renders one percentage series per sensor on a fixed domain', async () => {
        mocks.get.mockResolvedValue(history);
        const wrapper = mount(LqiHistoryWidget, { props: { organization }, global: { stubs } });
        await flushPromises();

        expect(wrapper.text()).toContain('Roof');
        expect(wrapper.text()).toContain('DEF456');

        const lines = wrapper.findAllComponents({ name: 'VisLine' });
        const firstRows = lines[0].props('data') as Array<Record<string, number | Date>>;
        const secondRows = lines[1].props('data') as Array<Record<string, number | Date>>;

        expect(lines).toHaveLength(2);
        expect(wrapper.findComponent({ name: 'VisXYContainer' }).props('yDomain')).toEqual([0, 100]);
        expect(firstRows).toHaveLength(2);
        expect(secondRows).toHaveLength(1);
        expect(lines[0].props('y')(firstRows[1])).toBeCloseTo(50.8974358974);
        expect(lines[1].props('y')(secondRows[0])).toBe(100);
        expect(lines.every((line) => line.props('curveType') === CurveType.MonotoneX)).toBe(true);

        const crosshair = wrapper.findComponent({ name: 'VisCrosshair' });
        const template = crosshair.props('template') as (datum: Record<string, number | Date>, x: Date) => string;
        expect(template(firstRows[1], firstRows[1].date as Date)).toContain('50.9%');
        expect(wrapper.get('time').attributes('datetime')).toBe('2026-07-25T11:40:00.000Z');
    });

    it('uses one hour by default and supports the thirty-minute range', async () => {
        mocks.get.mockResolvedValue(history);
        const wrapper = mount(LqiHistoryWidget, { props: { organization }, global: { stubs } });
        await flushPromises();

        expect(mocks.request.from).toBe('2026-07-25T11:00:00.000Z');
        expect(mocks.request.to).toBe('2026-07-25T12:00:00.000Z');

        await wrapper.get('[data-testid="range-select"]').setValue('30m');
        await flushPromises();

        expect(mocks.request.from).toBe('2026-07-25T11:30:00.000Z');
        expect(mocks.request.to).toBe('2026-07-25T12:00:00.000Z');

        await wrapper.get('[data-testid="range-select"]').setValue('yesterday');
        await flushPromises();

        expect(mocks.request.from).toBe(new Date(2026, 6, 24).toISOString());
        expect(mocks.request.to).toBe(new Date(2026, 6, 25).toISOString());
    });

    it('shows percentage statistics and annotates an isolated sensor', async () => {
        mocks.get.mockResolvedValue(history);
        const wrapper = mount(LqiHistoryWidget, { props: { organization }, global: { stubs } });
        await flushPromises();

        await wrapper.get('[aria-label="Show chart statistics"]').trigger('click');

        expect(wrapper.getComponent({ name: 'ChartContainer' }).classes()).toContain('widget-history-chart');
        expect(wrapper.get('[data-series="sensor_1_lqi"]').text()).toContain('0.0%');
        expect(wrapper.get('[data-series="sensor_1_lqi"]').text()).toContain('25.4%');
        expect(wrapper.get('[data-series="sensor_1_lqi"]').text()).toContain('50.9%');
        expect(wrapper.findComponent({ name: 'VisPlotline' }).exists()).toBe(false);

        await wrapper.get('[data-series="sensor_1_lqi"]').trigger('click');

        expect(wrapper.getComponent({ name: 'VisPlotline' }).props('value')).toBeCloseTo(25.4487179487);
        expect(wrapper.getComponent({ name: 'VisPlotline' }).props('labelText')).toBe('Avg 25.4%');
        const scatter = wrapper.getComponent({ name: 'VisScatter' });
        const markers = scatter.props('data') as Array<{ label: string }>;
        expect(markers.map((marker) => marker.label)).toEqual([
            expect.stringMatching(/^Min 0\.0%  \|  .+$/),
            expect.stringMatching(/^Max 50\.9%  \|  .+$/),
        ]);
        expect(scatter.props('color')).toBe('hsl(var(--foreground))');
    });
});
