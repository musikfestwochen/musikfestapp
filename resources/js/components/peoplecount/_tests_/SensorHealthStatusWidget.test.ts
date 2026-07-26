import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import SensorHealthStatusWidget from '../SensorHealthStatusWidget.vue';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    request: { processing: false, get: vi.fn() },
}));

// Mock Inertia's usePage (permissions are not directly used in the widget, but keep parity)
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => mocks.request,
    usePage: () => ({
        props: {
            auth: {
                permissions: ['peoplecount.area.view'],
                global_permissions: [],
                roles: [],
            },
        },
    }),
}));

describe('SensorHealthStatusWidget', () => {
    const mockOrganization = {
        id: 1,
        slug: 'test-org',
        name: 'Test Organization',
    };

    const basePayload = {
        last_updated: '2025-08-09T18:00:00Z',
        total: 0,
        all_healthy: true,
        healthy: [] as any[],
        suspicious: [] as any[],
        unhealthy: [] as any[],
    };

    beforeEach(() => {
        vi.resetAllMocks();
        mocks.request.processing = false;
        mocks.request.get = mocks.get;
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => name),
        );
        vi.useFakeTimers();
        // Silence error logs from components during negative-path tests
        vi.spyOn(console, 'error').mockImplementation(() => {});
        vi.spyOn(window, 'setInterval').mockImplementation(() => 123 as unknown as number);
        vi.spyOn(window, 'clearInterval').mockImplementation(() => {});

        const fixed = new Date('2025-08-09T18:02:00Z');
        vi.setSystemTime(fixed);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('renders loading state initially', async () => {
        mocks.get.mockReturnValue(new Promise(() => {}));

        const wrapper = mount(SensorHealthStatusWidget, {
            props: { organization: mockOrganization },
        });

        expect(wrapper.findAll('.h-24')).toHaveLength(2);
    });

    it('fetches health data on mount with correct url', async () => {
        mocks.get.mockResolvedValue(basePayload);

        mount(SensorHealthStatusWidget, { props: { organization: mockOrganization } });
        await flushPromises();

        expect(mocks.get).toHaveBeenCalledWith('peoplecount.sensor-health.index');
    });

    it('displays "No active sensors" when total is 0', async () => {
        mocks.get.mockResolvedValue({ ...basePayload, total: 0 });
        const wrapper = mount(SensorHealthStatusWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(wrapper.text()).toContain('No active sensors');
    });

    it('displays all healthy state', async () => {
        mocks.get.mockResolvedValue({
            ...basePayload,
            total: 2,
            all_healthy: true,
            healthy: [{ id: 1, serial: 'A', vendor: 'V', model: 'M', latest_ts: '2025-08-09T18:01:30Z', interval_counts: [] }],
        });
        const wrapper = mount(SensorHealthStatusWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(wrapper.text()).toContain('All 2 sensors are healthy');
        // recent -> pulse-green should be present when data is recent (<=2min)
        expect(wrapper.find('.pulse-green').exists()).toBe(true);
    });

    it('lists suspicious and unhealthy sensors', async () => {
        const payload = {
            ...basePayload,
            last_updated: '2025-08-09T18:02:00Z',
            total: 3,
            all_healthy: false,
            healthy: [],
            suspicious: [{ id: 1, serial: 'S-1', vendor: 'Axis', model: 'P8815-2', latest_ts: '2025-08-09T18:01:00Z', interval_counts: [] }],
            unhealthy: [{ id: 2, serial: 'U-1', vendor: 'Axis', model: 'P8815-2', latest_ts: '2025-08-09T17:58:00Z', interval_counts: [] }],
        };
        mocks.get.mockResolvedValue(payload);
        const wrapper = mount(SensorHealthStatusWidget, { props: { organization: mockOrganization } });
        await flushPromises();

        expect(wrapper.text()).toContain('Suspicious');
        expect(wrapper.text()).toContain('(1)');
        expect(wrapper.text()).toContain('Unhealthy');
        expect(wrapper.text()).toContain('S-1');
        expect(wrapper.text()).toContain('U-1');
    });

    it('shows error when API fails', async () => {
        mocks.get.mockRejectedValue(new Error('boom'));
        const wrapper = mount(SensorHealthStatusWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(wrapper.find('.text-red-500').exists()).toBe(true);
        expect(wrapper.text()).toContain('Failed to load sensor health');
    });

    it('applies stale-card when last_updated older than 2 minutes', async () => {
        mocks.get.mockResolvedValue({ ...basePayload, last_updated: '2025-08-09T17:59:59Z', total: 0 });
        const wrapper = mount(SensorHealthStatusWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(wrapper.find('.stale-card').exists()).toBe(true);
    });

    it('sets up and cleans up auto-refresh', async () => {
        mocks.get.mockResolvedValue(basePayload);
        const wrapper = mount(SensorHealthStatusWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(window.setInterval).toHaveBeenCalledWith(expect.any(Function), 10000);
        wrapper.unmount();
        expect(window.clearInterval).toHaveBeenCalledWith(123);
    });
});
