<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import AreaForm from '@/components/peoplecount/areas/AreaForm.vue';
import AssignmentCard from '@/components/peoplecount/cards/AssignmentCard.vue';
import EmptyStateCard from '@/components/peoplecount/cards/EmptyStateCard.vue';
import EventDetailsCard from '@/components/peoplecount/cards/EventDetailsCard.vue';
import RecurringResetTable from '@/components/peoplecount/resets/RecurringResetTable.vue';
import SingleResetTable from '@/components/peoplecount/resets/SingleResetTable.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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

            <Tabs class="mt-4 w-full" default-value="details">
                <div class="flex items-center justify-between">
                    <TabsList class="max-w-full justify-start overflow-x-auto">
                        <TabsTrigger class="shrink-0" value="details">Details</TabsTrigger>
                        <TabsTrigger :disabled="!area.event" class="shrink-0" value="event">Event</TabsTrigger>
                        <TabsTrigger class="shrink-0" value="assignments">Assignments</TabsTrigger>
                        <TabsTrigger class="shrink-0" value="resets-single">Manual Resets</TabsTrigger>
                        <TabsTrigger class="shrink-0" value="resets-recurring">Recurring Resets</TabsTrigger>
                    </TabsList>
                </div>

                <TabsContent class="mt-6" value="details">
                    <AreaForm :area="props.area" :events="props.events" :organization="props.organization" />
                </TabsContent>

                <TabsContent class="mt-6" value="event">
                    <div v-if="area.event">
                        <Heading title="Event Details" />
                        <div class="mt-4">
                            <EventDetailsCard :event="area.event" />
                        </div>
                    </div>
                    <div v-else>
                        <EmptyStateCard message="No event associated with this area." />
                    </div>
                </TabsContent>

                <TabsContent class="mt-6" value="assignments">
                    <Heading title="Assignments" />
                    <div v-if="area.assignments && area.assignments.length > 0" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <AssignmentCard
                            v-for="assignment in area.assignments"
                            :key="assignment.id"
                            :assignment="assignment"
                            :show-sensor-name="true"
                        />
                    </div>
                    <div v-else class="mt-4">
                        <EmptyStateCard message="No assignments found for this area." />
                    </div>
                </TabsContent>

                <TabsContent class="mt-6" value="resets-single">
                    <div>
                        <div class="flex items-center justify-between">
                            <Heading title="Manual Resets" />
                        </div>
                        <div class="mt-4">
                            <SingleResetTable :area="props.area" :organization="props.organization" :resets="area.area_single_resets || []" />
                        </div>
                    </div>
                </TabsContent>

                <TabsContent class="mt-6" value="resets-recurring">
                    <div>
                        <div class="flex items-center justify-between">
                            <Heading title="Recurring Resets" />
                        </div>
                        <div class="mt-4">
                            <RecurringResetTable :area="props.area" :organization="props.organization" :resets="area.area_recurring_resets || []" />
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </Layout>
</template>
