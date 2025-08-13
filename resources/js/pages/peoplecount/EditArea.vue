<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import AlertForm from '@/components/peoplecount/alerts/AlertForm.vue';
import AlertsTable from '@/components/peoplecount/alerts/AlertsTable.vue';
import AreaForm from '@/components/peoplecount/areas/AreaForm.vue';
import AssignmentCard from '@/components/peoplecount/cards/AssignmentCard.vue';
import EmptyStateCard from '@/components/peoplecount/cards/EmptyStateCard.vue';
import EventDetailsCard from '@/components/peoplecount/cards/EventDetailsCard.vue';
import RecurringResetTable from '@/components/peoplecount/resets/RecurringResetTable.vue';
import SingleResetTable from '@/components/peoplecount/resets/SingleResetTable.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization, PeoplecountArea, PeoplecountEvent } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

// Local type for optional alerts prop
export type AlertType = 'occupancy_alert';
export type AlertChannel = 'email' | 'vonage';
export interface AlertDTO {
    id: number;
    area_id: number;
    type: AlertType;
    channel: AlertChannel;
    cooldown_seconds: number;
    occupancy_alert_threshold?: number | null;
    created_by?: number | null;
    creator?: { id: number; name: string } | null;
    recipients?: { id: number; name: string; email?: string }[];
    created_at?: string;
}

const props = defineProps<{
    area: PeoplecountArea;
    organization: Organization;
    events: PeoplecountEvent[];
    alerts?: AlertDTO[];
}>();

const activeTab = ref('details');
const hasLoadedAlerts = ref(!!props.alerts);

watch(activeTab, (val) => {
    if (val === 'alerts' && !hasLoadedAlerts.value) {
        router.reload({
            only: ['alerts'],
            onFinish: () => {
                hasLoadedAlerts.value = true;
            },
            preserveState: true,
            preserveScroll: true,
        });
    }
});

function refreshAlerts() {
    router.reload({ only: ['alerts'], preserveState: true, preserveScroll: true });
}

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

            <Tabs v-model="activeTab" class="mt-4 w-full">
                <div class="flex items-center justify-between">
                    <TabsList class="max-w-full justify-start overflow-x-auto">
                        <TabsTrigger class="shrink-0" value="details">Details</TabsTrigger>
                        <TabsTrigger :disabled="!area.event" class="shrink-0" value="event">Event</TabsTrigger>
                        <TabsTrigger class="shrink-0" value="assignments">Assignments</TabsTrigger>
                        <TabsTrigger class="shrink-0" value="resets-single">Manual Resets</TabsTrigger>
                        <TabsTrigger class="shrink-0" value="resets-recurring">Recurring Resets</TabsTrigger>
                        <TabsTrigger class="shrink-0" value="alerts">Alerts</TabsTrigger>
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

                <TabsContent class="mt-6" value="alerts">
                    <div class="space-y-6">
                        <template v-if="!props.alerts">
                            <Skeleton class="h-8 w-40" />
                            <Skeleton class="h-24 w-full" />
                            <Skeleton class="h-64 w-full" />
                        </template>
                        <template v-else>
                            <Heading title="Alerts" />
                            <AlertForm :area-id="props.area.id" :on-created="refreshAlerts" :org-slug="props.organization.slug" />
                            <div class="mt-4">
                                <AlertsTable
                                    :alerts="props.alerts"
                                    :area-id="props.area.id"
                                    :on-changed="refreshAlerts"
                                    :org-slug="props.organization.slug"
                                />
                            </div>
                        </template>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </Layout>
</template>
