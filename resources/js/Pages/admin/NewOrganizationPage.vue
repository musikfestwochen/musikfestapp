<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import OrganizationForm from '@/components/OrganizationForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Organizations',
        href: '/organizations',
    },
    {
        title: 'Create',
        href: '/organizations/create',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Organizations" />

        <div class="px-4 py-6">
            <Heading class="mb-4" description="Create a new organization" level="2" title="Create Organization" />
            <OrganizationForm />
        </div>
    </AppLayout>
</template>
