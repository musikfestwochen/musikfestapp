<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import MeasurementCard from '@/components/peoplecount/cards/MeasurementCard.vue';
import SensorForm from '@/components/peoplecount/sensors/SensorForm.vue';
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
                    <MeasurementCard v-for="measurement in props.sensor.interval_counts" :key="measurement.id" :measurement="measurement" />
                </div>

                <div v-else class="text-muted-foreground mt-4 text-sm">No measurements available.</div>
            </div>
        </div>
    </Layout>
</template>
