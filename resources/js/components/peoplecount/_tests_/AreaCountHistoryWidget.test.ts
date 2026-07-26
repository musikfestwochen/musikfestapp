import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import AreaCountHistoryWidget from '../AreaCountHistoryWidget.vue';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    request: { processing: false, get: vi.fn(), from: '', to: '' },
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
    VisXYContainer: { template: '<div><slot /></div>' },
    VisLine: { template: '<div></div>' },
    VisAxis: { template: '<div></div>' },
    ChartContainer: { template: '<div><slot /></div>' },
    ChartCrosshair: { template: '<div></div>' },
    ChartTooltip: { template: '<div></div>' },
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

        vi.spyOn(console, 'error').mockImplementation(() => {});

        vi.spyOn(window, 'setInterval').mockImplementation(() => 999 as unknown as number);
        vi.spyOn(window, 'clearInterval').mockImplementation(() => {});

        vi.setSystemTime(new Date('2025-08-04T22:08:00Z'));

        localStorage.clear();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('fetches history on mount with default range', async () => {
        mocks.get.mockResolvedValue(mockSeries);

        mount(AreaCountHistoryWidget, {
            props: { organization: mockOrganization },
            global: { stubs: globalStubs },
        });

        await flushPromises();

        expect(mocks.get).toHaveBeenCalledWith('peoplecount.area-count-history.index');
        expect(mocks.request.from).toBe('2025-08-04T21:08:00.000Z');
        expect(mocks.request.to).toBe('2025-08-04T22:08:00.000Z');
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

        expect(wrapper.find('.text-red-500').exists()).toBe(true);
        expect(wrapper.find('.text-red-500').text()).toBe('Failed to load area count history');
    });

    it('sets up and cleans up auto-refresh', async () => {
        mocks.get.mockResolvedValue(mockSeries);

        const wrapper = mount(AreaCountHistoryWidget, {
            props: { organization: mockOrganization },
            global: { stubs: globalStubs },
        });

        expect(window.setInterval).toHaveBeenCalledWith(expect.any(Function), 30000);

        wrapper.unmount();

        expect(window.clearInterval).toHaveBeenCalledWith(999);
    });

    it('uses selected dropdown range params', async () => {
        mocks.get.mockResolvedValue(mockSeries);

        const wrapper = mount(AreaCountHistoryWidget, {
            props: { organization: mockOrganization },
            global: { stubs: globalStubs },
        });

        await flushPromises();
        mocks.get.mockClear();

        await wrapper.find('[data-testid="range-select"]').setValue('6h');
        await nextTick();
        await flushPromises();

        expect(mocks.get).toHaveBeenCalledWith('peoplecount.area-count-history.index');
        expect(mocks.request.from).toBe('2025-08-04T16:08:00.000Z');
        expect(mocks.request.to).toBe('2025-08-04T22:08:00.000Z');
    });
});
