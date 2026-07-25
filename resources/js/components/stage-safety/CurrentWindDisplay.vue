<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { StageSafetyCurrentReading, StageSafetyCurrentSensor } from '@/types';
import { getRelativeTime } from '@/utils/dateTimeHelpers';
import { formatWindSpeed } from '@/utils/stageSafety';
import { Wind } from 'lucide-vue-next';

defineProps<{
    sensors: StageSafetyCurrentSensor[];
}>();

function sensorName(item: StageSafetyCurrentSensor): string {
    return item.sensor.name || item.sensor.identifier;
}

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
    <div class="grid gap-4" :class="sensors.length > 1 ? 'xl:grid-cols-2' : ''">
        <article v-for="item in sensors" :key="item.sensor.id" class="bg-muted/25 rounded-xl border p-4 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="truncate font-semibold">{{ sensorName(item) }}</h3>
                        <Badge :variant="statusVariant(item.status)">{{ item.status.replace('_', ' ') }}</Badge>
                    </div>
                    <p class="text-muted-foreground mt-1 truncate text-sm">
                        {{ item.sensor.location || item.sensor.identifier }}
                    </p>
                </div>
                <Wind class="text-primary size-6 shrink-0" aria-hidden="true" />
            </div>

            <div class="mt-6 grid grid-cols-2 divide-x">
                <div class="pr-4 text-center">
                    <p class="text-primary text-4xl font-semibold tracking-tight sm:text-5xl">
                        {{ item.wind_average ? formatWindSpeed(item.wind_average.value) : '—' }}
                        <span v-if="item.wind_average" class="text-lg font-medium">km/h</span>
                    </p>
                    <p class="text-muted-foreground mt-2 text-sm">{{ readingLabel('Average', item.wind_average, item.status) }}</p>
                </div>
                <div class="pl-4 text-center">
                    <p class="text-3xl font-semibold tracking-tight sm:text-4xl">
                        {{ item.wind_gust ? formatWindSpeed(item.wind_gust.value) : '—' }}
                        <span v-if="item.wind_gust" class="text-base font-medium">km/h</span>
                    </p>
                    <p class="text-muted-foreground mt-2 text-sm">{{ readingLabel('Gust', item.wind_gust, item.status) }}</p>
                    <p v-if="item.wind_gust?.window_seconds" class="text-muted-foreground mt-1 text-xs">
                        {{ item.wind_gust.window_seconds }} second period
                    </p>
                </div>
            </div>

            <p class="text-muted-foreground mt-5 text-right text-xs">
                {{ item.latest_observed_at ? `Observed ${getRelativeTime(new Date(item.latest_observed_at))}` : 'Never observed' }}
            </p>
        </article>
    </div>
</template>
