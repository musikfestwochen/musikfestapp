import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountAssignment } from '@/types';
import { formatLocalDateTime } from '@/utils/dateTimeHelpers';
import { Link } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import DataTableColumnHeader from '../../data-table/DataTableColumnHeader.vue';

export function assignmentsColumns(organization: Organization): ColumnDef<PeoplecountAssignment>[] {
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
                const assignment = row.original;
                const sensor = assignment.sensor;
                return h('div', { class: 'text-sm' }, sensor ? `${sensor.vendor} ${sensor.model} (${sensor.serial})` : 'N/A');
            },
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
                return h('div', { class: 'text-sm' }, formatLocalDateTime(activeFrom));
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
                return h('div', { class: 'text-sm' }, formatLocalDateTime(activeTo));
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
                            h(
                                Link,
                                {
                                    href: route('peoplecount.assignments.destroy', {
                                        organization: organization.slug,
                                        assignment: assignment.id,
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
