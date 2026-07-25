<script setup lang="ts">
import CurrentWindDisplay from '@/components/stage-safety/CurrentWindDisplay.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { Organization, StageSafetyCurrentWindPayload } from '@/types';
import { useHttp } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { ref } from 'vue';

const props = defineProps<{ organization: Organization }>();
const request = useHttp<Record<string, never>, StageSafetyCurrentWindPayload>({});
const data = ref<StageSafetyCurrentWindPayload | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

async function fetchCurrentWind(): Promise<void> {
    if (request.processing) return;

    try {
        error.value = null;
        data.value = await request.get(route('stage-safety.current-wind.index', { organization: props.organization.slug }));
    } catch {
        error.value = 'Failed to load current wind.';
    } finally {
        loading.value = false;
    }
}

useIntervalFn(fetchCurrentWind, 10_000, { immediateCallback: true });
</script>

<template>
    <Card class="flex h-full flex-col lg:col-span-2">
        <CardHeader>
            <CardTitle>Current Wind</CardTitle>
        </CardHeader>
        <CardContent class="flex flex-1 flex-col">
            <div
                v-if="error"
                role="alert"
                class="mb-4 rounded-md bg-red-50 p-2 text-center text-sm text-red-600 dark:bg-red-950/30 dark:text-red-400"
            >
                {{ error }}
            </div>
            <div v-if="loading && !data" class="grid gap-4 sm:grid-cols-2">
                <Skeleton class="h-48 w-full rounded-xl" />
                <Skeleton class="hidden h-48 w-full rounded-xl sm:block" />
            </div>
            <div v-else-if="!data?.sensors.length" class="text-muted-foreground flex min-h-48 items-center justify-center text-center">
                No sensors currently report fresh wind data.
            </div>
            <CurrentWindDisplay v-else :sensors="data.sensors" />
        </CardContent>
    </Card>
</template>
