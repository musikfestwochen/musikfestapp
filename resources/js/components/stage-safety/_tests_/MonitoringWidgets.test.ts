import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CurrentWindWidget from '../CurrentWindWidget.vue';
import WindHistoryWidget from '../WindHistoryWidget.vue';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    request: { processing: false, get: vi.fn(), from: '', to: '' },
    useIntervalFn: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => mocks.request,
}));

vi.mock('@vueuse/core', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@vueuse/core')>()),
    useIntervalFn: mocks.useIntervalFn,
}));

const organization = { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' };
const historyStubs = {
    Select: {
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: '<select data-testid="range" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><slot /></select>',
    },
    SelectTrigger: { template: '<div><slot /></div>' },
    SelectValue: { template: '<div />' },
    SelectContent: { template: '<div><slot /></div>' },
    SelectItem: { props: ['value'], template: '<option :value="value"><slot /></option>' },
    VisXYContainer: { template: '<div><slot /></div>' },
    VisLine: { template: '<div />' },
    VisAxis: { template: '<div />' },
    ChartContainer: { template: '<div><slot /></div>' },
    ChartCrosshair: { template: '<div />' },
    ChartTooltip: { template: '<div />' },
};

describe('Stage Safety monitoring widgets', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-07-25T12:00:00Z'));
        mocks.request.processing = false;
        mocks.request.from = '';
        mocks.request.to = '';
        mocks.request.get = mocks.get;
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => name),
        );
        mocks.useIntervalFn.mockImplementation((callback: () => Promise<void>) => {
            void callback();
            return { pause: vi.fn(), resume: vi.fn(), isActive: true };
        });
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('loads current wind through its endpoint every ten seconds', async () => {
        mocks.get.mockResolvedValue({ generated_at: '', sensors: [] });
        const wrapper = mount(CurrentWindWidget, { props: { organization } });
        await flushPromises();

        expect(mocks.get).toHaveBeenCalledWith('stage-safety.current-wind.index');
        expect(mocks.useIntervalFn).toHaveBeenCalledWith(expect.any(Function), 10_000, { immediateCallback: true });
        expect(wrapper.text()).toContain('No sensors currently report fresh wind data');
        expect(wrapper.text()).toContain('Last refreshed:');
        expect(wrapper.get('time').attributes('datetime')).toBe('2026-07-25T12:00:00.000Z');
    });

    it('shows initial loading while the current wind request is pending', () => {
        mocks.get.mockReturnValue(new Promise(() => {}));
        const wrapper = mount(CurrentWindWidget, { props: { organization } });

        expect(mocks.get).toHaveBeenCalledOnce();
        expect(wrapper.find('.animate-pulse').exists()).toBe(true);
    });

    it('shows a current wind request error', async () => {
        mocks.get.mockRejectedValue(new Error('network'));
        const wrapper = mount(CurrentWindWidget, { props: { organization } });
        await flushPromises();

        expect(wrapper.text()).toContain('Failed to load current wind');
        expect(wrapper.get('[role="alert"]').text()).toBe('Failed to load current wind.');
        expect(wrapper.text()).not.toContain('No sensors currently report fresh wind data');
    });

    it('shows a wind history request error without an empty state', async () => {
        mocks.get.mockRejectedValue(new Error('network'));
        const wrapper = mount(WindHistoryWidget, {
            props: { organization },
            global: { stubs: historyStubs },
        });
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('Failed to load wind history.');
        expect(wrapper.text()).not.toContain('No wind readings');
    });

    it('loads bounded history independently every thirty seconds', async () => {
        mocks.get.mockResolvedValue({
            generated_at: '',
            from: '2026-07-25T11:00:00Z',
            to: '2026-07-25T12:00:00Z',
            sensors: [],
        });
        mount(WindHistoryWidget, {
            props: { organization },
            global: { stubs: historyStubs },
        });
        await flushPromises();

        expect(mocks.get).toHaveBeenCalledWith('stage-safety.wind-history.index');
        expect(mocks.request.from).toBe('2026-07-25T11:00:00.000Z');
        expect(mocks.request.to).toBe('2026-07-25T12:00:00.000Z');
        expect(mocks.useIntervalFn).toHaveBeenCalledWith(expect.any(Function), 30_000, { immediateCallback: true });
    });

    it('keeps current history visible while another range loads', async () => {
        const current = {
            generated_at: '',
            from: '2026-07-25T11:00:00Z',
            to: '2026-07-25T12:00:00Z',
            sensors: [],
        };
        mocks.get.mockResolvedValueOnce(current).mockReturnValueOnce(new Promise(() => {}));
        const wrapper = mount(WindHistoryWidget, {
            props: { organization },
            global: { stubs: historyStubs },
        });
        await flushPromises();

        await wrapper.get('[data-testid="range"]').setValue('6h');

        expect(wrapper.text()).toContain('No wind readings');
        expect(wrapper.find('.animate-pulse').exists()).toBe(false);
    });

    it('queues a changed history range while a request is active', async () => {
        let resolveFirst!: (value: { generated_at: string; from: string; to: string; sensors: never[] }) => void;
        mocks.get
            .mockImplementationOnce(() => {
                mocks.request.processing = true;
                return new Promise((resolve) => {
                    resolveFirst = resolve;
                });
            })
            .mockReturnValue(new Promise(() => {}));
        const wrapper = mount(WindHistoryWidget, {
            props: { organization },
            global: { stubs: historyStubs },
        });
        await wrapper.get('[data-testid="range"]').setValue('6h');
        mocks.request.processing = false;
        resolveFirst({ generated_at: '', from: '', to: '', sensors: [] });
        await flushPromises();

        expect(mocks.get).toHaveBeenCalledTimes(2);
        expect(mocks.request.from).toBe('2026-07-25T06:00:00.000Z');
        expect(wrapper.find('.animate-pulse').exists()).toBe(true);
    });

    it('ignores an error from a stale history range', async () => {
        let rejectFirst!: (reason: Error) => void;
        mocks.get
            .mockImplementationOnce(() => {
                mocks.request.processing = true;
                return new Promise((_, reject) => {
                    rejectFirst = reject;
                });
            })
            .mockReturnValueOnce(new Promise(() => {}));
        const wrapper = mount(WindHistoryWidget, {
            props: { organization },
            global: { stubs: historyStubs },
        });
        await wrapper.get('[data-testid="range"]').setValue('6h');
        mocks.request.processing = false;
        rejectFirst(new Error('stale request failed'));
        await flushPromises();

        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });
});
