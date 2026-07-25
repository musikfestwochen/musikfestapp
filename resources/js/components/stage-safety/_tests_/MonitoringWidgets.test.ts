import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CurrentWindWidget from '../CurrentWindWidget.vue';
import SensorHealthWidget from '../SensorHealthWidget.vue';
import WindHistoryWidget from '../WindHistoryWidget.vue';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    request: { processing: false, get: vi.fn(), from: '', to: '' },
    useIntervalFn: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => mocks.request,
}));

vi.mock('@vueuse/core', () => ({
    useIntervalFn: mocks.useIntervalFn,
}));

const organization = { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' };

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
    });

    it('does not overlap current wind requests', async () => {
        mocks.request.processing = true;
        mount(CurrentWindWidget, { props: { organization } });
        await flushPromises();

        expect(mocks.get).not.toHaveBeenCalled();
    });

    it('shows a current wind request error', async () => {
        mocks.get.mockRejectedValue(new Error('network'));
        const wrapper = mount(CurrentWindWidget, { props: { organization } });
        await flushPromises();

        expect(wrapper.text()).toContain('Failed to load current wind');
    });

    it('loads and renders stale sensor health', async () => {
        mocks.get.mockResolvedValue({
            generated_at: '',
            total: 1,
            all_fresh: false,
            fresh: [],
            stale: [
                {
                    id: 1,
                    identifier: 'ABC123',
                    name: 'Main Stage',
                    location: null,
                    stale_after_seconds: 300,
                    status: 'stale',
                    latest_observed_at: '2026-07-25T11:00:00Z',
                },
            ],
            never_seen: [],
        });
        const wrapper = mount(SensorHealthWidget, { props: { organization } });
        await flushPromises();

        expect(mocks.get).toHaveBeenCalledWith('stage-safety.sensor-health.index');
        expect(mocks.useIntervalFn).toHaveBeenCalledWith(expect.any(Function), 10_000, { immediateCallback: true });
        expect(wrapper.text()).toContain('Main Stage');
        expect(wrapper.text()).toContain('stale');
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
            global: { stubs: { WindHistoryChart: true } },
        });
        await flushPromises();

        expect(mocks.get).toHaveBeenCalledWith('stage-safety.wind-history.index');
        expect(mocks.request.from).toBe('2026-07-25T11:00:00.000Z');
        expect(mocks.request.to).toBe('2026-07-25T12:00:00.000Z');
        expect(mocks.useIntervalFn).toHaveBeenCalledWith(expect.any(Function), 30_000, { immediateCallback: true });
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
            .mockResolvedValue({ generated_at: '', from: '', to: '', sensors: [] });
        const wrapper = mount(WindHistoryWidget, {
            props: { organization },
            global: {
                stubs: {
                    WindHistoryChart: {
                        emits: ['update:timeRange'],
                        template: '<button data-testid="range" @click="$emit(\'update:timeRange\', \'6h\')">range</button>',
                    },
                },
            },
        });
        await wrapper.find('[data-testid="range"]').trigger('click');
        mocks.request.processing = false;
        resolveFirst({ generated_at: '', from: '', to: '', sensors: [] });
        await flushPromises();

        expect(mocks.get).toHaveBeenCalledTimes(2);
        expect(mocks.request.from).toBe('2026-07-25T06:00:00.000Z');
    });
});
