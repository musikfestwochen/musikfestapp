<script lang="ts" setup>
import UsersTable from '@/components/users/UsersTable.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { type BreadcrumbItem, Organization, User } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    users: User[];
    status?: string;
    organization: Organization;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: `/${props.organization.slug}/dashboard`,
    },
    {
        title: 'Users',
        href: route('orgmgmt.users.index', { organization: props.organization.slug }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="px-4 py-6">
            <UsersTable :organization="props.organization" :users="users" />
        </div>
    </Layout>
</template>
