<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { StageSafetyCurrentReading, StageSafetyCurrentSensor } from '@/types';
import { formatWindSpeed, stageSafetySensorName } from '@/utils/stageSafety';

defineProps<{
    sensors: StageSafetyCurrentSensor[];
}>();

function readingLabel(label: string, reading: StageSafetyCurrentReading | null, sensorStatus: StageSafetyCurrentSensor['status']): string {
    return !reading || (sensorStatus === 'fresh' && reading.status === 'fresh') ? label : `Last known ${label.toLowerCase()}`;
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
        <article v-for="item in sensors" :key="item.sensor.id" class="flex flex-col items-center py-4 text-center first:pt-0 last:pb-0">
            <div class="flex max-w-full flex-col items-center gap-2">
                <h3 class="max-w-full text-lg font-medium break-words">{{ stageSafetySensorName(item.sensor) }}</h3>
                <Badge v-if="item.status !== 'fresh'" :variant="statusVariant(item.status)">{{ item.status.replace('_', ' ') }}</Badge>
            </div>

            <dl class="mt-4 flex flex-col items-center gap-3">
                <div v-if="item.wind_gust">
                    <dt :class="readingLabel('Gust', item.wind_gust, item.status) === 'Gust' ? 'sr-only' : 'text-muted-foreground mb-1 text-xs'">
                        {{ readingLabel('Gust', item.wind_gust, item.status) }}
                    </dt>
                    <dd class="text-foreground text-5xl leading-none font-semibold tracking-tight tabular-nums">
                        {{ formatWindSpeed(item.wind_gust.value) }}
                        <span class="text-muted-foreground text-sm font-medium">km/h</span>
                    </dd>
                </div>

                <div v-else-if="item.wind_average">
                    <dt
                        :class="
                            readingLabel('Average', item.wind_average, item.status) === 'Average' ? 'sr-only' : 'text-muted-foreground mb-1 text-xs'
                        "
                    >
                        {{ readingLabel('Average', item.wind_average, item.status) }}
                    </dt>
                    <dd class="text-foreground text-5xl leading-none font-semibold tracking-tight tabular-nums">
                        {{ formatWindSpeed(item.wind_average.value) }}
                        <span class="text-muted-foreground text-sm font-medium">km/h</span>
                    </dd>
                </div>

                <div
                    v-if="item.wind_gust && item.wind_average"
                    class="bg-muted/40 inline-flex items-baseline gap-1.5 rounded-full border px-2.5 py-1"
                >
                    <dt class="text-muted-foreground text-xs">{{ readingLabel('Average', item.wind_average, item.status) }}</dt>
                    <dd class="text-sm font-semibold tracking-tight tabular-nums">
                        {{ formatWindSpeed(item.wind_average.value) }}
                        <span class="text-xs font-medium">km/h</span>
                    </dd>
                </div>
            </dl>
        </article>
    </div>
</template>
