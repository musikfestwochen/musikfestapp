import ConfirmActionButton from '@/components/ConfirmActionButton.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountEvent } from '@/types';
import { formatDateTime } from '@/utils/dateTimeHelpers';
import { getEventDuration, getEventStatus } from '@/utils/eventHelpers';
import { Link } from '@inertiajs/vue3';
import type { ColumnDef, StockFeatures } from '@tanstack/vue-table';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import DataTableColumnHeader from '../../data-table/DataTableColumnHeader.vue';

export function eventsColumns(organization: Organization): ColumnDef<StockFeatures, PeoplecountEvent>[] {
    return [
        {
            accessorKey: 'name',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Event Name',
                }),
            cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')),
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
                const event = row.original;
                const status = getEventStatus(event);
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
            accessorKey: 'starts_at',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Start Date & Time',
                }),
            cell: ({ row }) => {
                const startsAt = row.getValue('starts_at') as string;
                return h('div', { class: 'text-sm' }, formatDateTime(startsAt));
            },
            enableSorting: true,
            enableHiding: true,
        },
        {
            accessorKey: 'ends_at',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'End Date & Time',
                }),
            cell: ({ row }) => {
                const endsAt = row.getValue('ends_at') as string;
                return h('div', { class: 'text-sm' }, formatDateTime(endsAt));
            },
            enableSorting: true,
            enableHiding: true,
        },
        {
            id: 'duration',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Duration',
                }),
            cell: ({ row }) => {
                const event = row.original;
                const duration = getEventDuration(event);
                return h('div', { class: 'text-sm text-muted-foreground' }, duration.formatted);
            },
            enableSorting: false,
            enableHiding: true,
        },
        {
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            cell: ({ row }) => {
                const event = row.original;
                // Use the can function from usePermissions composable
                const { can } = usePermissions();
                const canEdit = can('peoplecount.events.edit');
                const canDelete = can('peoplecount.events.destroy');

                return h(
                    'div',
                    { class: 'flex items-center gap-2' },
                    [
                        canEdit &&
                            h(
                                Link,
                                {
                                    href: route('peoplecount.events.edit', {
                                        organization: organization.slug,
                                        event: event.id,
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
                                href: route('peoplecount.events.destroy', {
                                    organization: organization.slug,
                                    event: event.id,
                                }),
                                label: 'Delete',
                                title: `Delete event ${event.name}?`,
                                description: 'This event will be permanently deleted. This cannot be undone.',
                                confirmLabel: 'Delete event',
                                icon: Trash2,
                            }),
                    ].filter(Boolean),
                );
            },
        },
    ];
}
