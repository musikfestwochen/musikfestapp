import { Badge } from '@/components/ui/badge/index';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import DataTableColumnHeader from '../data-table/DataTableColumnHeader.vue';

export function usersColumns(organization?: Organization): ColumnDef<User>[] {
    return [
        {
            accessorKey: 'name',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Name',
                }),
            cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')),
            enableSorting: true,
            enableHiding: true,
        },
        {
            accessorKey: 'email',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Email',
                }),
            cell: ({ row }) => h('div', {}, row.getValue('email')),
            enableSorting: true,
            enableHiding: true,
        },
        {
            accessorKey: 'email_verified_at',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Verified',
                }),
            cell: ({ row }) => {
                const verified = row.getValue('email_verified_at');

                return verified ? h(Badge, { variant: 'default' }, () => 'Yes') : h(Badge, { variant: 'destructive' }, () => 'No');
            },
            enableSorting: true,
            enableHiding: true,
        },
        {
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            cell: ({ row }) => {
                const user = row.original;
                // Use the can function from usePermissions composable
                const { can } = usePermissions();
                const canEdit = can('admin.users.edit') || can('orgmgmt.users.edit');
                const canDelete = can('admin.users.destroy') || can('orgmgmt.users.destroy');

                return h(
                    'div',
                    { class: 'flex items-center gap-2' },
                    [
                        canEdit &&
                            h(
                                Link,
                                {
                                    href: organization
                                        ? route('orgmgmt.users.edit', {
                                              organization: organization.slug,
                                              user: user.id,
                                          })
                                        : route('admin.users.edit', { user: user.id }),
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
                                    href: organization
                                        ? route('orgmgmt.users.destroy', {
                                              organization: organization.slug,
                                              user: user.id,
                                          })
                                        : route('admin.users.destroy', { user: user.id }),
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
