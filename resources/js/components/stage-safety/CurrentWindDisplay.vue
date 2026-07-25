<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { StageSafetyCurrentReading, StageSafetyCurrentSensor } from '@/types';
import { getRelativeTime } from '@/utils/dateTimeHelpers';
import { formatWindSpeed, stageSafetySensorName } from '@/utils/stageSafety';

defineProps<{
    sensors: StageSafetyCurrentSensor[];
}>();

function readingLabel(label: string, reading: StageSafetyCurrentReading | null, sensorStatus: StageSafetyCurrentSensor['status']): string {
    return sensorStatus === 'fresh' && reading?.status === 'fresh' ? label : `Last known ${label.toLowerCase()}`;
}

function statusVariant(status: StageSafetyCurrentSensor['status']): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'fresh') return 'default';
    if (status === 'stale') return 'destructive';
    if (status === 'archived') return 'outline';
    return 'secondary';
}
</script>

<template>
    <div class="divide-y">
        <article v-for="item in sensors" :key="item.sensor.id" class="py-4 first:pt-0 last:pb-0">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="min-w-0 truncate font-semibold">{{ stageSafetySensorName(item.sensor) }}</h3>
                <Badge v-if="item.status !== 'fresh'" :variant="statusVariant(item.status)">{{ item.status.replace('_', ' ') }}</Badge>
            </div>

            <div class="mt-4 grid grid-cols-2 divide-x">
                <div class="pr-3 text-center">
                    <p class="text-primary text-3xl font-semibold tracking-tight">
                        {{ item.wind_average ? formatWindSpeed(item.wind_average.value) : '—' }}
                        <span v-if="item.wind_average" class="text-sm font-medium">km/h</span>
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">{{ readingLabel('Average', item.wind_average, item.status) }}</p>
                </div>
                <div class="pl-3 text-center">
                    <p class="text-2xl font-semibold tracking-tight">
                        {{ item.wind_gust ? formatWindSpeed(item.wind_gust.value) : '—' }}
                        <span v-if="item.wind_gust" class="text-sm font-medium">km/h</span>
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">{{ readingLabel('Gust', item.wind_gust, item.status) }}</p>
                </div>
            </div>

            <p class="text-muted-foreground mt-3 text-right text-xs">
                {{ item.latest_observed_at ? `Observed ${getRelativeTime(new Date(item.latest_observed_at))}` : 'Never observed' }}
            </p>
        </article>
    </div>
</template>
