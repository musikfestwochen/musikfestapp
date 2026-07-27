import SensorHealthWidget from '@/components/SensorHealthWidget.vue';
import { shallowMount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dashboard from '../Dashboard.vue';

const mocks = vi.hoisted(() => ({ can: vi.fn() }));

vi.mock('@/composables/usePermissions', () => ({
    usePermissions: () => ({ can: mocks.can }),
}));

vi.mock('@inertiajs/vue3', () => ({ Head: { template: '<div />' } }));

describe('Peoplecount dashboard', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders permitted Peoplecount widgets', () => {
        mocks.can.mockReturnValue(true);
        const wrapper = shallowMount(Dashboard, {
            props: { organization: { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' } },
            global: { stubs: { Layout: { template: '<main><slot /></main>' } } },
        });

        expect(Array.from(wrapper.find('.grid').element.children).map((element) => element.tagName.toLowerCase())).toEqual([
            'active-area-counts-widget-stub',
            'most-active-sensors-widget-stub',
            'sensor-health-widget-stub',
            'area-count-history-widget-stub',
        ]);
        expect(wrapper.findComponent(SensorHealthWidget).props()).toMatchObject({ showPeoplecount: true, showStageSafety: false });
    });

    it('does not mount unpermitted widgets', () => {
        mocks.can.mockReturnValue(false);
        const wrapper = shallowMount(Dashboard, {
            props: { organization: { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' } },
            global: { stubs: { Layout: { template: '<main><slot /></main>' } } },
        });

        expect(wrapper.find('.grid').element.children).toHaveLength(0);
    });
});
