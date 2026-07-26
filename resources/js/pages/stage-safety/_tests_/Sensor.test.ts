import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Sensor from '../Sensor.vue';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    request: { processing: false, get: vi.fn(), from: '', to: '' },
    useIntervalFn: vi.fn(),
}));

vi.mock('@/composables/usePermissions', () => ({
    usePermissions: () => ({ can: () => false }),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
    useHttp: () => mocks.request,
}));

vi.mock('@vueuse/core', () => ({
    useIntervalFn: mocks.useIntervalFn,
}));

const organization = { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' };
const sensor = {
    id: 1,
    organization_id: 1,
    manufacturer: 'Broadweigh',
    model: 'BW-WSS',
    identifier: 'ABC123',
    name: 'Main Stage',
    location: 'Roof',
    stale_after_seconds: 300,
    archived_at: null,
    created_at: '',
    updated_at: '',
};

describe('Stage Safety sensor monitoring page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mocks.request.processing = false;
        mocks.request.get = mocks.get;
        mocks.useIntervalFn.mockImplementation((callback: () => Promise<void>) => {
            void callback();
            return { pause: vi.fn(), resume: vi.fn(), isActive: true };
        });
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => name),
        );
    });

    it('shows newest-frame radio diagnostics and location-first title', async () => {
        mocks.get.mockResolvedValue({
            generated_at: '2026-07-25T12:00:00Z',
            current: {
                sensor: { id: 1, identifier: 'ABC123', name: 'Main Stage', location: 'Roof', stale_after_seconds: 300 },
                status: 'fresh',
                latest_observed_at: '2026-07-25T12:00:00Z',
                radio_diagnostics: { battery_low: true, rssi_dbm: -70, cv: 103 },
                wind_average: null,
                wind_gust: null,
            },
            history: {
                generated_at: '2026-07-25T12:00:00Z',
                from: '2026-07-25T11:00:00Z',
                to: '2026-07-25T12:00:00Z',
                sensors: [],
            },
        });

        const wrapper = mount(Sensor, {
            props: { organization, sensor },
            global: {
                stubs: {
                    Layout: { template: '<main><slot /></main>' },
                    CurrentWindDisplay: true,
                    WindHistoryChart: true,
                },
            },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Roof');
        expect(wrapper.text()).toContain('-70 dBm');
        expect(wrapper.text()).toContain('103');
        expect(wrapper.text()).toContain('Low');
    });

    it('keeps loading and ignores stale errors while a changed range is queued', async () => {
        let rejectFirst!: (reason: Error) => void;
        mocks.get
            .mockImplementationOnce(() => {
                mocks.request.processing = true;
                return new Promise((_, reject) => {
                    rejectFirst = reject;
                });
            })
            .mockReturnValueOnce(new Promise(() => {}));
        const wrapper = mount(Sensor, {
            props: { organization, sensor },
            global: {
                stubs: {
                    Layout: { template: '<main><slot /></main>' },
                    CurrentWindDisplay: true,
                    WindHistoryChart: {
                        emits: ['update:timeRange'],
                        template: '<button data-testid="range" @click="$emit(\'update:timeRange\', \'6h\')">range</button>',
                    },
                },
            },
        });

        await wrapper.find('[data-testid="range"]').trigger('click');
        mocks.request.processing = false;
        rejectFirst(new Error('stale request failed'));
        await flushPromises();

        expect(wrapper.text()).not.toContain('Failed to load sensor monitoring data');
        expect(wrapper.find('.animate-pulse').exists()).toBe(true);
    });
});
