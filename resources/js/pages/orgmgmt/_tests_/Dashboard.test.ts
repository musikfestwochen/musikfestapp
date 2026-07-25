import SensorHealthWidget from '@/components/SensorHealthWidget.vue';
import { shallowMount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dashboard from '../Dashboard.vue';

const mocks = vi.hoisted(() => ({ can: vi.fn() }));

vi.mock('@/composables/usePermissions', () => ({
    usePermissions: () => ({ can: mocks.can }),
}));

vi.mock('@inertiajs/vue3', () => ({ Head: { template: '<div />' } }));

describe('organization dashboard', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('does not mount widgets without their permissions', () => {
        mocks.can.mockReturnValue(false);
        const wrapper = shallowMount(Dashboard, {
            props: { organization: { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' } },
            global: { stubs: { Layout: { template: '<main><slot /></main>' } } },
        });

        expect(wrapper.find('current-wind-widget-stub').exists()).toBe(false);
        expect(wrapper.find('sensor-health-widget-stub').exists()).toBe(false);
    });

    it('mounts Stage Safety widgets and configures combined health with Stage access only', () => {
        mocks.can.mockImplementation((permission: string) => permission === 'stage-safety.monitoring.view');
        const wrapper = shallowMount(Dashboard, {
            props: { organization: { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' } },
            global: { stubs: { Layout: { template: '<main><slot /></main>' } } },
        });

        expect(wrapper.find('current-wind-widget-stub').exists()).toBe(true);
        expect(wrapper.findComponent(SensorHealthWidget).props()).toMatchObject({ showPeoplecount: false, showStageSafety: true });
        expect(wrapper.find('wind-history-widget-stub').exists()).toBe(true);
    });

    it('renders all widgets once in the requested order', () => {
        mocks.can.mockReturnValue(true);
        const wrapper = shallowMount(Dashboard, {
            props: { organization: { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' } },
            global: { stubs: { Layout: { template: '<main><slot /></main>' } } },
        });

        expect(Array.from(wrapper.find('.grid').element.children).map((element) => element.tagName.toLowerCase())).toEqual([
            'active-area-counts-widget-stub',
            'most-active-sensors-widget-stub',
            'current-wind-widget-stub',
            'sensor-health-widget-stub',
            'area-count-history-widget-stub',
            'wind-history-widget-stub',
        ]);
        expect(wrapper.findComponent(SensorHealthWidget).props()).toMatchObject({ showPeoplecount: true, showStageSafety: true });
    });

    it('mounts combined health for Peoplecount access only', () => {
        mocks.can.mockImplementation((permission: string) => permission === 'peoplecount.widgets.sensor_health');
        const wrapper = shallowMount(Dashboard, {
            props: { organization: { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' } },
            global: { stubs: { Layout: { template: '<main><slot /></main>' } } },
        });

        expect(wrapper.findComponent(SensorHealthWidget).props()).toMatchObject({ showPeoplecount: true, showStageSafety: false });
        expect(wrapper.find('current-wind-widget-stub').exists()).toBe(false);
    });
});
