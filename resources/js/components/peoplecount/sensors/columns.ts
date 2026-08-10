import ConfirmActionButton from '@/components/ConfirmActionButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountSensor } from '@/types';
import { Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import DataTableColumnHeader from '../../data-table/DataTableColumnHeader.vue';
import type { DataTableFeatures } from '../../data-table/features';

export function sensorsColumns(organization: Organization): ColumnDef<DataTableFeatures, PeoplecountSensor>[] {
    return [
        {
            accessorKey: 'name',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Name',
                }),
            cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name') || '—'),
            enableSorting: true,
            enableHiding: true,
        },
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
            accessorKey: 'has_active_token',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Token',
                }),
            cell: ({ row }) =>
                h(Badge, { variant: row.getValue('has_active_token') ? 'default' : 'secondary' }, () =>
                    row.getValue('has_active_token') ? 'Active' : 'Not active',
                ),
            enableSorting: false,
            enableHiding: true,
        },
        {
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            cell: ({ row }) => {
                const sensor = row.original;
                const { can } = usePermissions();
                const canEdit = can('peoplecount.sensors.edit');
                const canDelete = can('peoplecount.sensors.destroy');
                const sensorName = sensor.name || sensor.serial;

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
                            h(ConfirmActionButton, {
                                href: route('peoplecount.sensors.destroy', {
                                    organization: organization.slug,
                                    sensor: sensor.id,
                                }),
                                label: 'Delete',
                                title: `Delete sensor ${sensorName}?`,
                                description: 'This sensor will be permanently deleted. This cannot be undone.',
                                confirmLabel: 'Delete sensor',
                                icon: Trash2,
                            }),
                    ].filter(Boolean),
                );
            },
        },
    ];
}
