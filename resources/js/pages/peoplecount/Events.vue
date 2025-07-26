<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import EventsTable from '@/components/peoplecount/events/EventsTable.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { type BreadcrumbItem, Organization, PeoplecountEvent } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    events: PeoplecountEvent[];
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
        title: 'Events',
        href: route('peoplecount.events.index', { organization: props.organization.slug }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Events" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="px-4 py-6">
            <Heading description="Manage your people counting events and their schedules" title="Events" />

            <div class="mt-4">
                <EventsTable :events="events" :organization="props.organization" />
            </div>
        </div>
    </Layout>
</template>
