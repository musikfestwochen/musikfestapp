<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import AssignmentForm from '@/components/peoplecount/assignments/AssignmentForm.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization, PeoplecountAssignment, PeoplecountEvent, PeoplecountSensor } from '@/types';
import { formatLocalDateTime } from '@/utils/dateTimeHelpers';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    assignment: PeoplecountAssignment;
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
        title: 'Edit',
        href: route('peoplecount.assignments.edit', {
            organization: props.organization.slug,
            assignment: props.assignment.id,
        }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Edit Assignment" />

        <div class="px-4 py-6">
            <Heading title="Edit Assignment" />
            <AssignmentForm :assignment="props.assignment" :events="props.events" :organization="props.organization" :sensors="props.sensors" />

            <!-- Assignment Details -->
            <div class="mt-8">
                <Heading title="Assignment Details" />
                <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Event Information -->
                    <div v-if="assignment.event" class="rounded-lg border bg-card p-6">
                        <h3 class="text-lg font-semibold">Event</h3>
                        <div class="mt-2 space-y-2">
                            <p><span class="font-medium">Name:</span> {{ assignment.event.name }}</p>
                            <p><span class="font-medium">Start:</span> {{ formatLocalDateTime(assignment.event.starts_at) }}</p>
                            <p><span class="font-medium">End:</span> {{ formatLocalDateTime(assignment.event.ends_at) }}</p>
                        </div>
                    </div>

                    <!-- Area Information -->
                    <div v-if="assignment.area" class="rounded-lg border bg-card p-6">
                        <h3 class="text-lg font-semibold">Area</h3>
                        <div class="mt-2 space-y-2">
                            <p><span class="font-medium">Name:</span> {{ assignment.area.name }}</p>
                        </div>
                    </div>

                    <!-- Sensor Information -->
                    <div v-if="assignment.sensor" class="rounded-lg border bg-card p-6">
                        <h3 class="text-lg font-semibold">Sensor</h3>
                        <div class="mt-2 space-y-2">
                            <p><span class="font-medium">Vendor:</span> {{ assignment.sensor.vendor }}</p>
                            <p><span class="font-medium">Model:</span> {{ assignment.sensor.model }}</p>
                            <p><span class="font-medium">Serial:</span> {{ assignment.sensor.serial }}</p>
                        </div>
                    </div>

                    <!-- Assignment Configuration -->
                    <div class="rounded-lg border bg-card p-6">
                        <h3 class="text-lg font-semibold">Configuration</h3>
                        <div class="mt-2 space-y-2">
                            <p>
                                <span class="font-medium">Direction:</span>
                                <span :class="assignment.direction_flipped ? 'text-orange-600' : 'text-green-600'">
                                    {{ assignment.direction_flipped ? 'Flipped' : 'Normal' }}
                                </span>
                            </p>
                            <p><span class="font-medium">Active From:</span> {{ formatLocalDateTime(assignment.active_from) }}</p>
                            <p><span class="font-medium">Active To:</span> {{ formatLocalDateTime(assignment.active_to) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>
