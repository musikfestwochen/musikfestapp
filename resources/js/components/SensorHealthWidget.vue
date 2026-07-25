<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { Organization, StageSafetySensorHealthPayload } from '@/types';
import { getRelativeTime } from '@/utils/dateTimeHelpers';
import { stageSafetySensorName } from '@/utils/stageSafety';
import { useHttp } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { computed, ref } from 'vue';

interface PeoplecountHealthSensor {
    id: number;
    serial: string;
    vendor: string;
    model: string;
    name: string | null;
    latest_ts: string | null;
}

interface PeoplecountSensorHealthPayload {
    last_updated: string;
    total: number;
    all_healthy: boolean;
    healthy: PeoplecountHealthSensor[];
    suspicious: PeoplecountHealthSensor[];
    unhealthy: PeoplecountHealthSensor[];
}

const props = defineProps<{
    organization: Organization;
    showPeoplecount: boolean;
    showStageSafety: boolean;
}>();

const peoplecountRequest = useHttp<Record<string, never>, PeoplecountSensorHealthPayload>({});
const stageSafetyRequest = useHttp<Record<string, never>, StageSafetySensorHealthPayload>({});
const peoplecount = ref<PeoplecountSensorHealthPayload | null>(null);
const stageSafety = ref<StageSafetySensorHealthPayload | null>(null);
const peoplecountLoading = ref(props.showPeoplecount);
const stageSafetyLoading = ref(props.showStageSafety);
const peoplecountError = ref<string | null>(null);
const stageSafetyError = ref<string | null>(null);

const stageSafetyIssues = computed(() => [...(stageSafety.value?.stale ?? []), ...(stageSafety.value?.never_seen ?? [])]);

function peoplecountSensorName(sensor: PeoplecountHealthSensor): string {
    return sensor.name || `${sensor.vendor} ${sensor.model}`;
}

async function fetchPeoplecount(): Promise<void> {
    if (!props.showPeoplecount || peoplecountRequest.processing) return;

    try {
        peoplecountError.value = null;
        peoplecount.value = await peoplecountRequest.get(route('peoplecount.sensor-health.index', { organization: props.organization.slug }));
    } catch {
        peoplecountError.value = 'Failed to load Peoplecount sensor health.';
    } finally {
        peoplecountLoading.value = false;
    }
}

async function fetchStageSafety(): Promise<void> {
    if (!props.showStageSafety || stageSafetyRequest.processing) return;

    try {
        stageSafetyError.value = null;
        stageSafety.value = await stageSafetyRequest.get(route('stage-safety.sensor-health.index', { organization: props.organization.slug }));
    } catch {
        stageSafetyError.value = 'Failed to load Stage Safety sensor health.';
    } finally {
        stageSafetyLoading.value = false;
    }
}

async function fetchHealth(): Promise<void> {
    await Promise.all([fetchPeoplecount(), fetchStageSafety()]);
}

useIntervalFn(fetchHealth, 10_000, { immediateCallback: true });
</script>

<template>
    <Card class="flex h-full flex-col">
        <CardHeader>
            <CardTitle>Sensor Health</CardTitle>
        </CardHeader>
        <CardContent class="flex flex-1 flex-col divide-y">
            <section v-if="showPeoplecount" class="pb-4 first:pt-0 last:pb-0">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="font-medium">Peoplecount</h3>
                    <Badge v-if="peoplecount?.all_healthy && peoplecount.total" class="bg-green-600 hover:bg-green-600">Healthy</Badge>
                    <Badge v-else-if="peoplecount?.total" variant="destructive">Attention</Badge>
                </div>
                <div
                    v-if="peoplecountError"
                    role="alert"
                    class="mb-3 rounded-md bg-red-50 p-2 text-sm text-red-600 dark:bg-red-950/30 dark:text-red-400"
                >
                    {{ peoplecountError }}
                </div>
                <Skeleton v-if="peoplecountLoading && !peoplecount" class="h-20 w-full" />
                <p v-else-if="peoplecount && !peoplecount.total" class="text-muted-foreground py-4 text-center text-sm">
                    No sensors currently assigned.
                </p>
                <div v-else-if="peoplecount?.all_healthy" class="py-4 text-center text-sm text-green-700 dark:text-green-400">
                    All {{ peoplecount.total }} sensors are healthy
                </div>
                <ul v-else-if="peoplecount" class="divide-y text-sm">
                    <li
                        v-for="sensor in peoplecount.suspicious"
                        :key="`suspicious-${sensor.id}`"
                        class="flex items-center justify-between gap-2 py-2"
                    >
                        <span class="min-w-0 truncate">{{ peoplecountSensorName(sensor) }} · {{ sensor.serial }}</span>
                        <Badge variant="secondary">Suspicious</Badge>
                    </li>
                    <li v-for="sensor in peoplecount.unhealthy" :key="`unhealthy-${sensor.id}`" class="flex items-center justify-between gap-2 py-2">
                        <span class="min-w-0 truncate">{{ peoplecountSensorName(sensor) }} · {{ sensor.serial }}</span>
                        <Badge variant="destructive">Unhealthy</Badge>
                    </li>
                </ul>
                <p v-if="peoplecount" class="text-muted-foreground mt-2 text-right text-xs">
                    Updated {{ getRelativeTime(new Date(peoplecount.last_updated)) }}
                </p>
            </section>

            <section v-if="showStageSafety" class="pt-4 first:pt-0">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="font-medium">Stage Safety</h3>
                    <Badge v-if="stageSafety?.all_fresh" class="bg-green-600 hover:bg-green-600">Fresh</Badge>
                    <Badge v-else-if="stageSafety?.total" variant="destructive">Attention</Badge>
                </div>
                <div
                    v-if="stageSafetyError"
                    role="alert"
                    class="mb-3 rounded-md bg-red-50 p-2 text-sm text-red-600 dark:bg-red-950/30 dark:text-red-400"
                >
                    {{ stageSafetyError }}
                </div>
                <Skeleton v-if="stageSafetyLoading && !stageSafety" class="h-20 w-full" />
                <p v-else-if="stageSafety && !stageSafety.total" class="text-muted-foreground py-4 text-center text-sm">
                    No active Stage Safety sensors configured.
                </p>
                <div v-else-if="stageSafety?.all_fresh" class="py-4 text-center text-sm text-green-700 dark:text-green-400">
                    All {{ stageSafety.total }} sensors report fresh data
                </div>
                <ul v-else-if="stageSafety" class="divide-y text-sm">
                    <li v-for="sensor in stageSafetyIssues" :key="sensor.id" class="flex items-center justify-between gap-2 py-2">
                        <div class="min-w-0">
                            <p class="truncate">{{ stageSafetySensorName(sensor) }}</p>
                            <p class="text-muted-foreground text-xs">
                                {{
                                    sensor.latest_observed_at ? `Observed ${getRelativeTime(new Date(sensor.latest_observed_at))}` : 'Never observed'
                                }}
                            </p>
                        </div>
                        <Badge :variant="sensor.status === 'stale' ? 'destructive' : 'secondary'">{{ sensor.status.replace('_', ' ') }}</Badge>
                    </li>
                </ul>
            </section>
        </CardContent>
    </Card>
</template>
