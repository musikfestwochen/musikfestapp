import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountArea, PeoplecountAreaSingleReset } from '@/types';
import { formatLocalDateTime } from '@/utils/dateTimeHelpers';
import { Link } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { Trash2 } from 'lucide-vue-next';
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
                return h('div', { class: 'text-sm' }, reset.created_by_user?.name || 'Unknown');
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
