<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import UserForm from '@/components/users/UserForm.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization, RoleOption, User } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    user: User;
    organization: Organization;
    availableRoles: RoleOption[];
    selectedRoles: string[];
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
        title: 'Edit ' + props.user.name,
        href: route('orgmgmt.users.edit', { user: props.user.id, organization: props.organization.slug }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <div class="px-4 py-6">
            <Heading description="Edit user details" title="Edit User" />
            <UserForm
                :available-roles="props.availableRoles"
                :organization="props.organization"
                :selected-roles="props.selectedRoles"
                :user="props.user"
            />
        </div>
    </Layout>
</template>
