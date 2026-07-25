<script setup lang="ts">
import WindHistoryChart from '@/components/stage-safety/WindHistoryChart.vue';
import type { Organization, StageSafetyWindHistoryPayload } from '@/types';
import { useHttp } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { ref, watch } from 'vue';

const props = defineProps<{ organization: Organization }>();
const request = useHttp<{ from: string; to: string }, StageSafetyWindHistoryPayload>({ from: '', to: '' });
const data = ref<StageSafetyWindHistoryPayload | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const timeRange = ref('1h');
let refreshQueued = false;

function timeParams(): { from: string; to: string } {
    const to = new Date();
    const from = new Date(to.getTime() - Number.parseInt(timeRange.value) * 60 * 60 * 1000);
    return { from: from.toISOString(), to: to.toISOString() };
}

async function fetchHistory(): Promise<void> {
    if (request.processing) {
        refreshQueued = true;
        return;
    }

    const requestedRange = timeRange.value;
    Object.assign(request, timeParams());

    try {
        error.value = null;
        const response = await request.get(route('stage-safety.wind-history.index', { organization: props.organization.slug }));
        if (requestedRange === timeRange.value) data.value = response;
    } catch {
        error.value = 'Failed to load wind history.';
    } finally {
        loading.value = false;
        if (refreshQueued) {
            refreshQueued = false;
            void fetchHistory();
        }
    }
}

watch(timeRange, () => {
    data.value = null;
    loading.value = true;
    void fetchHistory();
});

useIntervalFn(fetchHistory, 30_000, { immediateCallback: true });
</script>

<template>
    <WindHistoryChart v-model:time-range="timeRange" :data="data" :loading="loading" :error="error" />
</template>
