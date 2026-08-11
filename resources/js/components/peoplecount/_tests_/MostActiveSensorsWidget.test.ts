import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import MostActiveSensorsWidget from '../MostActiveSensorsWidget.vue';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    request: { processing: false, get: vi.fn() },
    useIntervalFn: vi.fn(),
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

vi.mock('@vueuse/core', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@vueuse/core')>()),
    useIntervalFn: mocks.useIntervalFn,
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
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-07-26T12:00:00Z'));
        mocks.useIntervalFn.mockImplementation((callback: () => Promise<void>) => {
            void callback();
            return { pause: vi.fn(), resume: vi.fn(), isActive: true };
        });
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('renders loading state initially', async () => {
        mocks.get.mockReturnValue(new Promise(() => {}));
        const wrapper = mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        expect(wrapper.findAll('.h-24')).toHaveLength(2);
    });

    it('fetches data with correct URL', async () => {
        mocks.get.mockResolvedValue([baseArea()]);
        const wrapper = mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(mocks.get).toHaveBeenCalledWith('peoplecount.most-active-sensors.index');
        expect(wrapper.get('time').attributes('datetime')).toBe('2025-08-09T18:00:00.000Z');
    });

    it('shows empty state when no data', async () => {
        mocks.get.mockResolvedValue([]);
        const wrapper = mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(wrapper.text()).toContain('No active areas or sensors.');
        expect(wrapper.find('time').exists()).toBe(false);
    });

    it('keeps the empty state visible during background refresh', async () => {
        mocks.get.mockResolvedValueOnce([]);
        const wrapper = mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        await flushPromises();

        mocks.get.mockReturnValueOnce(new Promise(() => {}));
        const refresh = mocks.useIntervalFn.mock.calls[0][0] as () => void;
        void refresh();

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
        expect(
            wrapper
                .get('[aria-label="Area sensor counts"]')
                .findAll('dd')
                .map((dd) => dd.text()),
        ).toEqual(['4', '1', '5']);
        expect(
            wrapper
                .findAll('button')
                .find((button) => button.text() === '10m')!
                .attributes('aria-pressed'),
        ).toBe('true');
        expect(
            wrapper
                .findAll('button')
                .find((button) => button.text() === '30m')!
                .attributes('aria-pressed'),
        ).toBe('false');
        expect(
            wrapper
                .findAll('button')
                .find((button) => button.text() === '10m')!
                .classes(),
        ).toEqual(expect.arrayContaining(['border', 'border-foreground/60']));
        expect(
            wrapper
                .findAll('button')
                .find((button) => button.text() === '30m')!
                .classes(),
        ).not.toContain('border');

        // Click 30m -> sensor with Total 4 should be first
        await wrapper
            .findAll('button')
            .find((b) => b.text() === '30m')!
            .trigger('click');
        await flushPromises();
        items = wrapper.findAll('li');
        expect(items[0].text()).toContain('Total: 4');
        expect(
            wrapper
                .get('[aria-label="Area sensor counts"]')
                .findAll('dd')
                .map((dd) => dd.text()),
        ).toEqual(['3', '3', '6']);
        expect(
            wrapper
                .findAll('button')
                .find((button) => button.text() === '30m')!
                .attributes('aria-pressed'),
        ).toBe('true');

        // Click 1h -> sensor with Total 10 should be first
        await wrapper
            .findAll('button')
            .find((b) => b.text() === '1h')!
            .trigger('click');
        await flushPromises();
        items = wrapper.findAll('li');
        expect(items[0].text()).toContain('Total: 10');
    });

    it('shows summaries for each area and all areas in the accordion', async () => {
        const sensorSums = (inCount: number, outCount: number) => ({
            '10m': { in: inCount, out: outCount, total: inCount + outCount },
            '30m': { in: inCount, out: outCount, total: inCount + outCount },
            '1h': { in: inCount, out: outCount, total: inCount + outCount },
            '2h': { in: inCount, out: outCount, total: inCount + outCount },
        });
        const area = (id: number, name: string, sensorId: number, inCount: number, outCount: number) =>
            baseArea({
                id,
                name,
                sensors: [{ id: sensorId, serial: `S-${sensorId}`, vendor: 'Axis', model: 'P8815-2', sums: sensorSums(inCount, outCount) }],
            });

        mocks.get.mockResolvedValue([area(1, 'Area 1', 1, 2, 1), area(2, 'Area 2', 2, 4, 3)]);
        const wrapper = mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        await flushPromises();

        expect(wrapper.find('[aria-label="Area 1 sensor counts"]').exists()).toBe(true);
        expect(wrapper.find('[aria-label="Area 2 sensor counts"]').exists()).toBe(false);
        expect(wrapper.find('[aria-label="Total sensor counts"]').exists()).toBe(false);
        expect(
            wrapper
                .findAll('[data-reka-collection-item]')
                .map((trigger) => trigger.text())
                .filter((text) => text.includes('Area') || text.includes('All areas')),
        ).toEqual([expect.stringContaining('Area 1'), expect.stringContaining('Area 2'), expect.stringContaining('All areas')]);

        await wrapper
            .findAll('[data-reka-collection-item]')
            .find((trigger) => trigger.text().includes('Area 2'))!
            .trigger('click');
        await flushPromises();

        expect(
            wrapper
                .get('[aria-label="Area 2 sensor counts"]')
                .findAll('dd')
                .map((dd) => dd.text()),
        ).toEqual(['4', '3', '7']);

        await wrapper
            .findAll('[data-reka-collection-item]')
            .find((trigger) => trigger.text().includes('All areas'))!
            .trigger('click');
        await flushPromises();

        expect(
            wrapper
                .get('[aria-label="Total sensor counts"]')
                .findAll('dd')
                .map((dd) => dd.text()),
        ).toEqual(['6', '4', '10']);
        expect(wrapper.text()).toContain('All areas');
        expect(wrapper.text()).toContain('Sensors assigned to multiple areas count once per assignment.');
    });

    it('shows error banner when API fails', async () => {
        mocks.get.mockRejectedValue(new Error('boom'));
        const wrapper = mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toBe('Failed to load most active sensors');
        expect(wrapper.text()).not.toContain('No active areas or sensors.');
    });

    it('polls every twenty seconds', async () => {
        mocks.get.mockResolvedValue([baseArea()]);
        mount(MostActiveSensorsWidget, { props: { organization: mockOrganization } });
        await flushPromises();
        expect(mocks.useIntervalFn).toHaveBeenCalledWith(expect.any(Function), 20_000, { immediateCallback: true });
    });
});
