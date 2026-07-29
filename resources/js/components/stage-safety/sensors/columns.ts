import ConfirmActionButton from '@/components/ConfirmActionButton.vue';
import DataTableColumnHeader from '@/components/data-table/DataTableColumnHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import type { Organization, StageSafetySensor } from '@/types';
import { Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';

export function sensorColumns(organization: Organization): ColumnDef<StageSafetySensor>[] {
    return [
        {
            id: 'name',
            accessorFn: (sensor) => sensor.name || sensor.identifier,
            header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Name' }),
            cell: ({ row }) => h('div', { class: 'font-medium' }, row.original.name || row.original.identifier),
        },
        {
            accessorKey: 'model',
            header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Sensor Type' }),
            cell: ({ row }) => h('div', {}, `BroadWeigh ${row.original.model}`),
        },
        {
            accessorKey: 'identifier',
            header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Device ID' }),
        },
        {
            accessorKey: 'location',
            header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Location' }),
            cell: ({ row }) => h('div', {}, row.original.location || '—'),
        },
        {
            accessorKey: 'stale_after_seconds',
            header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Stale After' }),
            cell: ({ row }) => h('div', {}, `${row.original.stale_after_seconds}s`),
        },
        {
            accessorKey: 'has_active_token',
            header: 'API Token',
            cell: ({ row }) =>
                h(Badge, { variant: row.original.has_active_token ? 'default' : 'secondary' }, () =>
                    row.original.has_active_token ? 'Active' : 'Not active',
                ),
            enableSorting: false,
        },
        {
            id: 'actions',
            header: 'Actions',
            cell: ({ row }) => {
                const { can } = usePermissions();
                const sensor = row.original;

                return h(
                    'div',
                    { class: 'flex items-center gap-2', 'data-row-action': '' },
                    [
                        can('stage-safety.sensors.edit') &&
                            h(
                                Link,
                                {
                                    href: route('stage-safety.sensors.edit', {
                                        organization: organization.slug,
                                        stageSafetySensor: sensor.id,
                                    }),
                                    as: 'button',
                                },
                                () => h(Button, { variant: 'outline', size: 'sm' }, () => [h(Pencil, { class: 'mr-1 size-4' }), 'Edit']),
                            ),
                        can('stage-safety.sensors.destroy') &&
                            h(ConfirmActionButton, {
                                href: route('stage-safety.sensors.destroy', {
                                    organization: organization.slug,
                                    stageSafetySensor: sensor.id,
                                }),
                                label: 'Delete',
                                title: `Delete sensor ${sensor.name || sensor.identifier}?`,
                                description: 'This removes the sensor and immediately revokes its API tokens.',
                                confirmLabel: 'Delete sensor',
                                icon: Trash2,
                            }),
                    ].filter(Boolean),
                );
            },
            enableHiding: false,
        },
    ];
}
