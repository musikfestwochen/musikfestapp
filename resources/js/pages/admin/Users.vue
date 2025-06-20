<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import UsersTable from '@/components/users/UsersTable.vue';
import Layout from '@/layouts/admin/Layout.vue';
import { type BreadcrumbItem, User } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: route('admin.users.index'),
    },
];

defineProps<{
    users: User[];
    status?: string;
}>();
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="px-4 py-6">
            <Heading description="See all your users" title="Users" />

            <div class="mt-4">
                <UsersTable :users="users" />
            </div>
        </div>
    </Layout>
</template>
