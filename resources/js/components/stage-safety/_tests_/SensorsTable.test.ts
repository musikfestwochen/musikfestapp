import type { Organization, StageSafetySensor } from '@/types';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SensorsTable from '../sensors/SensorsTable.vue';

const mocks = vi.hoisted(() => ({ can: vi.fn() }));

vi.mock('@/composables/usePermissions', () => ({
    usePermissions: () => ({ can: mocks.can }),
}));

const organization = { id: 1, slug: 'test', name: 'Test', created_at: '', updated_at: '' } satisfies Organization;
const sensor = { id: 7 } as StageSafetySensor;
const DataTableStub = { name: 'DataTable', props: ['rowHref'], template: '<div />' };

describe('Stage Safety sensors table', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => name),
        );
    });

    it('links editable rows directly to the edit page', () => {
        mocks.can.mockImplementation((permission: string) => permission === 'stage-safety.sensors.edit');

        const wrapper = mount(SensorsTable, {
            props: { organization, sensors: [sensor] },
            global: {
                stubs: {
                    DataTable: DataTableStub,
                },
            },
        });

        const rowHref = wrapper.findComponent(DataTableStub).props('rowHref') as (value: StageSafetySensor) => string;

        expect(rowHref(sensor)).toBe('stage-safety.sensors.edit');
    });

    it('does not link rows for users without edit permission', () => {
        mocks.can.mockReturnValue(false);

        const wrapper = mount(SensorsTable, {
            props: { organization, sensors: [sensor] },
            global: {
                stubs: {
                    DataTable: DataTableStub,
                },
            },
        });

        expect(wrapper.findComponent(DataTableStub).props('rowHref')).toBeUndefined();
    });
});
