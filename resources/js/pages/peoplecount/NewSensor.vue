<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import SensorForm from '@/components/peoplecount/sensors/SensorForm.vue';
import SensorTokenDialog from '@/components/SensorTokenDialog.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import type { BreadcrumbItem, Organization, PeoplecountSensor, PeoplecountSensorCreatedResponse, PeoplecountSensorFormData } from '@/types';
import { Head, router, useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    organization: Organization;
    status?: string;
}>();

const form = useHttp<PeoplecountSensorFormData, PeoplecountSensorCreatedResponse>({
    vendor: '',
    model: '',
    serial: '',
    name: null,
});
const token = ref<string | null>(null);
const createdSensor = ref<PeoplecountSensor | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
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
        href: route('peoplecount.sensors.create', { organization: props.organization.slug }),
    },
];

async function submit(): Promise<void> {
    try {
        const response = await form.post(route('peoplecount.sensors.store', { organization: props.organization.slug }));
        createdSensor.value = response.sensor;
        token.value = response.token;
    } catch {
        // useHttp exposes validation errors through form.errors.
    }
}

function updateForm(values: Partial<PeoplecountSensorFormData>): void {
    Object.assign(form, values);
}

function acknowledgeToken(): void {
    const sensor = createdSensor.value;
    token.value = null;
    createdSensor.value = null;

    if (sensor) {
        router.visit(
            route('peoplecount.sensors.edit', {
                organization: props.organization.slug,
                sensor: sensor.id,
            }),
        );
    }
}
</script>

<template>
    <Layout :breadcrumbs="breadcrumbs">
        <Head title="Create Sensor" />

        <div class="px-4 py-6">
            <Heading description="Register a sensor and issue its API token" title="Create Sensor" />
            <div class="mt-6">
                <SensorForm
                    :errors="form.errors"
                    :form="form"
                    :processing="form.processing"
                    submit-label="Create Sensor"
                    @change="updateForm"
                    @submit="submit"
                />
            </div>
        </div>

        <SensorTokenDialog :open="token !== null" :token="token || ''" @acknowledged="acknowledgeToken" />
    </Layout>
</template>
