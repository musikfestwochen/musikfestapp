import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import SensorHealthStatusWidget from '../SensorHealthStatusWidget.vue';

// Mock axios
vi.mock('axios');

// Mock Inertia's usePage (permissions are not directly used in the widget, but keep parity)
vi.mock('@inertiajs/vue3', () => ({
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
        vi.spyOn(window, 'setInterval').mockImplementation(() => 123 as unknown as number);
        vi.spyOn(window, 'clearInterval').mockImplementation(() => {});

        // Fix Date.now and Date constructor
        const fixed = new Date('2025-08-09T18:02:00Z');
        const RealDate = global.Date;
        class MockDate extends RealDate {
            constructor(...args: any[]) {
                if (args.length === 0) {
                    super(fixed);
                } else {
                    // @ts-expect-error mocking Date constructor signature for test
                    super(...args);
                }
            }
        }
        vi.spyOn(global, 'Date').mockImplementation((...args: any[]) => new MockDate(...args) as any);
        vi.spyOn(Date, 'now').mockReturnValue(fixed.getTime());
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders loading state initially', async () => {
        vi.mocked(axios.get).mockReturnValue(new Promise(() => {}));

        const wrapper = mount(SensorHealthStatusWidget, {
            props: { organization: mockOrganization },
        });

        expect(wrapper.findAll('.h-24')).toHaveLength(2);
    });

    it('fetches health data on mount with correct url', async () => {
        vi.mocked(axios.get).mockResolvedValue({ data: basePayload });

        mount(SensorHealthStatusWidget, { props: { organization: mockOrganization } });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith(`/${mockOrganization.slug}/peoplecount/sensor-health`);
    });

    it('displays "No active sensors" when total is 0', async () => {
        vi.mocked(axios.get).mockResolvedValue({ data: { ...basePayload, total: 0 } });
        const wrapper = mount(SensorHealthStatusWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(wrapper.text()).toContain('No active sensors');
    });

    it('displays all healthy state', async () => {
        vi.mocked(axios.get).mockResolvedValue({
            data: {
                ...basePayload,
                total: 2,
                all_healthy: true,
                healthy: [{ id: 1, serial: 'A', vendor: 'V', model: 'M', latest_ts: '2025-08-09T18:01:30Z', interval_counts: [] }],
            },
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
        vi.mocked(axios.get).mockResolvedValue({ data: payload });
        const wrapper = mount(SensorHealthStatusWidget, { props: { organization: mockOrganization } });
        await flushPromises();

        expect(wrapper.text()).toContain('Suspicious');
        expect(wrapper.text()).toContain('(1)');
        expect(wrapper.text()).toContain('Unhealthy');
        expect(wrapper.text()).toContain('S-1');
        expect(wrapper.text()).toContain('U-1');
    });

    it('shows error when API fails', async () => {
        vi.mocked(axios.get).mockRejectedValue(new Error('boom'));
        const wrapper = mount(SensorHealthStatusWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(wrapper.find('.text-red-500').exists()).toBe(true);
        expect(wrapper.text()).toContain('Failed to load sensor health');
    });

    it('applies stale-card when last_updated older than 2 minutes', async () => {
        vi.mocked(axios.get).mockResolvedValue({ data: { ...basePayload, last_updated: '2025-08-09T17:59:59Z', total: 0 } });
        const wrapper = mount(SensorHealthStatusWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(wrapper.find('.stale-card').exists()).toBe(true);
    });

    it('sets up and cleans up auto-refresh', async () => {
        vi.mocked(axios.get).mockResolvedValue({ data: basePayload });
        const wrapper = mount(SensorHealthStatusWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(window.setInterval).toHaveBeenCalledWith(expect.any(Function), 10000);
        wrapper.unmount();
        expect(window.clearInterval).toHaveBeenCalledWith(123);
    });
});
