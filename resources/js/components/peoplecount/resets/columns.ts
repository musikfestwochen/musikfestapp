import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountArea, PeoplecountAreaRecurringReset, PeoplecountAreaSingleReset } from '@/types';
import { formatLocalDateTime, getNextRRuleOccurrences, rruleToText } from '@/utils/dateTimeHelpers';
import { Link } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { Edit, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import DataTableColumnHeader from '../../data-table/DataTableColumnHeader.vue';

export function singleResetColumns(organization: Organization, area: PeoplecountArea): ColumnDef<PeoplecountAreaSingleReset>[] {
    return [
        {
            accessorKey: 'reset_value',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Reset Value',
                }),
            cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('reset_value')),
            enableSorting: true,
            enableHiding: true,
        },
        {
            accessorKey: 'effective_at',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Effective At',
                }),
            cell: ({ row }) => {
                const reset = row.original;
                return h('div', { class: 'text-sm' }, formatLocalDateTime(reset.effective_at));
            },
            enableSorting: true,
            enableHiding: true,
        },
        {
            id: 'created_by',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Created By',
                }),
            cell: ({ row }) => {
                const reset = row.original;
                return h('div', { class: 'text-sm' }, reset.created_by?.name || 'Unknown');
            },
            enableSorting: true,
            enableHiding: true,
        },
        {
            id: 'notes',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Notes',
                }),
            cell: ({ row }) => {
                const reset = row.original;
                return h('div', { class: 'text-sm text-muted-foreground' }, reset.notes || 'No notes');
            },
            enableSorting: false,
            enableHiding: true,
        },
        {
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            cell: ({ row }) => {
                const reset = row.original;
                // Use the can function from usePermissions composable
                const { can } = usePermissions();
                const canDelete = can('peoplecount.area_resets.destroy');

                return h(
                    'div',
                    { class: 'flex items-center gap-2' },
                    [
                        canDelete &&
                            h(
                                Link,
                                {
                                    href: route('peoplecount.areas.single-resets.destroy', {
                                        organization: organization.slug,
                                        area: area.id,
                                        single_reset: reset.id,
                                    }),
                                    method: 'delete',
                                    as: 'button',
                                },
                                () =>
                                    h(
                                        Button,
                                        {
                                            variant: 'destructive',
                                            size: 'sm',
                                        },
                                        () => [h(Trash2, { class: 'w-4 h-4 mr-1' }), 'Delete'],
                                    ),
                            ),
                    ].filter(Boolean),
                );
            },
        },
    ];
}

export function recurringResetColumns(organization: Organization, area: PeoplecountArea): ColumnDef<PeoplecountAreaRecurringReset>[] {
    return [
        {
            accessorKey: 'reset_value',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Reset Value',
                }),
            cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('reset_value')),
            enableSorting: true,
            enableHiding: true,
        },
        {
            id: 'rrule',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Schedule',
                }),
            cell: ({ row }) => {
                const reset = row.original;
                const humanReadable = rruleToText(reset.rrule);
                return h('div', { class: 'text-sm' }, humanReadable || 'Invalid RRULE');
            },
            enableSorting: false,
            enableHiding: true,
        },
        {
            id: 'notes',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Notes',
                }),
            cell: ({ row }) => {
                const reset = row.original;
                return h('div', { class: 'text-sm text-muted-foreground' }, reset.notes || 'No notes');
            },
            enableSorting: false,
            enableHiding: true,
        },
        {
            id: 'next_occurrence',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Next Occurrence',
                }),
            cell: ({ row }) => {
                const reset = row.original;
                try {
                    // Get start date from event or use current date
                    const startDate = new Date(); // Default to the current date

                    const nextOccurrences = getNextRRuleOccurrences(reset.rrule, startDate);
                    if (nextOccurrences.length > 0) {
                        // Format the occurrence in the stored timezone using Intl.DateTimeFormat
                        // This avoids the double conversion bug in formatLocalDateTime
                        const formatter = new Intl.DateTimeFormat('en-GB', {
                            timeZone: reset.timezone || 'UTC',
                            day: '2-digit',
                            month: '2-digit',
                            year: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false,
                        });
                        const formattedTime = formatter.format(nextOccurrences[0]);
                        return h('div', { class: 'text-sm' }, formattedTime);
                    }
                    return h('div', { class: 'text-sm text-muted-foreground' }, 'No upcoming occurrences');
                } catch {
                    return h('div', { class: 'text-sm text-red-600' }, 'Invalid schedule');
                }
            },
            enableSorting: false,
            enableHiding: true,
        },
        {
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            cell: ({ row }) => {
                const reset = row.original;
                const { can } = usePermissions();
                const canUpdate = can('peoplecount.area_resets.update');
                const canDelete = can('peoplecount.area_resets.destroy');

                return h(
                    'div',
                    { class: 'flex items-center gap-2' },
                    [
                        canUpdate &&
                            h(
                                Link,
                                {
                                    href: route('peoplecount.areas.recurring-resets.edit', {
                                        organization: organization.slug,
                                        area: area.id,
                                        recurring_reset: reset.id,
                                    }),
                                },
                                () =>
                                    h(
                                        Button,
                                        {
                                            variant: 'outline',
                                            size: 'sm',
                                        },
                                        () => [h(Edit, { class: 'w-4 h-4 mr-1' }), 'Edit'],
                                    ),
                            ),
                        canDelete &&
                            h(
                                Link,
                                {
                                    href: route('peoplecount.areas.recurring-resets.destroy', {
                                        organization: organization.slug,
                                        area: area.id,
                                        recurring_reset: reset.id,
                                    }),
                                    method: 'delete',
                                    as: 'button',
                                },
                                () =>
                                    h(
                                        Button,
                                        {
                                            variant: 'destructive',
                                            size: 'sm',
                                        },
                                        () => [h(Trash2, { class: 'w-4 h-4 mr-1' }), 'Delete'],
                                    ),
                            ),
                    ].filter(Boolean),
                );
            },
        },
    ];
}
