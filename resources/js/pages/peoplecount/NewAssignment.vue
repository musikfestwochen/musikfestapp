<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import AssignmentForm from '@/components/peoplecount/assignments/AssignmentForm.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization, PeoplecountEvent, PeoplecountSensor } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    organization: Organization;
    events: PeoplecountEvent[];
    sensors: PeoplecountSensor[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: route('organization.dashboard', { organization: props.organization.slug }),
    },
    {
        title: 'Assignments',
        href: route('peoplecount.assignments.index', { organization: props.organization.slug }),
    },
    {
        title: 'Create',
        href: route('peoplecount.assignments.create', { organization: props.organization.slug }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Create Assignment" />

        <div class="px-4 py-6">
            <Heading description="Create a new sensor assignment" title="Create Assignment" />
            <AssignmentForm :events="props.events" :organization="props.organization" :sensors="props.sensors" />
        </div>
    </Layout>
</template>
