<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { Organization, StageSafetyHealthSensor, StageSafetySensorHealthPayload } from '@/types';
import { getRelativeTime } from '@/utils/dateTimeHelpers';
import { useHttp } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { CircleCheck, CircleHelp, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ organization: Organization }>();
const request = useHttp<Record<string, never>, StageSafetySensorHealthPayload>({});
const data = ref<StageSafetySensorHealthPayload | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const issues = computed(() => [...(data.value?.stale ?? []), ...(data.value?.never_seen ?? [])]);

async function fetchHealth(): Promise<void> {
    if (request.processing) return;

    try {
        const response = await request.get(route('stage-safety.sensor-health.index', { organization: props.organization.slug }));
        error.value = null;
        data.value = response;
    } catch {
        error.value = 'Failed to load sensor health.';
    } finally {
        loading.value = false;
    }
}

function sensorName(sensor: StageSafetyHealthSensor): string {
    return sensor.name || sensor.identifier;
}

useIntervalFn(fetchHealth, 10_000, { immediateCallback: true });
</script>

<template>
    <Card class="flex h-full flex-col">
        <CardHeader class="flex flex-row items-center justify-between gap-3 space-y-0">
            <CardTitle>Sensor Health</CardTitle>
            <Badge v-if="data?.all_fresh" class="bg-green-600 hover:bg-green-600">Good</Badge>
            <Badge v-else-if="data?.total" variant="destructive">Attention</Badge>
        </CardHeader>
        <CardContent class="flex flex-1 flex-col">
            <div
                v-if="error"
                role="alert"
                class="mb-4 rounded-md bg-red-50 p-2 text-center text-sm text-red-600 dark:bg-red-950/30 dark:text-red-400"
            >
                {{ error }}
            </div>
            <div v-if="loading && !data" class="space-y-3">
                <Skeleton class="h-10 w-full" />
                <Skeleton class="h-16 w-full" />
                <Skeleton class="h-16 w-full" />
            </div>
            <div v-else-if="!data?.total" class="text-muted-foreground flex min-h-48 items-center justify-center text-center">
                No active Stage Safety sensors configured.
            </div>
            <div v-else class="flex flex-1 flex-col gap-4">
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg border p-2">
                        <p class="text-xl font-semibold text-green-600 dark:text-green-400">{{ data.fresh.length }}</p>
                        <p class="text-muted-foreground text-xs">Fresh</p>
                    </div>
                    <div class="rounded-lg border p-2">
                        <p class="text-destructive text-xl font-semibold">{{ data.stale.length }}</p>
                        <p class="text-muted-foreground text-xs">Stale</p>
                    </div>
                    <div class="rounded-lg border p-2">
                        <p class="text-xl font-semibold">{{ data.never_seen.length }}</p>
                        <p class="text-muted-foreground text-xs">Never seen</p>
                    </div>
                </div>

                <div v-if="data.all_fresh" class="flex flex-1 items-center justify-center gap-2 text-sm text-green-700 dark:text-green-400">
                    <CircleCheck class="size-5" />
                    All sensors report fresh data
                </div>

                <ul v-else class="divide-y rounded-lg border">
                    <li v-for="sensor in issues" :key="sensor.id" class="flex items-center gap-3 p-3 text-sm">
                        <TriangleAlert v-if="sensor.status === 'stale'" class="text-destructive size-4 shrink-0" />
                        <CircleHelp v-else class="text-muted-foreground size-4 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ sensorName(sensor) }}</p>
                            <p class="text-muted-foreground text-xs">
                                {{
                                    sensor.latest_observed_at ? `Observed ${getRelativeTime(new Date(sensor.latest_observed_at))}` : 'Never observed'
                                }}
                            </p>
                        </div>
                        <Badge :variant="sensor.status === 'stale' ? 'destructive' : 'secondary'">{{ sensor.status.replace('_', ' ') }}</Badge>
                    </li>
                </ul>
            </div>
        </CardContent>
    </Card>
</template>
