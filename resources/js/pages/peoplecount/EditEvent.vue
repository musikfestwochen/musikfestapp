<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import EventForm from '@/components/peoplecount/events/EventForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization, PeoplecountEvent } from '@/types';
import { Head } from '@inertiajs/vue3';
import { MapPin, Users } from 'lucide-vue-next';

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
                    <Card v-for="area in event.areas" :key="area.id">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <MapPin class="h-4 w-4" />
                                {{ area.name }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground">
                                {{ area.assignments?.length || 0 }} assignment{{ (area.assignments?.length || 0) !== 1 ? 's' : '' }}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Assignments Information (if available) -->
            <div v-if="event.assignments && event.assignments.length > 0" class="mt-8">
                <Heading title="Assignments" />
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="assignment in event.assignments" :key="assignment.id">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Users class="h-4 w-4" />
                                {{ assignment.area?.name || 'Unknown Area' }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm font-medium">{{ assignment.sensor?.vendor }} {{ assignment.sensor?.model }}</p>
                            <p class="text-sm text-muted-foreground">Direction: {{ assignment.direction }}</p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ new Date(assignment.active_from).toLocaleDateString() }} -
                                {{ new Date(assignment.active_to).toLocaleDateString() }}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </Layout>
</template>
