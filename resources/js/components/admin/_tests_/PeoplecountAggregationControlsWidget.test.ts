import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PeoplecountAggregationControlsWidget from '../PeoplecountAggregationControlsWidget.vue';

const mocks = vi.hoisted(() => ({
    can: vi.fn(),
}));

vi.mock('@/composables/usePermissions', () => ({
    usePermissions: () => ({ can: mocks.can }),
}));

const ConfirmActionButton = {
    name: 'ConfirmActionButton',
    props: ['href', 'method', 'label'],
    template: '<button>{{ label }}</button>',
};

describe('PeoplecountAggregationControlsWidget', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => name),
        );
    });

    it('uses the shared shell without an update footer', () => {
        mocks.can.mockReturnValue(true);
        const wrapper = mount(PeoplecountAggregationControlsWidget, {
            global: { stubs: { ConfirmActionButton }, mocks: { route: (name: string) => name } },
        });

        expect(wrapper.get('h3').text()).toBe('Peoplecount Aggregations');
        expect(wrapper.text()).toContain('Update aggregated area counts outside the scheduler.');
        expect(wrapper.text()).toContain('Update Aggregations');
        expect(wrapper.text()).toContain('Rebuild Aggregations');
        expect(wrapper.find('time').exists()).toBe(false);

        const actions = wrapper.findAllComponents(ConfirmActionButton);
        expect(actions[0].props()).toMatchObject({ href: 'admin.peoplecount-aggregations.update', method: 'patch' });
        expect(actions[1].props()).toMatchObject({ href: 'admin.peoplecount-aggregations.destroy', method: 'delete' });
    });

    it('only renders permitted actions', () => {
        mocks.can.mockImplementation((permission: string) => permission === 'admin.peoplecount_aggregations.update');
        const wrapper = mount(PeoplecountAggregationControlsWidget, {
            global: { stubs: { ConfirmActionButton }, mocks: { route: (name: string) => name } },
        });

        expect(wrapper.text()).toContain('Update Aggregations');
        expect(wrapper.text()).not.toContain('Rebuild Aggregations');
    });

    it('does not render without an aggregation permission', () => {
        mocks.can.mockReturnValue(false);
        const wrapper = mount(PeoplecountAggregationControlsWidget, {
            global: { stubs: { ConfirmActionButton }, mocks: { route: (name: string) => name } },
        });

        expect(wrapper.find('h3').exists()).toBe(false);
    });
});
