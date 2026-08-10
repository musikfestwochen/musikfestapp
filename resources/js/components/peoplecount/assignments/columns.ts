import ConfirmActionButton from '@/components/ConfirmActionButton.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountAssignment } from '@/types';
import { formatDateTime } from '@/utils/dateTimeHelpers';
import { Link } from '@inertiajs/vue3';
import type { ColumnDef, StockFeatures } from '@tanstack/vue-table';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import DataTableColumnHeader from '../../data-table/DataTableColumnHeader.vue';

export function assignmentsColumns(organization: Organization): ColumnDef<StockFeatures, PeoplecountAssignment>[] {
    return [
        {
            accessorKey: 'event',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Event',
                }),
            cell: ({ row }) => {
                const assignment = row.original;
                return h('div', { class: 'font-medium' }, assignment.event?.name || 'N/A');
            },
            enableSorting: true,
            enableHiding: true,
        },
        {
            accessorKey: 'area',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Area',
                }),
            cell: ({ row }) => {
                const assignment = row.original;
                return h('div', { class: 'font-medium' }, assignment.area?.name || 'N/A');
            },
            enableSorting: true,
            enableHiding: true,
        },
        {
            accessorKey: 'sensor',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Sensor',
                }),
            cell: ({ row }) => {
                const sensor = row.original.sensor;
                if (!sensor) return 'N/A';
                return h(
                    'div',
                    { class: 'text-sm' },
                    [
                        sensor.name ? h('div', { class: 'font-medium' }, sensor.name) : null,
                        h('div', { class: sensor.name ? 'text-muted-foreground text-xs' : 'font-medium' }, `${sensor.vendor} ${sensor.model}`),
                        sensor.name ? null : h('div', { class: 'text-muted-foreground text-xs' }, sensor.serial),
                    ].filter(Boolean),
                );
            },
            enableSorting: true,
            enableHiding: true,
        },
        {
            accessorKey: 'label',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Label',
                }),
            cell: ({ row }) => h('div', { class: 'text-sm' }, row.original.label || '—'),
            enableSorting: true,
            enableHiding: true,
        },
        {
            accessorKey: 'direction_flipped',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Direction',
                }),
            cell: ({ row }) => {
                const directionFlipped = row.getValue('direction_flipped') as boolean;
                return h(
                    'span',
                    {
                        class: `inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                            directionFlipped ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800'
                        }`,
                    },
                    directionFlipped ? 'Flipped' : 'Normal',
                );
            },
            enableSorting: true,
            enableHiding: true,
        },
        {
            accessorKey: 'active_from',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Active From',
                }),
            cell: ({ row }) => {
                const activeFrom = row.getValue('active_from') as string;
                return h('div', { class: 'text-sm' }, formatDateTime(activeFrom));
            },
            enableSorting: true,
            enableHiding: true,
        },
        {
            accessorKey: 'active_to',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Active To',
                }),
            cell: ({ row }) => {
                const activeTo = row.getValue('active_to') as string;
                return h('div', { class: 'text-sm' }, formatDateTime(activeTo));
            },
            enableSorting: true,
            enableHiding: true,
        },
        {
            id: 'status',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Status',
                }),
            cell: ({ row }) => {
                const assignment = row.original;
                const now = new Date();
                const activeFrom = new Date(assignment.active_from);
                const activeTo = new Date(assignment.active_to);

                let status: { text: string; class: string };
                if (now < activeFrom) {
                    status = { text: 'Upcoming', class: 'bg-blue-100 text-blue-800' };
                } else if (now > activeTo) {
                    status = { text: 'Completed', class: 'bg-gray-100 text-gray-800' };
                } else {
                    status = { text: 'Active', class: 'bg-green-100 text-green-800' };
                }

                return h(
                    'span',
                    {
                        class: `inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${status.class}`,
                    },
                    status.text,
                );
            },
            enableSorting: false,
            enableHiding: true,
        },
        {
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            cell: ({ row }) => {
                const assignment = row.original;
                // Use the can function from usePermissions composable
                const { can } = usePermissions();
                const canEdit = can('peoplecount.assignments.edit');
                const canDelete = can('peoplecount.assignments.destroy');
                const assignmentName =
                    assignment.label || `${assignment.area?.name || 'area'} / ${assignment.sensor?.name || assignment.sensor?.serial || 'sensor'}`;

                return h(
                    'div',
                    { class: 'flex items-center gap-2' },
                    [
                        canEdit &&
                            h(
                                Link,
                                {
                                    href: route('peoplecount.assignments.edit', {
                                        organization: organization.slug,
                                        assignment: assignment.id,
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
                                href: route('peoplecount.assignments.destroy', {
                                    organization: organization.slug,
                                    assignment: assignment.id,
                                }),
                                label: 'Delete',
                                title: `Delete assignment ${assignmentName}?`,
                                description: 'This assignment will be permanently deleted. This cannot be undone.',
                                confirmLabel: 'Delete assignment',
                                icon: Trash2,
                            }),
                    ].filter(Boolean),
                );
            },
        },
    ];
}
