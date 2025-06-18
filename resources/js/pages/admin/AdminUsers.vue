<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import Icon from '@/components/Icon.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/composables/usePermissions';
import AdminAppLayout from '@/layouts/admin/AdminAppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const { can } = usePermissions();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '/users',
    },
];

const columns = [
    {
        name: 'Name',
        sortable: true,
        accessor: 'name',
        width: '52',
    },
    {
        name: 'Email',
        sortable: true,
        accessor: 'email',
        width: '52',
    },
    {
        name: 'Verified',
        sortable: false,
        accessor: 'email_verified_at',
        mapping: (value: string) => {
            return value ? 'Yes' : '<span class="text-red-500">No</span>';
        },
        width: '22',
    },
    {
        name: 'Actions',
        sortable: false,
        actions: [
            {
                name: 'Edit',
                href: (user: any) => route('users.edit', user.id),
                icon: 'PencilIcon',
                button_variant: 'outline',
                permission: 'users.update',
            },
            {
                name: 'Delete',
                href: (user: any) => route('users.destroy', user.id),
                method: 'delete',
                icon: 'TrashIcon',
                button_variant: 'destructive',
                permission: 'users.delete',
            },
        ],
        width: '52',
    },
];

defineProps<{
    users: object;
    status?: string;
}>();
</script>

<template>
    <AdminAppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="px-4 py-6">
            <Heading description="See all your users" title="Users">
                <Button as-child variant="secondary">
                    <Link :href="route('users.create')">Create User</Link>
                </Button>
            </Heading>

            <div class="mt-4 overflow-x-auto">
                <Table class="w-full min-w-max">
                    <TableHeader>
                        <TableRow>
                            <TableHead v-for="column in columns" :key="column.name">
                                <span :class="`w-${column.width}`" class="inline-block truncate">
                                    {{ column.name }}
                                    <Button v-if="column.sortable" as-child variant="ghost">
                                        <Link
                                            :href="
                                                route('users.index', {
                                                    sort: column.accessor,
                                                    order: route().current() === 'users.index' && route().params.order === 'asc' ? 'desc' : 'asc',
                                                })
                                            "
                                        >
                                            <Icon v-if="column.sortable" class="ml-1" name="ArrowUpDown" />
                                        </Link>
                                    </Button>
                                </span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in users.data" :key="user.id">
                            <TableCell v-for="column in columns" :key="column.accessor">
                                <span
                                    v-if="column.accessor"
                                    :class="`w-${column.width}`"
                                    :title="user[column.accessor]"
                                    class="inline-block truncate"
                                    v-html="column.mapping ? column.mapping(user[column.accessor]) : user[column.accessor]"
                                />
                                <Button v-for="action in column.actions" :key="action.name" :variant="action.button_variant" as-child class="ml-2">
                                    <Link v-if="can(action.permission)" :href="action.href(user)" :method="action.method || 'get'">
                                        <Icon :name="action.icon" class="mr-1" />
                                        {{ action.name }}
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :items="users" />
        </div>
    </AdminAppLayout>
</template>
