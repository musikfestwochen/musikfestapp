<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import EventForm from '@/components/peoplecount/events/EventForm.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    organization: Organization;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: route('organization.dashboard', { organization: props.organization.slug }),
    },
    {
        title: 'Events',
        href: route('peoplecount.events.index', { organization: props.organization.slug }),
    },
    {
        title: 'Create',
        href: route('peoplecount.events.create', { organization: props.organization.slug }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Events" />

        <div class="px-4 py-6">
            <Heading description="Create a new event" title="Create Event" />
            <EventForm :organization="props.organization" />
        </div>
    </Layout>
</template>
