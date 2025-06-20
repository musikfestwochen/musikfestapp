<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import UserForm from '@/components/users/UserForm.vue';
import Layout from '@/layouts/admin/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: route('admin.users.index'),
    },
    {
        title: 'Create',
        href: route('admin.users.create'),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <div class="px-4 py-6">
            <Heading description="Create a new user" title="Create User" />
            <UserForm />
        </div>
    </Layout>
</template>
