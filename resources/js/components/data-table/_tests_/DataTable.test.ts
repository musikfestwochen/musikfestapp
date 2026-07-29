import type { ColumnDef } from '@tanstack/vue-table';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import DataTable from '../DataTable.vue';

vi.mock('@inertiajs/vue3', () => ({
    router: { visit: vi.fn() },
}));

const columns: ColumnDef<unknown>[] = [
    {
        id: 'name',
        header: 'Name',
        cell: () => 'Example',
    },
];

describe('DataTable responsive actions', () => {
    it('places one action declaration beside the mobile heading and in the desktop toolbar', () => {
        const wrapper = mount(DataTable, {
            props: {
                columns,
                data: [{ name: 'Example' }],
                title: 'Examples',
                description: 'Manage examples',
            },
            slots: {
                actions: '<button data-testid="primary-action">Create Example</button>',
                'heading-actions': '<button data-testid="secondary-action">Show Archived</button>',
            },
        });

        const primaryActions = wrapper.findAll('[data-testid="primary-action"]');

        expect(wrapper.get('h2').text()).toBe('Examples');
        expect(wrapper.text()).toContain('Manage examples');
        expect(primaryActions).toHaveLength(2);
        expect(primaryActions.some((action) => action.element.parentElement?.classList.contains('lg:hidden'))).toBe(true);
        expect(primaryActions.some((action) => action.element.parentElement?.classList.contains('hidden'))).toBe(true);
        expect(wrapper.findAll('[data-testid="secondary-action"]')).toHaveLength(1);
    });
});
