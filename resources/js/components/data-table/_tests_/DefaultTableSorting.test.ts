import OrganizationsTable from '@/components/organizations/OrganizationsTable.vue';
import AreasTable from '@/components/peoplecount/areas/AreasTable.vue';
import AssignmentsTable from '@/components/peoplecount/assignments/AssignmentsTable.vue';
import EventsTable from '@/components/peoplecount/events/EventsTable.vue';
import RecurringResetTable from '@/components/peoplecount/resets/RecurringResetTable.vue';
import SingleResetTable from '@/components/peoplecount/resets/SingleResetTable.vue';
import PeoplecountSensorsTable from '@/components/peoplecount/sensors/SensorsTable.vue';
import StageSafetySensorsTable from '@/components/stage-safety/sensors/SensorsTable.vue';
import UsersTable from '@/components/users/UsersTable.vue';
import type { SortingState } from '@tanstack/vue-table';
import { mount, type VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import type { Component } from 'vue';

vi.mock('@/composables/usePermissions', () => ({
    usePermissions: () => ({ can: () => false }),
}));

const organization = { id: 1, slug: 'test', name: 'Test', created_at: '', updated_at: '' };
const area = { id: 1, name: 'Main Area' };
const DataTableStub = { name: 'DataTable', props: ['initialSorting'], template: '<div />' };

function getInitialSorting(component: Component, props: Record<string, unknown>): SortingState {
    const wrapper: VueWrapper = mount(component, {
        props,
        global: { stubs: { DataTable: DataTableStub } },
    });

    return wrapper.findComponent(DataTableStub).props('initialSorting') as SortingState;
}

describe('resource table default sorting', () => {
    it.each([
        ['users', UsersTable, { users: [], organization }, [{ id: 'name', desc: false }]],
        ['organizations', OrganizationsTable, { organizations: [] }, [{ id: 'name', desc: false }]],
        ['events', EventsTable, { events: [], organization }, [{ id: 'starts_at', desc: true }]],
        ['areas', AreasTable, { areas: [], organization }, [{ id: 'name', desc: false }]],
        ['assignments', AssignmentsTable, { assignments: [], organization }, [{ id: 'active_from', desc: true }]],
        ['peoplecount sensors', PeoplecountSensorsTable, { sensors: [], organization }, [{ id: 'serial', desc: false }]],
        ['Stage Safety sensors', StageSafetySensorsTable, { sensors: [], organization }, [{ id: 'name', desc: false }]],
        ['manual resets', SingleResetTable, { resets: [], organization, area }, [{ id: 'effective_at', desc: true }]],
        ['recurring resets', RecurringResetTable, { resets: [], organization, area }, [{ id: 'reset_time', desc: false }]],
    ])('configures %s', (_name, component, props, expected) => {
        expect(getInitialSorting(component as Component, props as Record<string, unknown>)).toEqual(expected);
    });
});
