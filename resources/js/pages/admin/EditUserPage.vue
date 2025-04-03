<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import UserForm from '@/components/UserForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, User } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ user: User }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '/users',
    },
    {
        title: 'Edit ' + props.user.name,
        href: '/users/create',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <div class="px-4 py-6">
            <Heading class="mb-4" description="Create a new user" level="2" title="Create User" />
            <UserForm :user="props.user" />
        </div>
    </AppLayout>
</template>
