import type { Organization, User } from '@/types';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { usersColumns } from '../columns';
import UsersTable from '../UsersTable.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { visit: vi.fn() },
    usePage: () => ({
        props: {
            auth: { permissions: [], global_permissions: [], roles: [] },
        },
    }),
}));

const organization: Organization = {
    id: 1,
    name: 'Musikfest',
    slug: 'musikfest',
    created_at: '',
    updated_at: '',
};

function user(overrides: Partial<User>): User {
    return {
        id: 1,
        name: 'Alex Example',
        email: 'alex@example.test',
        eastereggs_activated: false,
        email_verified_at: null,
        created_at: '',
        updated_at: '',
        organization_roles: [],
        ...overrides,
    };
}

const users = [
    user({
        id: 1,
        name: 'Alex Admin',
        email: 'admin@example.test',
        organization_roles: [
            {
                name: 'OrganizationAdmin',
                display_name: 'Organization administrator',
                description: 'Can manage organization users.',
            },
            { name: 'PeopleCountViewer', display_name: null, description: null },
        ],
    }),
    user({
        id: 2,
        name: 'Sam Safety',
        email: 'safety@example.test',
        organization_roles: [{ name: 'StageSafetyViewer', display_name: 'Stage Safety viewer', description: null }],
    }),
    user({ id: 3, name: 'Nora No Role', email: 'nora@example.test' }),
];

describe('UsersTable organization roles', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => name),
        );
    });

    it('shows organization roles, fallback names, and role counts', () => {
        const wrapper = mount(UsersTable, { props: { users, organization } });

        expect(wrapper.text()).toContain('Roles');
        expect(wrapper.text()).toContain('Organization administrator');
        expect(wrapper.text()).toContain('PeopleCountViewer');
        expect(wrapper.text()).toContain('Stage Safety viewer');
        expect(wrapper.text()).toContain('No role assigned');
        const roleBadges = wrapper
            .findAll('tbody [class*="rounded-full"]')
            .filter((badge) => ['Organization administrator', 'PeopleCountViewer', 'Stage Safety viewer'].includes(badge.text()));
        expect(roleBadges).toHaveLength(3);
        expect(roleBadges.every((badge) => badge.classes().includes('bg-secondary'))).toBe(true);
        expect(wrapper.get('[data-role-filter="all"]').text()).toContain('3');
        expect(wrapper.get('[data-role-filter="OrganizationAdmin"]').text()).toContain('1');
        expect(wrapper.get('[data-role-filter="PeopleCountViewer"]').text()).toContain('1');
        expect(wrapper.get('[data-role-filter="StageSafetyViewer"]').text()).toContain('1');
        expect(wrapper.get('[data-role-filter="none"]').text()).toContain('1');
    });

    it('filters by role and no-role assignments', async () => {
        const wrapper = mount(UsersTable, { props: { users, organization } });

        await wrapper.get('[data-role-filter="StageSafetyViewer"]').trigger('click');
        expect(wrapper.get('tbody').text()).toContain('Sam Safety');
        expect(wrapper.get('tbody').text()).not.toContain('Alex Admin');

        await wrapper.get('[data-role-filter="none"]').trigger('click');
        expect(wrapper.get('tbody').text()).toContain('Nora No Role');
        expect(wrapper.get('tbody').text()).not.toContain('Sam Safety');
    });

    it('uses compact controls by default and restores desktop controls at large widths', () => {
        const wrapper = mount(UsersTable, { props: { users, organization } });
        const mobileRoleFilter = wrapper.get('button[aria-label="Filter users by role"]');
        const desktopRoleFilters = wrapper.get('[role="group"][aria-label="Filter users by role"]');
        const compactView = wrapper.findAll('button').find((button) => button.text() === 'View' && button.classes().includes('size-10'));

        expect(mobileRoleFilter.classes()).toContain('lg:hidden');
        expect(mobileRoleFilter.classes()).toContain('sm:w-72');
        expect(desktopRoleFilters.classes()).toContain('hidden');
        expect(desktopRoleFilters.classes()).toContain('lg:flex');
        expect(compactView).toBeDefined();
        expect(compactView!.get('span').classes()).toContain('sr-only');
    });

    it('searches names and email addresses', async () => {
        const wrapper = mount(UsersTable, { props: { users, organization } });
        const search = wrapper.get('input[placeholder="Search by name or email..."]');

        await search.setValue('safety@example.test');

        expect(wrapper.get('tbody').text()).toContain('Sam Safety');
        expect(wrapper.get('tbody').text()).not.toContain('Alex Admin');
    });

    it('keeps organization roles out of the global users table', () => {
        const wrapper = mount(UsersTable, { props: { users } });

        expect(wrapper.find('[data-role-filter]').exists()).toBe(false);
        expect(wrapper.findAll('th').map((header) => header.text())).not.toContain('Roles');
    });

    it('uses human-readable IDs in the column toggle menu', () => {
        const columnIds = usersColumns(organization).map((column) => column.id ?? ('accessorKey' in column ? column.accessorKey : undefined));

        expect(columnIds).toContain('verified');
        expect(columnIds).toContain('roles');
        expect(columnIds).not.toContain('email_verified_at');
        expect(columnIds).not.toContain('organization_roles');
    });
});
