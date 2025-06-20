<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import OrganizationForm from '@/components/organizations/OrganizationForm.vue';
import Layout from '@/layouts/admin/Layout.vue';
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
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Organizations" />

        <div class="px-4 py-6">
            <Heading description="Create a new organization" title="Create Organization" />
            <OrganizationForm />
        </div>
    </Layout>
</template>
