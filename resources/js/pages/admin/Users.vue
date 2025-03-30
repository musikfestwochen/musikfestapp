<script lang="ts" setup>
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

import Heading from '@/components/Heading.vue';
import Icon from '@/components/Icon.vue';
import Pagination from '@/components/Pagination.vue';
import { TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';

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
];

const props = defineProps<{
    users: object;
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <div class="px-4 py-6">
            <Heading description="See all your users" title="Users" />

            <Table class="mt-4">
                <TableHeader>
                    <TableRow>
                        <TableHead v-for="column in columns" :key="column.name">
                            <span :class="`w-${column.width}`" class="inline-block truncate">
                                {{ column.name }}
                                <Button v-if="column.sortable" as-child variant="ghost">
                                    <Link
                                        :href="
                                            route('user.index', {
                                                sort: column.accessor,
                                                order: route().current() === 'user.index' && route().params.order === 'asc' ? 'desc' : 'asc',
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
                                :class="`w-${column.width}`"
                                :title="user[column.accessor]"
                                class="inline-block truncate"
                                v-html="column.mapping ? column.mapping(user[column.accessor]) : user[column.accessor]"
                            />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Pagination :items="users" />
        </div>
    </AppLayout>
</template>
