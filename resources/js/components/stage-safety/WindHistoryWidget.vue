<script setup lang="ts">
import { ChartContainer, ChartCrosshair, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import WidgetChartLegend from '@/components/widgets/WidgetChartLegend.vue';
import WidgetShell from '@/components/widgets/WidgetShell.vue';
import WidgetTimeRangeSelect from '@/components/widgets/WidgetTimeRangeSelect.vue';
import { WIDGET_CHART_COLORS, WIDGET_TIME_RANGE_MINUTES, type WidgetChartSeries, type WidgetTimeRange } from '@/components/widgets/widgetChart';
import { useChartSeriesVisibility } from '@/composables/useChartSeriesVisibility';
import { useWidgetPolling } from '@/composables/useWidgetPolling';
import type { Organization, StageSafetyReadingKind, StageSafetyWindHistoryPayload } from '@/types';
import { metersPerSecondToKilometersPerHour, stageSafetySensorName } from '@/utils/stageSafety';
import { CurveType, type Crosshair } from '@unovis/ts';
import { VisAxis, VisLine, VisXYContainer } from '@unovis/vue';
import { useHttp } from '@inertiajs/vue3';
import { Wind } from 'lucide-vue-next';
import { computed, h, nextTick, ref, render, watch } from 'vue';

interface ChartDataPoint {
    date: Date;
    [key: string]: Date | number | undefined;
}

const props = defineProps<{ organization: Organization }>();
const timeRange = ref<WidgetTimeRange>('1h');
const request = useHttp<{ from: string; to: string }, StageSafetyWindHistoryPayload>({ from: '', to: '' });
const { data, loading, error, refresh } = useWidgetPolling({
    interval: 60_000,
    load: () => {
        Object.assign(request, timeParams());
        return request.get(route('stage-safety.wind-history.index', { organization: props.organization.slug }));
    },
    errorMessage: 'Failed to load wind history.',
});

function timeParams(): { from: string; to: string } {
    const to = new Date();
    const from = new Date(to.getTime() - WIDGET_TIME_RANGE_MINUTES[timeRange.value] * 60 * 1000);
    return { from: from.toISOString(), to: to.toISOString() };
}

function seriesKey(sensorId: number, kind: StageSafetyReadingKind): string {
    return `sensor_${sensorId}_${kind}`;
}

const chartSeries = computed<WidgetChartSeries[]>(() =>
    (data.value?.sensors ?? []).flatMap((item, index) => {
        const color = WIDGET_CHART_COLORS[index % WIDGET_CHART_COLORS.length];
        const series: WidgetChartSeries[] = [];
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
const { hiddenSeriesKeys, isSeriesVisible, selectSeries } = useChartSeriesVisibility(() => chartSeries.value.map((item) => item.key));
const visibleChartSeries = computed(() => chartSeries.value.filter((item) => isSeriesVisible(item.key)));
const chartConfig = computed<ChartConfig>(() =>
    Object.fromEntries(visibleChartSeries.value.map((item) => [item.key, { label: item.label, color: item.color }])),
);
const chartData = computed<ChartDataPoint[]>(() => {
    const buckets = new Map<number, ChartDataPoint>();

    for (const item of data.value?.sensors ?? []) {
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
const latestDataAt = computed(() => chartData.value.at(-1)?.date ?? null);
const chartDataBySeries = computed<Record<string, ChartDataPoint[]>>(() =>
    Object.fromEntries(chartSeries.value.map((item) => [item.key, chartData.value.filter((point) => seriesValue(point, item.key) !== undefined)])),
);
const seriesColors = computed(() => visibleChartSeries.value.map((item) => item.color));
const crosshairRef = ref<{ component: Crosshair<ChartDataPoint> } | null>(null);
const windValueFormatter = new Intl.NumberFormat('de-CH', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

function crosshairTemplate(datum: ChartDataPoint | { data: ChartDataPoint }, x: number | Date): string {
    const container = document.createElement('div');
    const dataPoint = 'data' in datum ? (datum as { data: ChartDataPoint }).data : datum;
    const payload = Object.fromEntries(
        Object.entries(dataPoint).map(([key, value]) => [key, typeof value === 'number' ? windValueFormatter.format(value) : value]),
    );
    const vnode = h(ChartTooltipContent, {
        payload,
        config: chartConfig.value,
        x,
        indicator: 'line',
        labelFormatter: (value: number | Date) =>
            new Date(typeof value === 'number' ? value : value.getTime()).toLocaleString([], {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }),
    });

    render(vnode, container);
    const html = container.innerHTML;
    render(null, container);

    return html;
}

async function syncCrosshair(): Promise<void> {
    // Unovis creates the exposed Crosshair one tick after its Vue wrapper mounts.
    await nextTick();
    await nextTick();
    crosshairRef.value?.component.setData(chartData.value);
}

function seriesValue(point: ChartDataPoint, key: string): number | undefined {
    const value = point[key];
    return typeof value === 'number' ? value : undefined;
}

function formatTickDate(value: number): string {
    return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatWindTick(value: number): string {
    return value.toLocaleString([], { maximumFractionDigits: 1 });
}

watch(timeRange, refresh);
watch(chartData, () => void syncCrosshair());
</script>

<template>
    <WidgetShell title="Wind History" subtitle="Average and gust speed in km/h" :error="error" :last-updated="latestDataAt" span="full">
        <template #icon><Wind /></template>
        <template #actions><WidgetTimeRangeSelect v-model="timeRange" /></template>

        <div v-if="loading && !hasData" class="flex h-[280px] items-center justify-center sm:h-[350px]">
            <Skeleton class="h-full w-full" />
        </div>

        <div v-else-if="data && !hasData" class="text-muted-foreground flex h-[280px] items-center justify-center text-center sm:h-[350px]">
            No wind readings for selected time range.
        </div>

        <div v-else-if="hasData" class="flex flex-1 flex-col">
            <p class="sr-only" aria-live="polite">
                Wind history chart with {{ chartSeries.length }} series across {{ data?.sensors.length ?? 0 }} sensors.
            </p>
            <ChartContainer
                :config="chartConfig"
                class="h-[280px] w-full min-w-0 sm:h-[350px]"
                role="img"
                aria-label="Wind history in kilometers per hour"
            >
                <VisXYContainer :margin="{ top: 8, right: 8, bottom: 24, left: 38 }">
                    <template v-for="item in chartSeries" :key="item.key">
                        <VisLine
                            v-if="isSeriesVisible(item.key)"
                            :data="chartDataBySeries[item.key]"
                            :x="(point: ChartDataPoint) => point.date.getTime()"
                            :y="(point: ChartDataPoint) => seriesValue(point, item.key)"
                            :color="item.color"
                            :curve-type="CurveType.MonotoneX"
                            :line-width="2"
                            :line-dash-array="item.dash"
                        />
                    </template>
                    <VisAxis type="x" :tick-line="false" :domain-line="false" :grid-line="false" :tick-format="formatTickDate" />
                    <VisAxis type="y" :tick-line="false" :domain-line="false" :grid-line="true" :tick-format="formatWindTick" />
                    <ChartTooltip />
                    <ChartCrosshair ref="crosshairRef" :template="crosshairTemplate" :color="seriesColors" />
                </VisXYContainer>
            </ChartContainer>
            <WidgetChartLegend :series="chartSeries" :hidden-series-keys="hiddenSeriesKeys" @select="selectSeries" />
        </div>
    </WidgetShell>
</template>
