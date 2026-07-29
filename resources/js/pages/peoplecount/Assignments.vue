<script lang="ts" setup>
import AssignmentsTable from '@/components/peoplecount/assignments/AssignmentsTable.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { type BreadcrumbItem, Organization, PeoplecountAssignment } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    assignments: PeoplecountAssignment[];
    status?: string;
    organization: Organization;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: `/${props.organization.slug}/dashboard`,
    },
    {
        title: 'People Counting',
        href: `/${props.organization.slug}/peoplecount`,
    },
    {
        title: 'Assignments',
        href: route('peoplecount.assignments.index', { organization: props.organization.slug }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Assignments" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="px-4 py-6">
            <AssignmentsTable :assignments="assignments" :organization="props.organization" />
        </div>
    </Layout>
</template>
