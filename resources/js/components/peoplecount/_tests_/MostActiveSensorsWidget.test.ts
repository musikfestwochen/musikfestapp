import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import MostActiveSensorsWidget from '../MostActiveSensorsWidget.vue';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    request: { processing: false, get: vi.fn() },
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => mocks.request,
    usePage: () => ({
        props: {
            auth: {
                permissions: ['peoplecount.widgets.most_active_sensors'],
                global_permissions: [],
                roles: [],
            },
        },
    }),
}));

describe('MostActiveSensorsWidget', () => {
    const mockOrganization = { id: 1, slug: 'test-org', name: 'Test Org' };

    const baseArea = (override?: Partial<any>) => ({
        id: 1,
        name: 'Area 1',
        event_name: 'Event',
        last_updated: '2025-08-09T18:00:00Z',
        sensors: [],
        ...override,
    });

    beforeEach(() => {
        vi.resetAllMocks();
        mocks.request.processing = false;
        mocks.request.get = mocks.get;
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => name),
        );
        // Silence error logs from components during negative-path tests
        vi.spyOn(console, 'error').mockImplementation(() => {});
        vi.spyOn(window, 'setInterval').mockImplementation(() => 123 as unknown as number);
        vi.spyOn(window, 'clearInterval').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders loading state initially', async () => {
        mocks.get.mockReturnValue(new Promise(() => {}));
        const wrapper = mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        expect(wrapper.findAll('.h-24')).toHaveLength(2);
    });

    it('fetches data with correct URL', async () => {
        mocks.get.mockResolvedValue([baseArea()]);
        mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(mocks.get).toHaveBeenCalledWith('peoplecount.most-active-sensors.index');
    });

    it('shows empty state when no data', async () => {
        mocks.get.mockResolvedValue([]);
        const wrapper = mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(wrapper.text()).toContain('No active areas or sensors.');
    });

    it('keeps the empty state visible during background refresh', async () => {
        mocks.get.mockResolvedValueOnce([]);
        const wrapper = mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        await flushPromises();

        mocks.get.mockReturnValueOnce(new Promise(() => {}));
        const refresh = vi.mocked(window.setInterval).mock.calls[0][0] as () => void;
        refresh();

        expect(wrapper.text()).toContain('No active areas or sensors.');
        expect(wrapper.find('.animate-pulse').exists()).toBe(false);
    });

    it('sorts sensors by selected range total desc and toggles on click', async () => {
        const area = baseArea({
            sensors: [
                {
                    id: 1,
                    serial: 'A',
                    vendor: 'Axis',
                    model: 'P8815-2',
                    sums: {
                        '10m': { in: 1, out: 1, total: 2 },
                        '30m': { in: 2, out: 2, total: 4 },
                        '1h': { in: 3, out: 3, total: 6 },
                        '2h': { in: 4, out: 4, total: 8 },
                    },
                },
                {
                    id: 2,
                    serial: 'B',
                    vendor: 'Axis',
                    model: 'P8815-2',
                    sums: {
                        '10m': { in: 3, out: 0, total: 3 },
                        '30m': { in: 1, out: 1, total: 2 },
                        '1h': { in: 10, out: 0, total: 10 },
                        '2h': { in: 1, out: 0, total: 1 },
                    },
                },
            ],
        });
        mocks.get.mockResolvedValue([area]);
        const wrapper = mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        await flushPromises();

        // Default selectedRange = '10m' -> sensor with Total 3 should be first
        let items = wrapper.findAll('li');
        expect(items[0].text()).toContain('Total: 3');

        // Click 30m -> sensor with Total 4 should be first
        await wrapper
            .findAll('button')
            .find((b) => b.text() === '30m')!
            .trigger('click');
        await flushPromises();
        items = wrapper.findAll('li');
        expect(items[0].text()).toContain('Total: 4');

        // Click 1h -> sensor with Total 10 should be first
        await wrapper
            .findAll('button')
            .find((b) => b.text() === '1h')!
            .trigger('click');
        await flushPromises();
        items = wrapper.findAll('li');
        expect(items[0].text()).toContain('Total: 10');
    });

    it('shows error banner when API fails', async () => {
        mocks.get.mockRejectedValue(new Error('boom'));
        const wrapper = mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(wrapper.find('.text-red-500').exists()).toBe(true);
        expect(wrapper.text()).toContain('Failed to load most active sensors');
    });

    it('sets up and cleans up auto-refresh', async () => {
        mocks.get.mockResolvedValue([baseArea()]);
        const wrapper = mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(window.setInterval).toHaveBeenCalledWith(expect.any(Function), 10000);
        wrapper.unmount();
        expect(window.clearInterval).toHaveBeenCalledWith(123);
    });
});
