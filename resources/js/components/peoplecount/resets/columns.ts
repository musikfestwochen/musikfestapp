import ConfirmActionButton from '@/components/ConfirmActionButton.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountArea, PeoplecountAreaRecurringReset, PeoplecountAreaSingleReset } from '@/types';
import { APP_LOCALE, formatLocalDateTime } from '@/utils/dateTimeHelpers';
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
                            h(ConfirmActionButton, {
                                href: route('peoplecount.areas.single-resets.destroy', {
                                    organization: organization.slug,
                                    area: area.id,
                                    single_reset: reset.id,
                                }),
                                label: 'Delete',
                                title: `Delete reset for ${area.name}?`,
                                description: 'This reset will be permanently deleted. This cannot be undone.',
                                confirmLabel: 'Delete reset',
                                icon: Trash2,
                            }),
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
            accessorKey: 'reset_time',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Schedule',
                }),
            cell: ({ row }) => {
                const reset = row.original;
                const scheduleText = `Daily at ${reset.reset_time} (${reset.timezone})`;
                return h('div', { class: 'text-sm' }, scheduleText);
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
            id: 'next_occurrence',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Next Occurrence',
                }),
            cell: ({ row }) => {
                const reset = row.original;
                try {
                    // Calculate next daily occurrence
                    const now = new Date();
                    const [hours, minutes] = reset.reset_time.split(':').map(Number);

                    // Create today's reset time in the specified timezone
                    const today = new Date();
                    today.setHours(hours, minutes, 0, 0);

                    // If today's reset time has passed, use tomorrow
                    const nextOccurrence =
                        now.getHours() > hours || (now.getHours() === hours && now.getMinutes() >= minutes)
                            ? new Date(today.getTime() + 24 * 60 * 60 * 1000)
                            : today;

                    // Format the occurrence in the stored timezone
                    const formatter = new Intl.DateTimeFormat(APP_LOCALE, {
                        timeZone: reset.timezone || 'UTC',
                        day: '2-digit',
                        month: '2-digit',
                        year: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false,
                    });
                    const formattedTime = formatter.format(nextOccurrence);
                    return h('div', { class: 'text-sm' }, formattedTime);
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
                            h(ConfirmActionButton, {
                                href: route('peoplecount.areas.recurring-resets.destroy', {
                                    organization: organization.slug,
                                    area: area.id,
                                    recurring_reset: reset.id,
                                }),
                                label: 'Delete',
                                title: `Delete recurring reset for ${area.name}?`,
                                description: 'This recurring reset will be permanently deleted. This cannot be undone.',
                                confirmLabel: 'Delete recurring reset',
                                icon: Trash2,
                            }),
                    ].filter(Boolean),
                );
            },
        },
    ];
}
