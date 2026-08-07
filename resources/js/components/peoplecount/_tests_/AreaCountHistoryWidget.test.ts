import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import AreaCountHistoryWidget from '../AreaCountHistoryWidget.vue';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    request: { processing: false, get: vi.fn(), from: '', to: '' },
    useIntervalFn: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => mocks.request,
    usePage: () => ({
        props: {
            auth: {
                permissions: ['peoplecount.widgets.area_count_history'],
                global_permissions: [],
                roles: [],
            },
        },
    }),
}));

vi.mock('@vueuse/core', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@vueuse/core')>()),
    useIntervalFn: mocks.useIntervalFn,
}));

vi.mock('@unovis/vue', () => ({
    VisXYContainer: { name: 'VisXYContainer', props: ['data', 'margin'], template: '<div><slot /></div>' },
    VisLine: { name: 'VisLine', props: ['data', 'color', 'lineDashArray', 'y'], template: '<div />' },
    VisAxis: { template: '<div />' },
    VisPlotline: { name: 'VisPlotline', props: ['value', 'labelText'], template: '<div />' },
    VisScatter: { name: 'VisScatter', props: ['data', 'label', 'color'], template: '<div />' },
    VisTooltip: { template: '<div />' },
    VisCrosshair: { name: 'VisCrosshair', props: ['color'], template: '<div />' },
}));

const globalStubs = {
    Select: {
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template:
            '<select data-testid="range-select" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><slot /></select>',
    },
    SelectTrigger: { template: '<div><slot /></div>' },
    SelectValue: { template: '<div></div>' },
    SelectContent: { template: '<div><slot /></div>' },
    SelectItem: {
        props: ['value'],
        template: '<option :value="value"><slot /></option>',
    },
    VisXYContainer: { name: 'VisXYContainer', props: ['data', 'margin'], template: '<div><slot /></div>' },
    VisLine: { name: 'VisLine', props: ['data', 'color', 'lineDashArray', 'y'], template: '<div></div>' },
    VisAxis: { template: '<div></div>' },
    VisPlotline: { name: 'VisPlotline', props: ['value', 'labelText'], template: '<div></div>' },
    VisScatter: { name: 'VisScatter', props: ['data', 'label', 'color'], template: '<div></div>' },
    ChartContainer: { name: 'ChartContainer', props: ['config'], template: '<div><slot /></div>' },
    ChartCrosshair: { name: 'ChartCrosshair', props: ['color'], template: '<div></div>' },
    ChartTooltip: { template: '<div></div>' },
    Switch: {
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template:
            '<button aria-label="Show chart statistics" :aria-pressed="modelValue" @click="$emit(\'update:modelValue\', !modelValue)">Statistics</button>',
    },
};

describe('AreaCountHistoryWidget', () => {
    const mockOrganization = {
        id: 1,
        slug: 'test-org',
        name: 'Test Organization',
    };

    const mockSeries = [
        {
            id: 1,
            name: 'Area 1',
            event_name: 'Event 1',
            data: [
                { time: '2025-08-04T22:00:00Z', count: 10 },
                { time: '2025-08-04T22:05:00Z', count: 15 },
            ],
        },
    ];

    beforeEach(() => {
        vi.resetAllMocks();
        mocks.request.processing = false;
        mocks.request.get = mocks.get;
        mocks.request.from = '';
        mocks.request.to = '';
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => name),
        );
        vi.useFakeTimers();

        mocks.useIntervalFn.mockImplementation((callback: () => Promise<void>) => {
            void callback();
            return { pause: vi.fn(), resume: vi.fn(), isActive: true };
        });

        vi.setSystemTime(new Date('2025-08-04T22:08:00Z'));

        localStorage.clear();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('fetches history on mount with default range', async () => {
        mocks.get.mockResolvedValue(mockSeries);

        const wrapper = mount(AreaCountHistoryWidget, {
            props: { organization: mockOrganization },
            global: { stubs: globalStubs },
        });

        await flushPromises();

        expect(mocks.get).toHaveBeenCalledWith('peoplecount.area-count-history.index');
        expect(mocks.request.from).toBe('2025-08-04T21:08:00.000Z');
        expect(mocks.request.to).toBe('2025-08-04T22:08:00.000Z');
        expect(wrapper.get('time').attributes('datetime')).toBe('2025-08-04T22:05:00.000Z');
    });

    it('renders the loading state initially', () => {
        mocks.get.mockReturnValue(new Promise(() => {}));

        const wrapper = mount(AreaCountHistoryWidget, {
            props: { organization: mockOrganization },
            global: { stubs: globalStubs },
        });

        expect(wrapper.find('.animate-pulse').exists()).toBe(true);
    });

    it('renders the empty state when no series are returned', async () => {
        mocks.get.mockResolvedValue([]);

        const wrapper = mount(AreaCountHistoryWidget, {
            props: { organization: mockOrganization },
            global: { stubs: globalStubs },
        });

        await flushPromises();

        expect(wrapper.text()).toContain('No data available');
    });

    it('uses entity colors and shared legend selection', async () => {
        mocks.get.mockResolvedValue([
            ...mockSeries,
            {
                id: 2,
                name: 'Area 2',
                event_name: 'Event 1',
                data: [{ time: '2025-08-04T22:00:00Z', count: 20 }],
            },
        ]);
        const wrapper = mount(AreaCountHistoryWidget, {
            props: { organization: mockOrganization },
            global: { stubs: globalStubs },
        });
        await flushPromises();

        const lines = wrapper.findAllComponents({ name: 'VisLine' });
        const rows = lines[0].props('data') as Array<Record<string, number | Date | undefined>>;
        expect(lines).toHaveLength(2);
        expect(lines[0].props('color')).toBe('var(--color-chart-1)');
        expect(lines[1].props('color')).toBe('var(--color-chart-2)');
        expect(lines.every((line) => line.props('lineDashArray') === undefined)).toBe(true);
        expect(lines[0].props('y')(rows[0])).toBe(10);
        expect(lines[1].props('y')(rows[0])).toBe(20);

        await wrapper.get('[data-series="area_1"]').trigger('click');
        expect(wrapper.findAllComponents({ name: 'VisLine' })).toHaveLength(1);
        expect(Object.keys(wrapper.findComponent({ name: 'ChartContainer' }).props('config'))).toEqual(['area_1']);
        expect(wrapper.findComponent({ name: 'VisCrosshair' }).props('color')).toEqual(['var(--color-chart-1)']);

        await wrapper.get('[data-series="area_2"]').trigger('click', { shiftKey: true });
        expect(wrapper.findAllComponents({ name: 'VisLine' })).toHaveLength(2);
    });

    it('shows statistics and focused extrema for the visible series', async () => {
        mocks.get.mockResolvedValue(mockSeries);
        const wrapper = mount(AreaCountHistoryWidget, {
            props: { organization: mockOrganization },
            global: { stubs: globalStubs },
        });
        await flushPromises();

        expect(wrapper.findComponent({ name: 'VisPlotline' }).exists()).toBe(false);
        expect(wrapper.getComponent({ name: 'VisXYContainer' }).props('margin')).toBeUndefined();
        expect(wrapper.getComponent({ name: 'ChartContainer' }).classes()).toContain('h-[240px]');
        expect(wrapper.getComponent({ name: 'ChartContainer' }).classes()).toContain('widget-history-chart');

        await wrapper.get('[aria-label="Show chart statistics"]').trigger('click');

        expect(wrapper.getComponent({ name: 'ChartContainer' }).classes()).toContain('h-[220px]');
        expect(wrapper.get('[data-series="area_1"]').text()).toContain('10');
        expect(wrapper.get('[data-series="area_1"]').text()).toContain('12.5');
        expect(wrapper.get('[data-series="area_1"]').text()).toContain('15');
        expect(wrapper.getComponent({ name: 'VisPlotline' }).props('value')).toBe(12.5);
        expect(wrapper.getComponent({ name: 'VisPlotline' }).props('labelText')).toBe('Avg 12.5');

        const scatter = wrapper.getComponent({ name: 'VisScatter' });
        const markers = scatter.props('data') as Array<{ label: string }>;
        expect(markers.map((marker) => marker.label)).toEqual([expect.stringMatching(/^Min 10  \|  .+$/), expect.stringMatching(/^Max 15  \|  .+$/)]);
        expect(scatter.props('color')).toBe('hsl(var(--foreground))');
    });

    it('keeps the empty state visible while another range loads', async () => {
        mocks.get.mockResolvedValueOnce([]).mockReturnValueOnce(new Promise(() => {}));
        const wrapper = mount(AreaCountHistoryWidget, {
            props: { organization: mockOrganization },
            global: { stubs: globalStubs },
        });
        await flushPromises();

        await wrapper.find('[data-testid="range-select"]').setValue('6h');

        expect(wrapper.text()).toContain('No data available');
        expect(wrapper.find('.animate-pulse').exists()).toBe(false);
    });

    it('renders an error message when the request fails', async () => {
        mocks.get.mockRejectedValue(new Error('boom'));

        const wrapper = mount(AreaCountHistoryWidget, {
            props: { organization: mockOrganization },
            global: { stubs: globalStubs },
        });

        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('Failed to load area count history');
        expect(wrapper.text()).not.toContain('No data available');
    });

    it('polls every minute', async () => {
        mocks.get.mockResolvedValue(mockSeries);

        mount(AreaCountHistoryWidget, {
            props: { organization: mockOrganization },
            global: { stubs: globalStubs },
        });

        expect(mocks.useIntervalFn).toHaveBeenCalledWith(expect.any(Function), 60_000, { immediateCallback: true });
    });

    it('uses selected dropdown range params', async () => {
        mocks.get.mockResolvedValue(mockSeries);

        const wrapper = mount(AreaCountHistoryWidget, {
            props: { organization: mockOrganization },
            global: { stubs: globalStubs },
        });

        await flushPromises();
        mocks.get.mockClear();

        await wrapper.find('[data-testid="range-select"]').setValue('30m');
        await nextTick();
        await flushPromises();

        expect(mocks.get).toHaveBeenCalledWith('peoplecount.area-count-history.index');
        expect(mocks.request.from).toBe('2025-08-04T21:38:00.000Z');
        expect(mocks.request.to).toBe('2025-08-04T22:08:00.000Z');

        await wrapper.find('[data-testid="range-select"]').setValue('yesterday');
        await flushPromises();

        const expectedTo = new Date();
        expectedTo.setHours(0, 0, 0, 0);
        const expectedFrom = new Date(expectedTo);
        expectedFrom.setDate(expectedFrom.getDate() - 1);

        expect(mocks.request.from).toBe(expectedFrom.toISOString());
        expect(mocks.request.to).toBe(expectedTo.toISOString());
    });

    it('queues a range change while a request is active', async () => {
        let resolveFirst!: (value: typeof mockSeries) => void;
        mocks.get
            .mockImplementationOnce(() => {
                mocks.request.processing = true;
                return new Promise((resolve) => {
                    resolveFirst = resolve;
                });
            })
            .mockReturnValue(new Promise(() => {}));
        const wrapper = mount(AreaCountHistoryWidget, {
            props: { organization: mockOrganization },
            global: { stubs: globalStubs },
        });

        await wrapper.find('[data-testid="range-select"]').setValue('6h');
        expect(mocks.get).toHaveBeenCalledOnce();

        mocks.request.processing = false;
        resolveFirst(mockSeries);
        await flushPromises();

        expect(mocks.get).toHaveBeenCalledTimes(2);
        expect(mocks.request.from).toBe('2025-08-04T16:08:00.000Z');
        expect(wrapper.find('.animate-pulse').exists()).toBe(true);
    });
});
