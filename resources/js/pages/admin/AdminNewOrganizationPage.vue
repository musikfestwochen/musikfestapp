<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import OrganizationForm from '@/components/OrganizationForm.vue';
import AdminAppLayout from '@/layouts/admin/AdminAppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Organizations',
        href: route('admin.organizations.index'),
    },
    {
        title: 'Create',
        href: route('admin.organizations.create'),
    },
];
</script>

<template>
    <AdminAppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Organizations" />

        <div class="px-4 py-6">
            <Heading class="mb-4" description="Create a new organization" level="2" title="Create Organization" />
            <OrganizationForm />
        </div>
    </AdminAppLayout>
</template>
