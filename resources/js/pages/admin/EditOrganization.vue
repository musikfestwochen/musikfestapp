<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import OrganizationForm from '@/components/organizations/OrganizationForm.vue';
import Layout from '@/layouts/admin/Layout.vue';
import { BreadcrumbItem, Organization } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ organization: Organization }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Organizations',
        href: route('admin.organizations.index'),
    },
    {
        title: 'Edit ' + props.organization.name,
        href: route('admin.organizations.edit', { id: props.organization.id }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Organizations" />

        <div class="px-4 py-6">
            <Heading description="Edit organization details" title="Edit Organization" />
            <OrganizationForm :organization="props.organization" />
        </div>
    </Layout>
</template>
