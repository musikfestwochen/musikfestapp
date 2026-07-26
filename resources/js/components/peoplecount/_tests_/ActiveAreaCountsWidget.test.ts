import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ActiveAreaCountsWidget from '../ActiveAreaCountsWidget.vue';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    request: { processing: false, get: vi.fn() },
    useIntervalFn: vi.fn(),
}));

// Mock Inertia's usePage
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => mocks.request,
    usePage: () => ({
        props: {
            auth: {
                permissions: ['peoplecount.area.view', 'peoplecount.areas.*'],
                global_permissions: [],
                roles: [],
            },
        },
    }),
}));

vi.mock('@vueuse/core', () => ({
    useIntervalFn: mocks.useIntervalFn,
}));

describe('ActiveAreaCountsWidget', () => {
    const mockOrganization = {
        id: 1,
        slug: 'test-org',
        name: 'Test Organization',
    };

    const mockAreaCounts = [
        {
            id: 1,
            name: 'Area 1',
            event_name: 'Event 1',
            count: 42,
            net_change: 5,
            net_change_time_ago: '30 seconds ago',
            debug_counts: {
                in: 50,
                out: 45,
                net: 5,
            },
            last_updated: '2025-08-04T22:00:00Z',
        },
        {
            id: 2,
            name: 'Area 2',
            event_name: 'Event 2',
            count: 123,
            net_change: -3,
            net_change_time_ago: '45 seconds ago',
            debug_counts: {
                in: 120,
                out: 123,
                net: -3,
            },
            last_updated: '2025-08-04T22:00:00Z',
        },
    ];

    beforeEach(() => {
        // Reset mocks before each test
        vi.resetAllMocks();
        mocks.request.processing = false;
        mocks.request.get = mocks.get;
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => name),
        );
        vi.useFakeTimers();
        mocks.useIntervalFn.mockImplementation((callback: () => Promise<void>) => {
            void callback();
            return { pause: vi.fn(), resume: vi.fn(), isActive: true };
        });

        const fixedNowDate = new Date('2025-08-04T22:08:00Z');
        vi.setSystemTime(fixedNowDate);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('renders loading state initially', async () => {
        mocks.get.mockReturnValue(new Promise(() => {}));

        const wrapper = mount(ActiveAreaCountsWidget, {
            props: {
                organization: mockOrganization,
            },
        });

        // Check that loading state is shown
        expect(wrapper.find('.h-24').exists()).toBe(true);
        expect(wrapper.findAll('.h-24')).toHaveLength(2); // Two skeleton loaders
    });

    it('fetches area counts on mount', async () => {
        mocks.get.mockResolvedValue(mockAreaCounts);

        mount(ActiveAreaCountsWidget, {
            props: {
                organization: mockOrganization,
            },
        });

        // Wait for the promise to resolve
        await flushPromises();

        expect(mocks.get).toHaveBeenCalledWith('peoplecount.area-aggregation.index');
    });

    it('displays area counts correctly', async () => {
        mocks.get.mockResolvedValue(mockAreaCounts);

        const wrapper = mount(ActiveAreaCountsWidget, {
            props: {
                organization: mockOrganization,
            },
        });

        // Wait for the promise to resolve
        await flushPromises();

        // Check that area counts are displayed correctly
        const areaItems = wrapper.findAll('.area-item');
        expect(areaItems).toHaveLength(2);

        // Check first area
        expect(areaItems[0].find('.font-medium').text()).toBe('Area 1');
        expect(areaItems[0].find('.text-muted-foreground').text()).toBe('Event 1');
        expect(areaItems[0].find('.count-display').text()).toBe('42');

        // Check second area
        expect(areaItems[1].find('.font-medium').text()).toBe('Area 2');
        expect(areaItems[1].find('.text-muted-foreground').text()).toBe('Event 2');
        expect(areaItems[1].find('.count-display').text()).toBe('123');

        // Check last updated time
        expect(wrapper.text()).toContain('Last refreshed:');
    });

    it('uses dark-mode styles for debug counts', async () => {
        mocks.get.mockResolvedValue(mockAreaCounts);
        const wrapper = mount(ActiveAreaCountsWidget, {
            props: { organization: mockOrganization },
        });
        await flushPromises();

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Debug Counts'))!
            .trigger('click');

        expect(wrapper.findAll('div').some((element) => element.classes().includes('dark:bg-emerald-950/20'))).toBe(true);
        expect(wrapper.findAll('div').some((element) => element.classes().includes('dark:bg-rose-950/20'))).toBe(true);
        expect(wrapper.findAll('div').some((element) => element.classes().includes('dark:bg-blue-950/30'))).toBe(true);
    });

    it('displays a message when no active areas are found', async () => {
        mocks.get.mockResolvedValue([]);

        const wrapper = mount(ActiveAreaCountsWidget, {
            props: {
                organization: mockOrganization,
            },
        });

        // Wait for the promise to resolve
        await flushPromises();

        // Check that "No active areas found" message is displayed
        expect(wrapper.text()).toContain('No active areas found');
    });

    it('keeps the empty state visible during background refresh', async () => {
        mocks.get.mockResolvedValueOnce([]);
        const wrapper = mount(ActiveAreaCountsWidget, { props: { organization: mockOrganization } });
        await flushPromises();

        mocks.get.mockReturnValueOnce(new Promise(() => {}));
        const refresh = mocks.useIntervalFn.mock.calls[0][0] as () => void;
        void refresh();

        expect(wrapper.text()).toContain('No active areas found');
        expect(wrapper.find('.animate-pulse').exists()).toBe(false);
    });

    it('displays an error message when API call fails', async () => {
        mocks.get.mockRejectedValue(new Error('API error'));

        const wrapper = mount(ActiveAreaCountsWidget, {
            props: {
                organization: mockOrganization,
            },
        });

        // Wait for the promise to reject
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('Failed to load area counts');
        expect(wrapper.text()).not.toContain('No active areas found');
    });

    it('polls every ten seconds', async () => {
        mocks.get.mockResolvedValue(mockAreaCounts);

        mount(ActiveAreaCountsWidget, {
            props: {
                organization: mockOrganization,
            },
        });

        expect(mocks.useIntervalFn).toHaveBeenCalledWith(expect.any(Function), 10_000, { immediateCallback: true });
    });

    it('shows a warning when data is more than one minute old', async () => {
        // Create a mock area count with a last_updated time more than 1 minute old
        const staleAreaCounts = [
            {
                ...mockAreaCounts[0],
                last_updated: '2025-08-04T22:06:59Z', // More than 1 minute old
            },
        ];

        mocks.get.mockResolvedValue(staleAreaCounts);

        const wrapper = mount(ActiveAreaCountsWidget, {
            props: {
                organization: mockOrganization,
            },
        });

        // Wait for the promise to resolve
        await flushPromises();

        expect(wrapper.get('[role="status"]').text()).toBe('Data may be stale.');
    });

    it('does not show a warning when data is less than one minute old', async () => {
        // Create a mock area count with a last_updated time less than 1 minute old
        const freshAreaCounts = [
            {
                ...mockAreaCounts[0],
                last_updated: '2025-08-04T22:07:01Z', // Less than 1 minute old
            },
        ];

        mocks.get.mockResolvedValue(freshAreaCounts);

        const wrapper = mount(ActiveAreaCountsWidget, {
            props: {
                organization: mockOrganization,
            },
        });

        // Wait for the promise to resolve
        await flushPromises();

        expect(wrapper.find('[role="status"]').exists()).toBe(false);
    });
});
