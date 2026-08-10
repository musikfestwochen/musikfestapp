import ConfirmActionButton from '@/components/ConfirmActionButton.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization } from '@/types';
import { Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import DataTableColumnHeader from '../data-table/DataTableColumnHeader.vue';
import type { DataTableFeatures } from '../data-table/features';

export const organizationsColumns: ColumnDef<DataTableFeatures, Organization>[] = [
    {
        accessorKey: 'name',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column,
                title: 'Organization Name',
            }),
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')),
    },
    {
        accessorKey: 'email',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column,
                title: 'Email',
            }),
        cell: ({ row }) => h('div', {}, row.getValue('email')),
    },
    {
        accessorKey: 'website',
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column,
                title: 'Website',
            }),
        cell: ({ row }) => {
            const website = row.getValue('website') as string;
            return h(
                'a',
                {
                    href: website,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    class: 'text-blue-600 hover:underline',
                },
                website,
            );
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        cell: ({ row }) => {
            const organization = row.original;
            // Use the can function from usePermissions composable
            const { can } = usePermissions();
            const canEdit = can('admin.organizations.edit') || can('orgmgmt.organizations.edit');
            const canDelete = can('admin.organizations.destroy') || can('orgmgmt.organizations.delete');

            return h(
                'div',
                { class: 'flex items-center gap-2' },
                [
                    canEdit &&
                        h(
                            Link,
                            {
                                href: route('admin.organizations.edit', { id: organization.id }),
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
                            href: route('admin.organizations.destroy', { id: organization.id }),
                            label: 'Delete',
                            title: `Delete organization ${organization.name}?`,
                            description: 'This organization will be permanently deleted. This cannot be undone.',
                            confirmLabel: 'Delete organization',
                            icon: Trash2,
                        }),
                ].filter(Boolean),
            );
        },
    },
];
