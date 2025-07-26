<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import AreaForm from '@/components/peoplecount/areas/AreaForm.vue';
import AssignmentCard from '@/components/peoplecount/cards/AssignmentCard.vue';
import EmptyStateCard from '@/components/peoplecount/cards/EmptyStateCard.vue';
import EventDetailsCard from '@/components/peoplecount/cards/EventDetailsCard.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization, PeoplecountArea, PeoplecountEvent } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    area: PeoplecountArea;
    organization: Organization;
    events: PeoplecountEvent[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: route('organization.dashboard', { organization: props.organization.slug }),
    },
    {
        title: 'Areas',
        href: route('peoplecount.areas.index', { organization: props.organization.slug }),
    },
    {
        title: 'Edit',
        href: route('peoplecount.areas.edit', {
            organization: props.organization.slug,
            area: props.area.id,
        }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Areas" />

        <div class="px-4 py-6">
            <Heading title="Edit Area" />
            <AreaForm :area="props.area" :events="props.events" :organization="props.organization" />

            <!-- Event Information -->
            <div v-if="area.event" class="mt-8">
                <Heading title="Event Details" />
                <div class="mt-4">
                    <EventDetailsCard :event="area.event" />
                </div>
            </div>

            <!-- Assignments Information (if available) -->
            <div v-if="area.assignments && area.assignments.length > 0" class="mt-8">
                <Heading title="Assignments" />
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <AssignmentCard v-for="assignment in area.assignments" :key="assignment.id" :assignment="assignment" :show-sensor-name="true" />
                </div>
            </div>

            <!-- No Assignments Message -->
            <div v-else class="mt-8">
                <Heading title="Assignments" />
                <div class="mt-4">
                    <EmptyStateCard message="No assignments found for this area." />
                </div>
            </div>
        </div>
    </Layout>
</template>
