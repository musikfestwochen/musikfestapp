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

    it('does not mount Stage Safety widgets without monitoring permission', () => {
        mocks.can.mockReturnValue(false);
        const wrapper = shallowMount(Dashboard, {
            props: { organization: { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' } },
            global: { stubs: { Layout: { template: '<main><slot /></main>' } } },
        });

        expect(wrapper.text()).not.toContain('Stage Safety');
        expect(wrapper.find('current-wind-widget-stub').exists()).toBe(false);
    });

    it('mounts all Stage Safety widgets with monitoring permission', () => {
        mocks.can.mockImplementation((permission: string) => permission === 'stage-safety.monitoring.view');
        const wrapper = shallowMount(Dashboard, {
            props: { organization: { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' } },
            global: { stubs: { Layout: { template: '<main><slot /></main>' } } },
        });

        expect(wrapper.text()).toContain('Stage Safety');
        expect(wrapper.text()).not.toContain('People Count');
        expect(wrapper.find('current-wind-widget-stub').exists()).toBe(true);
        expect(wrapper.find('sensor-health-widget-stub').exists()).toBe(true);
        expect(wrapper.find('wind-history-widget-stub').exists()).toBe(true);
    });
});
