import type { Organization, PeoplecountArea, PeoplecountAreaRecurringReset } from '@/types';
import { formatDateTime } from '@/utils/dateTimeHelpers';
import { describe, expect, it, vi } from 'vitest';
import type { VNode } from 'vue';
import { recurringResetColumns } from '../columns';

vi.mock('@/utils/dateTimeHelpers', () => ({
    formatDateTime: vi.fn(() => 'formatted occurrence'),
}));

const organization = { id: 1, slug: 'test' } as Organization;
const area = { id: 2, name: 'Main Hall' } as PeoplecountArea;
const reset = {
    id: 3,
    reset_time: '08:00',
    timezone: 'Europe/Zurich',
    next_occurrence: '2024-03-31T01:00:00.000000Z',
} as PeoplecountAreaRecurringReset;

function renderCell(columnId: string): VNode {
    const column = recurringResetColumns(organization, area).find(
        (candidate) => candidate.id === columnId || ('accessorKey' in candidate && candidate.accessorKey === columnId),
    );

    return (column?.cell as (context: { row: { original: PeoplecountAreaRecurringReset } }) => VNode)({ row: { original: reset } });
}

describe('recurring reset columns', () => {
    it('shows explicit wall-time schedule and formats backend next occurrence', () => {
        expect(renderCell('reset_time').children).toBe('Daily at 08:00 (Europe/Zurich)');
        expect(renderCell('next_occurrence').children).toBe('formatted occurrence');
        expect(formatDateTime).toHaveBeenCalledWith(reset.next_occurrence);
    });
});
