<script lang="ts" setup>
import { Head } from '@inertiajs/vue3';

import { type BreadcrumbItem } from '@/types';

import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { ColumnDef, FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '/users',
    },
];

const props = defineProps<{
    users: object;
}>();

const columns: ColumnDef<(typeof props.users)[0]>[] = [
    {
        accessorKey: 'name',
        header: 'Name',
    },
    {
        accessorKey: 'email',
        header: 'Email',
    },
    {
        accessorKey: 'email_verified_at',
        header: 'Email Verified At',
        cell: ({ getValue }) => {
            const value = getValue();
            return value ? new Date(value).toLocaleDateString() : 'Not Verified';
        },
    },
];

const table = useVueTable({
    data: props.users.data,
    columns,
    getCoreRowModel: getCoreRowModel(),
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <div class="px-4 py-6">
            <Heading description="See all your users" title="Users" />

            <Table class="mt-4">
                <TableHeader>
                    <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                        <TableHead v-for="header in headerGroup.headers" :key="header.id">
                            <FlexRender :props="header.getContext()" :render="header.column.columnDef.header" />
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="row in table.getRowModel().rows" :key="row.id">
                        <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                            <FlexRender :props="cell.getContext()" :render="cell.column.columnDef.cell" />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Pagination :items="users" />
        </div>
    </AppLayout>
</template>
