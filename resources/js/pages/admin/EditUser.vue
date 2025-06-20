<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import UserForm from '@/components/users/UserForm.vue';
import Layout from '@/layouts/admin/Layout.vue';
import { BreadcrumbItem, User } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ user: User }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: route('admin.users.index'),
    },
    {
        title: 'Edit ' + props.user.name,
        href: route('admin.users.edit', { id: props.user.id }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <div class="px-4 py-6">
            <Heading class="mb-4" description="Edit user details" level="2" title="Edit User" />
            <UserForm :user="props.user" />
        </div>
    </Layout>
</template>
