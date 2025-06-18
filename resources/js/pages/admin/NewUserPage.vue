<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import UserForm from '@/components/UserForm.vue';
import AdminAppLayout from '@/layouts/admin/AdminAppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '/users',
    },
    {
        title: 'Create',
        href: '/users/create',
    },
];
</script>

<template>
    <AdminAppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <div class="px-4 py-6">
            <Heading class="mb-4" description="Create a new user" level="2" title="Create User" />
            <UserForm />
        </div>
    </AdminAppLayout>
</template>
