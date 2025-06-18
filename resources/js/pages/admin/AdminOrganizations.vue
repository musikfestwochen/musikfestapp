<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import OrganizationsTable from '@/components/organizations/OrganizationsTable.vue';
import AdminAppLayout from '@/layouts/admin/AdminAppLayout.vue';
import { type BreadcrumbItem, Organization } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Organizations',
        href: route('admin.organizations.index'),
    },
];

defineProps<{
    organizations?: Organization[];
    status?: string;
}>();
</script>

<template>
    <AdminAppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Organizations" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="px-4 py-6">
            <Heading description="See all your organizations" title="Organizations" />
            <div class="mt-4">
                <OrganizationsTable :organizations="organizations" />
            </div>
        </div>
    </AdminAppLayout>
</template>
