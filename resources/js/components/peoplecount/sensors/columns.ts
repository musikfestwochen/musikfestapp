import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountSensor } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import DataTableColumnHeader from '../../data-table/DataTableColumnHeader.vue';

export function sensorsColumns(organization: Organization): ColumnDef<PeoplecountSensor>[] {
    return [
        {
            accessorKey: 'vendor',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Vendor',
                }),
            cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('vendor')),
            enableSorting: true,
            enableHiding: true,
        },
        {
            accessorKey: 'model',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Model',
                }),
            cell: ({ row }) => h('div', {}, row.getValue('model')),
            enableSorting: true,
            enableHiding: true,
        },
        {
            accessorKey: 'serial',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Serial Number',
                }),
            cell: ({ row }) => h('div', {}, row.getValue('serial')),
            enableSorting: true,
            enableHiding: true,
        },
        {
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            cell: ({ row }) => {
                const sensor = row.original;
                // Use the can function from usePermissions composable
                const { can } = usePermissions();
                const canEdit = can('peoplecount.sensors.edit');
                const canDelete = can('peoplecount.sensors.destroy');

                return h(
                    'div',
                    { class: 'flex items-center gap-2' },
                    [
                        canEdit &&
                            h(
                                Link,
                                {
                                    href: route('peoplecount.sensors.edit', {
                                        organization: organization.slug,
                                        sensor: sensor.id,
                                    }),
                                    as: 'button',
                                },
                                () =>
                                    h(
                                        Button,
                                        {
                                            variant: 'outline',
                                            size: 'sm',
                                        },
                                        () => [h(Pencil, { class: 'w-4 h-4 mr-1' }), 'Edit'],
                                    ),
                            ),
                        canDelete &&
                            h(
                                Link,
                                {
                                    href: route('peoplecount.sensors.destroy', {
                                        organization: organization.slug,
                                        sensor: sensor.id,
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
