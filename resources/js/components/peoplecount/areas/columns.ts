import ConfirmActionButton from '@/components/ConfirmActionButton.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountArea } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import DataTableColumnHeader from '../../data-table/DataTableColumnHeader.vue';

export function areasColumns(organization: Organization): ColumnDef<PeoplecountArea>[] {
    return [
        {
            accessorKey: 'name',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Area Name',
                }),
            cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')),
            enableSorting: true,
            enableHiding: true,
        },
        {
            id: 'event_name',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Event',
                }),
            cell: ({ row }) => {
                const area = row.original;
                return h('div', { class: 'text-sm' }, area.event?.name || 'N/A');
            },
            enableSorting: false,
            enableHiding: true,
        },
        {
            id: 'assignments_count',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Assignments',
                }),
            cell: ({ row }) => {
                const area = row.original;
                const count = area.assignments?.length || 0;
                return h('div', { class: 'text-sm text-muted-foreground' }, `${count} assignment${count !== 1 ? 's' : ''}`);
            },
            enableSorting: false,
            enableHiding: true,
        },
        {
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            cell: ({ row }) => {
                const area = row.original;
                // Use the can function from usePermissions composable
                const { can } = usePermissions();
                const canEdit = can('peoplecount.areas.edit');
                const canDelete = can('peoplecount.areas.destroy');

                return h(
                    'div',
                    { class: 'flex items-center gap-2' },
                    [
                        canEdit &&
                            h(
                                Link,
                                {
                                    href: route('peoplecount.areas.edit', {
                                        organization: organization.slug,
                                        area: area.id,
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
                                href: route('peoplecount.areas.destroy', {
                                    organization: organization.slug,
                                    area: area.id,
                                }),
                                label: 'Delete',
                                title: `Delete area ${area.name}?`,
                                description: 'This area will be permanently deleted. This cannot be undone.',
                                confirmLabel: 'Delete area',
                                icon: Trash2,
                            }),
                    ].filter(Boolean),
                );
            },
        },
    ];
}
