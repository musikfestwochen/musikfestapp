<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import AreaCard from '@/components/peoplecount/cards/AreaCard.vue';
import AssignmentCard from '@/components/peoplecount/cards/AssignmentCard.vue';
import EventForm from '@/components/peoplecount/events/EventForm.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization, PeoplecountEvent } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    event: PeoplecountEvent;
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
        title: 'Edit',
        href: route('peoplecount.events.edit', {
            organization: props.organization.slug,
            event: props.event.id,
        }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Events" />

        <div class="px-4 py-6">
            <Heading title="Edit Event" />
            <EventForm :event="props.event" :organization="props.organization" />

            <!-- Areas Information (if available) -->
            <div v-if="event.areas && event.areas.length > 0" class="mt-8">
                <Heading title="Areas" />
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <AreaCard v-for="area in event.areas" :key="area.id" :area="area" />
                </div>
            </div>

            <!-- Assignments Information (if available) -->
            <div v-if="event.assignments && event.assignments.length > 0" class="mt-8">
                <Heading title="Assignments" />
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <AssignmentCard v-for="assignment in event.assignments" :key="assignment.id" :assignment="assignment" :show-area-name="true" />
                </div>
            </div>
        </div>
    </Layout>
</template>
