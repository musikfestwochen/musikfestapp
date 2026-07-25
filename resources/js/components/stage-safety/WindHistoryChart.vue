<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartCrosshair, ChartTooltip, ChartTooltipContent, componentToString, type ChartConfig } from '@/components/ui/chart';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import type { StageSafetyReadingKind, StageSafetyWindHistoryPayload } from '@/types';
import { metersPerSecondToKilometersPerHour, stageSafetySensorName } from '@/utils/stageSafety';
import { CurveType } from '@unovis/ts';
import { VisAxis, VisLine, VisXYContainer } from '@unovis/vue';
import { Wind } from 'lucide-vue-next';
import { computed } from 'vue';

interface ChartDataPoint {
    date: Date;
    [key: string]: Date | number | undefined;
}

interface ChartSeries {
    key: string;
    label: string;
    color: string;
    dash?: number[];
}

const props = defineProps<{
    data: StageSafetyWindHistoryPayload | null;
    loading: boolean;
    error: string | null;
    timeRange: string;
}>();

const emit = defineEmits<{
    'update:timeRange': [value: string];
}>();

const SENSOR_COLORS = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];
const timeRangeOptions = [
    { value: '1h', label: 'Last hour' },
    { value: '3h', label: 'Last 3 hours' },
    { value: '6h', label: 'Last 6 hours' },
    { value: '12h', label: 'Last 12 hours' },
    { value: '24h', label: 'Last 24 hours' },
];

const selectedRange = computed({
    get: () => props.timeRange,
    set: (value: string) => emit('update:timeRange', value),
});

function seriesKey(sensorId: number, kind: StageSafetyReadingKind): string {
    return `sensor_${sensorId}_${kind}`;
}

const chartSeries = computed<ChartSeries[]>(() =>
    (props.data?.sensors ?? []).flatMap((item, index) => {
        const color = SENSOR_COLORS[index % SENSOR_COLORS.length];
        const series: ChartSeries[] = [];
        const hasReadings = (kind: StageSafetyReadingKind): boolean => item.readings.some((reading) => reading.kind === kind);

        if (hasReadings('wind_average')) {
            series.push({
                key: seriesKey(item.sensor.id, 'wind_average'),
                label: `${stageSafetySensorName(item.sensor)} average`,
                color,
            });
        }
        if (hasReadings('wind_gust')) {
            series.push({
                key: seriesKey(item.sensor.id, 'wind_gust'),
                label: `${stageSafetySensorName(item.sensor)} gust`,
                color,
                dash: [6, 3],
            });
        }

        return series;
    }),
);

const chartConfig = computed<ChartConfig>(() =>
    Object.fromEntries(chartSeries.value.map((series) => [series.key, { label: series.label, color: series.color }])),
);

const chartData = computed<ChartDataPoint[]>(() => {
    const buckets = new Map<number, ChartDataPoint>();

    for (const item of props.data?.sensors ?? []) {
        for (const reading of item.readings) {
            const timestamp = new Date(reading.observed_at).getTime();
            const row = buckets.get(timestamp) ?? { date: new Date(timestamp) };
            row[seriesKey(item.sensor.id, reading.kind)] = metersPerSecondToKilometersPerHour(reading.value);
            buckets.set(timestamp, row);
        }
    }

    return Array.from(buckets.values()).sort((left, right) => left.date.getTime() - right.date.getTime());
});

const hasData = computed(() => chartData.value.length > 0);
const seriesColors = computed(() => chartSeries.value.map((series) => series.color));

function formatTickDate(value: number): string {
    return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatWindTick(value: number): string {
    return value.toLocaleString([], { maximumFractionDigits: 1 });
}

function seriesValue(point: ChartDataPoint, key: string): number | undefined {
    const value = point[key];

    return typeof value === 'number' ? value : undefined;
}
</script>

<template>
    <Card class="col-span-full flex min-w-0 flex-col">
        <CardHeader class="flex flex-col gap-3 pb-4 sm:flex-row sm:items-center sm:justify-between sm:space-y-0">
            <div>
                <CardTitle class="flex items-center gap-2"><Wind class="size-4" aria-hidden="true" /> Wind History</CardTitle>
                <p class="text-muted-foreground mt-1 text-sm">Average and gust speed in km/h</p>
            </div>
            <Select v-model="selectedRange">
                <SelectTrigger class="w-full sm:w-[160px]">
                    <SelectValue placeholder="Select range" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="option in timeRangeOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </CardHeader>
        <CardContent class="flex min-w-0 flex-1 flex-col">
            <div
                v-if="error"
                role="alert"
                class="mb-4 rounded-md bg-red-50 p-2 text-center text-sm text-red-600 dark:bg-red-950/30 dark:text-red-400"
            >
                {{ error }}
            </div>

            <div v-if="loading && !hasData" class="flex h-[280px] items-center justify-center sm:h-[350px]">
                <Skeleton class="h-full w-full" />
            </div>

            <div v-else-if="!hasData" class="text-muted-foreground flex h-[280px] items-center justify-center text-center sm:h-[350px]">
                No wind readings for selected time range.
            </div>

            <div v-else class="flex flex-1 flex-col">
                <p class="sr-only" aria-live="polite">
                    Wind history chart with {{ chartSeries.length }} series across {{ data?.sensors.length ?? 0 }} sensors.
                </p>
                <ChartContainer
                    :config="chartConfig"
                    class="h-[280px] w-full min-w-0 sm:h-[350px]"
                    role="img"
                    aria-label="Wind history in kilometers per hour"
                >
                    <VisXYContainer :data="chartData" :margin="{ top: 8, right: 8, bottom: 24, left: 38 }">
                        <VisLine
                            v-for="series in chartSeries"
                            :key="series.key"
                            :x="(point: ChartDataPoint) => point.date.getTime()"
                            :y="(point: ChartDataPoint) => seriesValue(point, series.key)"
                            :color="series.color"
                            :curve-type="CurveType.MonotoneX"
                            :interpolate-missing-data="true"
                            :line-width="2"
                            :line-dash-array="series.dash"
                        />
                        <VisAxis type="x" :tick-line="false" :domain-line="false" :grid-line="false" :tick-format="formatTickDate" />
                        <VisAxis type="y" :tick-line="false" :domain-line="false" :grid-line="true" :tick-format="formatWindTick" />
                        <ChartTooltip />
                        <ChartCrosshair
                            :template="
                                componentToString(chartConfig, ChartTooltipContent, {
                                    indicator: 'line',
                                    labelFormatter: (value: number | Date) =>
                                        new Date(typeof value === 'number' ? value : value.getTime()).toLocaleString([], {
                                            month: 'short',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        }),
                                })
                            "
                            :color="seriesColors"
                        />
                    </VisXYContainer>
                </ChartContainer>

                <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 border-t pt-3 text-sm">
                    <div v-for="series in chartSeries" :key="`legend-${series.key}`" class="flex items-center gap-2">
                        <svg width="24" height="8" :style="{ color: series.color }" aria-hidden="true">
                            <line
                                x1="0"
                                y1="4"
                                x2="24"
                                y2="4"
                                stroke="currentColor"
                                stroke-width="2"
                                :stroke-dasharray="series.dash?.join(',') ?? 'none'"
                            />
                        </svg>
                        <span>{{ series.label }}</span>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
