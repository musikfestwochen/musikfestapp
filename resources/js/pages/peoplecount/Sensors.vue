<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import SensorsTable from '@/components/peoplecount/sensors/SensorsTable.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { type BreadcrumbItem, Organization, PeoplecountSensor } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    sensors: PeoplecountSensor[];
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
        title: 'Sensors',
        href: route('peoplecount.sensors.index', { organization: props.organization.slug }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Sensors" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="px-4 py-6">
            <Heading description="See all your sensors and manage their API tokens" title="Sensors" />

            <div class="mt-4">
                <SensorsTable :organization="props.organization" :sensors="sensors" />
            </div>
        </div>
    </Layout>
</template>
