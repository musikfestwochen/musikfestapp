<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import OrganizationForm from '@/components/OrganizationForm.vue';
import AdminAppLayout from '@/layouts/admin/AdminAppLayout.vue';
import { BreadcrumbItem, Organization } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ organization: Organization }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Organizations',
        href: '/organizations',
    },
    {
        title: 'Edit ' + props.organization.name,
        href: '/organizations/' + props.organization.id + '/edit',
    },
];
</script>

<template>
    <AdminAppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Organizations" />

        <div class="px-4 py-6">
            <Heading class="mb-4" description="Edit organization details" level="2" title="Edit Organization" />
            <OrganizationForm :organization="props.organization" />
        </div>
    </AdminAppLayout>
</template>
