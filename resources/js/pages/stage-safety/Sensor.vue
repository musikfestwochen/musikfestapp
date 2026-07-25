<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import CurrentWindDisplay from '@/components/stage-safety/CurrentWindDisplay.vue';
import WindHistoryChart from '@/components/stage-safety/WindHistoryChart.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { usePermissions } from '@/composables/usePermissions';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import type { BreadcrumbItem, Organization, StageSafetySensor, StageSafetySensorMonitoringPayload } from '@/types';
import { Head, Link, useHttp } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    organization: Organization;
    sensor: StageSafetySensor;
}>();

const { can } = usePermissions();
const request = useHttp<{ from: string; to: string }, StageSafetySensorMonitoringPayload>({ from: '', to: '' });
const monitoring = ref<StageSafetySensorMonitoringPayload | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const timeRange = ref('1h');
let refreshQueued = false;

const title = props.sensor.name || props.sensor.identifier;
const currentSensors = computed(() => (monitoring.value ? [monitoring.value.current] : []));
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
        title,
        href: route('stage-safety.sensors.show', { organization: props.organization.slug, stageSafetySensor: props.sensor.id }),
    },
];

function timeParams(): { from: string; to: string } {
    const to = new Date();
    const from = new Date(to.getTime() - Number.parseInt(timeRange.value) * 60 * 60 * 1000);
    return { from: from.toISOString(), to: to.toISOString() };
}

async function fetchMonitoring(): Promise<void> {
    if (request.processing) {
        refreshQueued = true;
        return;
    }

    const requestedRange = timeRange.value;
    Object.assign(request, timeParams());

    try {
        error.value = null;
        const response = await request.get(
            route('stage-safety.sensors.monitoring.index', {
                organization: props.organization.slug,
                stageSafetySensor: props.sensor.id,
            }),
        );
        if (requestedRange === timeRange.value) monitoring.value = response;
    } catch {
        error.value = 'Failed to load sensor monitoring data.';
    } finally {
        loading.value = false;
        if (refreshQueued) {
            refreshQueued = false;
            void fetchMonitoring();
        }
    }
}

watch(timeRange, () => {
    monitoring.value = null;
    loading.value = true;
    void fetchMonitoring();
});

useIntervalFn(fetchMonitoring, 30_000, { immediateCallback: true });
</script>

<template>
    <Layout :breadcrumbs="breadcrumbs">
        <Head :title="`${title} Monitoring`" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <Heading :title="title" :description="sensor.location || `${sensor.manufacturer} ${sensor.model} · ${sensor.identifier}`">
                <Button v-if="can('stage-safety.sensors.edit')" as-child variant="outline">
                    <Link :href="route('stage-safety.sensors.edit', { organization: organization.slug, stageSafetySensor: sensor.id })">
                        Edit sensor
                    </Link>
                </Button>
            </Heading>

            <div
                v-if="error"
                role="alert"
                class="rounded-md border border-red-200 bg-red-50 p-3 text-center text-sm text-red-600 dark:border-red-900 dark:bg-red-950/30 dark:text-red-400"
            >
                {{ error }}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Current Wind</CardTitle>
                </CardHeader>
                <CardContent>
                    <Skeleton v-if="loading && !monitoring" class="h-52 w-full rounded-xl" />
                    <CurrentWindDisplay v-else-if="currentSensors.length" :sensors="currentSensors" />
                    <div v-else class="text-muted-foreground flex min-h-52 items-center justify-center">No monitoring data available.</div>
                </CardContent>
            </Card>

            <WindHistoryChart v-model:time-range="timeRange" :data="monitoring?.history ?? null" :loading="loading" :error="error" />
        </div>
    </Layout>
</template>
