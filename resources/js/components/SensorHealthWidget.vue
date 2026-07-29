<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import WidgetNotice from '@/components/widgets/WidgetNotice.vue';
import WidgetShell from '@/components/widgets/WidgetShell.vue';
import { useWidgetPolling } from '@/composables/useWidgetPolling';
import type { Organization, StageSafetySensorHealthPayload } from '@/types';
import { getRelativeTime } from '@/utils/dateTimeHelpers';
import { stageSafetySensorName } from '@/utils/stageSafety';
import { useHttp } from '@inertiajs/vue3';
import { Activity } from 'lucide-vue-next';
import { computed } from 'vue';

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
const {
    data: peoplecount,
    loading: peoplecountLoading,
    error: peoplecountError,
} = useWidgetPolling({
    interval: 20_000,
    load: () => peoplecountRequest.get(route('peoplecount.sensor-health.index', { organization: props.organization.slug })),
    errorMessage: 'Failed to load Peoplecount sensor health.',
    enabled: props.showPeoplecount,
});
const {
    data: stageSafety,
    loading: stageSafetyLoading,
    error: stageSafetyError,
} = useWidgetPolling({
    interval: 20_000,
    load: () => stageSafetyRequest.get(route('stage-safety.sensor-health.index', { organization: props.organization.slug })),
    errorMessage: 'Failed to load Stage Safety sensor health.',
    enabled: props.showStageSafety,
});

const stageSafetyIssues = computed(() => [...(stageSafety.value?.stale ?? []), ...(stageSafety.value?.never_seen ?? [])]);
const latestDataAt = computed(() => {
    const peoplecountSensors = peoplecount.value
        ? [...peoplecount.value.healthy, ...peoplecount.value.suspicious, ...peoplecount.value.unhealthy]
        : [];
    const stageSafetySensors = stageSafety.value ? [...stageSafety.value.fresh, ...stageSafety.value.stale, ...stageSafety.value.never_seen] : [];
    const timestamps = [
        ...peoplecountSensors.flatMap((sensor) => (sensor.latest_ts ? [new Date(sensor.latest_ts).getTime()] : [])),
        ...stageSafetySensors.flatMap((sensor) => (sensor.latest_observed_at ? [new Date(sensor.latest_observed_at).getTime()] : [])),
    ];

    return timestamps.length ? new Date(Math.max(...timestamps)) : null;
});

function peoplecountSensorName(sensor: PeoplecountHealthSensor): string {
    return sensor.name || `${sensor.vendor} ${sensor.model}`;
}
</script>

<template>
    <WidgetShell title="Sensor Health" :last-updated="latestDataAt">
        <template #icon><Activity /></template>

        <div class="flex flex-1 flex-col divide-y">
            <section v-if="showPeoplecount" class="pb-4 first:pt-0 last:pb-0">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="font-medium">Peoplecount</h3>
                    <Badge v-if="peoplecount?.all_healthy && peoplecount.total" variant="success">Healthy</Badge>
                    <Badge v-else-if="peoplecount?.total" variant="destructive">Attention</Badge>
                </div>
                <WidgetNotice v-if="peoplecountError" class="mb-3" variant="error">{{ peoplecountError }}</WidgetNotice>
                <Skeleton v-if="peoplecountLoading && !peoplecount" class="h-20 w-full" />
                <p v-else-if="peoplecount && !peoplecount.total" class="text-muted-foreground py-4 text-center text-sm">
                    No sensors currently assigned.
                </p>
                <div v-else-if="peoplecount?.all_healthy" class="py-4 text-center text-sm text-emerald-700 dark:text-emerald-300/80">
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
            </section>

            <section v-if="showStageSafety" class="pt-4 first:pt-0">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="font-medium">Stage Safety</h3>
                    <Badge v-if="stageSafety?.all_fresh" variant="success">Fresh</Badge>
                    <Badge v-else-if="stageSafety?.total" variant="destructive">Attention</Badge>
                </div>
                <WidgetNotice v-if="stageSafetyError" class="mb-3" variant="error">{{ stageSafetyError }}</WidgetNotice>
                <Skeleton v-if="stageSafetyLoading && !stageSafety" class="h-20 w-full" />
                <p v-else-if="stageSafety && !stageSafety.total" class="text-muted-foreground py-4 text-center text-sm">
                    No active Stage Safety sensors configured.
                </p>
                <div v-else-if="stageSafety?.all_fresh" class="py-4 text-center text-sm text-emerald-700 dark:text-emerald-300/80">
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
        </div>
    </WidgetShell>
</template>
