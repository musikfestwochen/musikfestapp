<script lang="ts" setup>
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

import Heading from '@/components/Heading.vue';
import Icon from '@/components/Icon.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';

const { can } = usePermissions();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Organizations',
        href: '/organizations',
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
        name: 'Website',
        sortable: true,
        accessor: 'website',
        width: '52',
    },
    {
        name: 'Actions',
        sortable: false,
        actions: [
            {
                name: 'Edit',
                href: (organization: any) => route('organizations.edit', organization.id),
                icon: 'PencilIcon',
                button_variant: 'outline',
                permission: 'organizations.update',
            },
            {
                name: 'Delete',
                href: (organization: any) => route('organizations.destroy', organization.id),
                method: 'delete',
                icon: 'TrashIcon',
                button_variant: 'destructive',
                permission: 'organizations.delete',
            },
        ],
        width: '52',
    },
];

defineProps<{
    organizations: object;
    status?: string;
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Organizations" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="px-4 py-6">
            <Heading description="See all your organizations" title="Organizations">
                <Button as-child variant="secondary">
                    <Link :href="route('organizations.create')">Create Organization</Link>
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
                                                route('organizations.index', {
                                                    sort: column.accessor,
                                                    order:
                                                        route().current() === 'organizations.index' && route().params.order === 'asc'
                                                            ? 'desc'
                                                            : 'asc',
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
                        <TableRow v-for="organization in organizations.data" :key="organization.id">
                            <TableCell v-for="column in columns" :key="column.accessor">
                                <span
                                    v-if="column.accessor"
                                    :class="`w-${column.width}`"
                                    :title="organization[column.accessor]"
                                    class="inline-block truncate"
                                    v-html="column.mapping ? column.mapping(organization[column.accessor]) : organization[column.accessor]"
                                />
                                <Button v-for="action in column.actions" :key="action.name" :variant="action.button_variant" as-child class="ml-2">
                                    <Link v-if="can(action.permission)" :href="action.href(organization)" :method="action.method || 'get'">
                                        <Icon :name="action.icon" class="mr-1" />
                                        {{ action.name }}
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :items="organizations" />
        </div>
    </AppLayout>
</template>
