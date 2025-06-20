<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import UserForm from '@/components/users/UserForm.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
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
    {
        title: 'Create',
        href: route('orgmgmt.users.create', { organization: props.organization.slug }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <div class="px-4 py-6">
            <Heading description="Create a new user" title="Create User" />
            <UserForm :organization="props.organization" />
        </div>
    </Layout>
</template>
