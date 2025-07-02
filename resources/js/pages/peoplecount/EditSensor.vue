<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import SensorForm from '@/components/peoplecount/sensors/SensorForm.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization, PeoplecountSensor } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    sensor: PeoplecountSensor;
    organization: Organization;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: route('organization.dashboard', { organization: props.organization.slug }),
    },
    {
        title: 'Sensors',
        href: route('peoplecount.sensors.index', { organization: props.organization.slug }),
    },
    {
        title: 'Edit',
        href: route('peoplecount.sensors.edit', {
            organization: props.organization.slug,
            sensor: props.sensor.id,
        }),
    },
];

const formatTimestamp = (iso: string) =>
    new Date(iso).toLocaleString(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    });
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Sensors" />

        <div class="px-4 py-6">
            <Heading title="Edit Sensor" />
            <SensorForm :organization="props.organization" :sensor="props.sensor" />

            <div class="mt-8">
                <Heading title="Last Measurements" />

                <div v-if="(props.sensor.interval_counts?.length ?? 0) > 0" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="measurement in props.sensor.interval_counts" :key="measurement.id">
                        <CardHeader>
                            <CardTitle>Measurement Interval</CardTitle>
                            <CardDescription>
                                <div class="flex flex-wrap gap-1 md:flex-nowrap">
                                    <span class="whitespace-nowrap"> From: {{ formatTimestamp(measurement.ts_from) }} </span>
                                    <span class="hidden md:inline">—</span>
                                    <span class="whitespace-nowrap"> To: {{ formatTimestamp(measurement.ts_to) }} </span>
                                </div>
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="flex gap-4">
                            <Badge variant="secondary">In: {{ measurement.count_in }}</Badge>
                            <Badge variant="secondary">Out: {{ measurement.count_out }}</Badge>
                        </CardContent>
                    </Card>
                </div>

                <div v-else class="mt-4 text-sm text-muted-foreground">No measurements available.</div>
            </div>
        </div>
    </Layout>
</template>
