import ConfirmActionButton from '@/components/ConfirmActionButton.vue';
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
            accessorKey: 'phone',
            header: ({ column }) =>
                h(DataTableColumnHeader, {
                    column,
                    title: 'Phone',
                }),
            cell: ({ row }) => {
                return h('div', {}, row.getValue('phone') || '-');
            },
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
        ...(!organization
            ? [
                  {
                      accessorKey: 'organizations_count',
                      header: ({ column }) =>
                          h(DataTableColumnHeader, {
                              column,
                              title: 'Organizations',
                          }),
                      cell: ({ row }) => h('div', {}, row.getValue('organizations_count') ?? 0),
                      enableSorting: true,
                      enableHiding: true,
                  } satisfies ColumnDef<User>,
              ]
            : []),
        {
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            cell: ({ row }) => {
                const user = row.original;
                // Use the can function from usePermissions composable
                const { can } = usePermissions();
                const canEdit = organization ? can('orgmgmt.users.edit') : can('admin.users.edit');
                const canDelete = organization ? can('orgmgmt.users.destroy') : can('admin.users.destroy');

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
                            h(ConfirmActionButton, {
                                href: organization
                                    ? route('orgmgmt.users.destroy', {
                                          organization: organization.slug,
                                          user: user.id,
                                      })
                                    : route('admin.users.destroy', { user: user.id }),
                                label: 'Delete',
                                title: `Delete user ${user.name}?`,
                                description: organization
                                    ? 'This user will be removed from the organization. If this is their only organization, their account will be deleted.'
                                    : 'This user will be permanently deleted. This cannot be undone.',
                                confirmLabel: organization ? 'Remove user' : 'Delete user',
                                icon: Trash2,
                            }),
                    ].filter(Boolean),
                );
            },
        },
    ];
}
