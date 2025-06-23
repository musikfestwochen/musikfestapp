<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
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
        title: 'Create',
        href: route('peoplecount.sensors.edit', { organization: props.organization.slug, sensor: props.sensor.id }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Sensors" />

        <div class="px-4 py-6">
            <Heading description="Create a new sensor" title="Create Sensor" />
            <SensorForm :organization="props.organization" :sensor="props.sensor" />
        </div>
    </Layout>
</template>
