<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import SensorForm from '@/components/stage-safety/sensors/SensorForm.vue';
import SensorTokenDialog from '@/components/stage-safety/sensors/SensorTokenDialog.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import type {
    BreadcrumbItem,
    Organization,
    StageSafetySensor,
    StageSafetySensorCreatedResponse,
    StageSafetySensorFormData,
    StageSafetySensorType,
} from '@/types';
import { Head, router, useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    organization: Organization;
    sensorTypes: StageSafetySensorType[];
}>();

const defaultType = props.sensorTypes[0];
const form = useHttp<StageSafetySensorFormData, StageSafetySensorCreatedResponse>({
    manufacturer: defaultType?.manufacturer || '',
    model: defaultType?.model || '',
    identifier: '',
    name: null,
    location: null,
    stale_after_seconds: 300,
});
const token = ref<string | null>(null);
const createdSensor = ref<StageSafetySensor | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: route('organization.dashboard', { organization: props.organization.slug }),
    },
    {
        title: 'Stage Safety Sensors',
        href: route('stage-safety.sensors.index', { organization: props.organization.slug }),
    },
    {
        title: 'Create',
        href: route('stage-safety.sensors.create', { organization: props.organization.slug }),
    },
];

async function submit(): Promise<void> {
    try {
        const response = await form.post(route('stage-safety.sensors.store', { organization: props.organization.slug }));
        createdSensor.value = response.sensor;
        token.value = response.token;
    } catch {
        // useHttp exposes validation errors through form.errors.
    }
}

function updateForm(values: Partial<StageSafetySensorFormData>): void {
    Object.assign(form, values);
}

function acknowledgeToken(): void {
    const sensor = createdSensor.value;
    token.value = null;
    createdSensor.value = null;

    if (sensor) {
        router.visit(
            route('stage-safety.sensors.edit', {
                organization: props.organization.slug,
                stageSafetySensor: sensor.id,
            }),
        );
    }
}
</script>

<template>
    <Layout :breadcrumbs="breadcrumbs">
        <Head title="Create Stage Safety Sensor" />

        <div class="px-4 py-6">
            <Heading description="Register a sensor and issue its API token" title="Create Stage Safety Sensor" />
            <div class="mt-6">
                <SensorForm
                    :errors="form.errors"
                    :form="form"
                    :processing="form.processing"
                    :sensor-types="sensorTypes"
                    submit-label="Create Sensor"
                    @change="updateForm"
                    @submit="submit"
                />
            </div>
        </div>

        <SensorTokenDialog :open="token !== null" :token="token || ''" @acknowledged="acknowledgeToken" />
    </Layout>
</template>
