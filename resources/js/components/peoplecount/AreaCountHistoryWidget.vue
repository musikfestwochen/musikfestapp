<script lang="ts" setup>
import { ChartContainer, ChartCrosshair, ChartTooltip, ChartTooltipContent, componentToString, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import WidgetChartLegend from '@/components/widgets/WidgetChartLegend.vue';
import WidgetHistoryControls from '@/components/widgets/WidgetHistoryControls.vue';
import WidgetShell from '@/components/widgets/WidgetShell.vue';
import {
    calculateWidgetChartStatistics,
    WIDGET_CHART_COLORS,
    widgetChartStatisticMarkers,
    widgetTimeRangeParams,
    widgetTimeRangeShowsDate,
    type WidgetChartSeries,
    type WidgetChartStatisticMarker,
    type WidgetChartStatistics,
    type WidgetTimeRange,
} from '@/components/widgets/widgetChart';
import { useChartSeriesVisibility } from '@/composables/useChartSeriesVisibility';
import { useWidgetPolling } from '@/composables/useWidgetPolling';
import { DATE_TIME_LOCALE, formatChartTick, formatChartTooltip } from '@/utils/dateTimeHelpers';
import { CurveType, PlotlineLabelPosition, PlotlineLineStylePresets, type Crosshair } from '@unovis/ts';
import { VisAxis, VisLine, VisPlotline, VisScatter, VisXYContainer } from '@unovis/vue';
import { useHttp } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

interface DataPoint {
    time: string;
    count: number;
}

interface AreaSeries {
    id: number;
    name: string;
    event_name: string;
    data: DataPoint[];
}

interface ChartDataPoint {
    date: Date;
    [key: `area_${number}`]: number | null | undefined;
}

const props = defineProps<{
    organization: { id: number; slug: string; name: string };
}>();

const timeRange = ref<WidgetTimeRange>('1h');
const statisticsEnabled = ref(false);
const request = useHttp<{ from: string; to: string }, AreaSeries[]>({ from: '', to: '' });
const {
    data: series,
    loading,
    error,
    refresh,
} = useWidgetPolling({
    interval: 60_000,
    load: () => {
        Object.assign(request, timeParams());
        return request.get(route('peoplecount.area-count-history.index', { organization: props.organization.slug }));
    },
    errorMessage: 'Failed to load area count history',
});

function timeParams(): { from: string; to: string } {
    return widgetTimeRangeParams(timeRange.value);
}

function shortenName(name: string, maxLength = 18): string {
    return name.length <= maxLength ? name : `${name.slice(0, maxLength - 1).trimEnd()}…`;
}

const chartSeries = computed<WidgetChartSeries[]>(() =>
    (series.value ?? []).map((area, index) => ({
        key: `area_${area.id}`,
        label: shortenName(area.name),
        color: WIDGET_CHART_COLORS[index % WIDGET_CHART_COLORS.length],
    })),
);
const { hiddenSeriesKeys, isSeriesVisible, selectSeries } = useChartSeriesVisibility(() => chartSeries.value.map((item) => item.key));
const visibleChartSeries = computed(() => chartSeries.value.filter((item) => isSeriesVisible(item.key)));
const chartConfig = computed<ChartConfig>(() =>
    Object.fromEntries(visibleChartSeries.value.map((item) => [item.key, { label: item.label, color: item.color }])),
);
const chartData = computed<ChartDataPoint[]>(() => {
    const buckets = new Map<number, ChartDataPoint>();

    for (const area of series.value ?? []) {
        for (const point of area.data) {
            const timestamp = new Date(point.time).getTime();
            const row = buckets.get(timestamp) ?? { date: new Date(timestamp) };
            row[`area_${area.id}`] = point.count;
            buckets.set(timestamp, row);
        }
    }

    return Array.from(buckets.values()).sort((left, right) => left.date.getTime() - right.date.getTime());
});
const statistics = computed<Record<string, WidgetChartStatistics | null>>(() =>
    Object.fromEntries(
        (series.value ?? []).map((area) => [
            `area_${area.id}`,
            calculateWidgetChartStatistics(area.data.map((point) => ({ date: new Date(point.time), value: point.count }))),
        ]),
    ),
);
const hasData = computed(() => chartData.value.length > 0);
const latestDataAt = computed(() => chartData.value.at(-1)?.date ?? null);
const seriesColors = computed(() => visibleChartSeries.value.map((item) => item.color));
const crosshairRef = ref<{ component: Crosshair<ChartDataPoint> } | null>(null);
const focusedSeries = computed(() => (statisticsEnabled.value && visibleChartSeries.value.length === 1 ? visibleChartSeries.value[0] : null));
const focusedStatistics = computed(() => (focusedSeries.value ? statistics.value[focusedSeries.value.key] : null));
const countFormatter = new Intl.NumberFormat(DATE_TIME_LOCALE, { maximumFractionDigits: 1 });
const statisticMarkers = computed(() => widgetChartStatisticMarkers(focusedStatistics.value, formatCount));

function seriesValue(point: ChartDataPoint, key: string): number | undefined {
    const value = point[key as `area_${number}`];
    return typeof value === 'number' ? value : undefined;
}

function formatTickDate(value: number): string {
    return formatChartTick(value, widgetTimeRangeShowsDate(timeRange.value));
}

function formatCount(value: number): string {
    return countFormatter.format(value);
}

async function syncCrosshair(): Promise<void> {
    await nextTick();
    await nextTick();
    crosshairRef.value?.component?.setData(chartData.value);
}

watch(timeRange, refresh);
watch(chartData, () => void syncCrosshair());
</script>

<template>
    <WidgetShell title="Area Count History" :error="error" :last-updated="latestDataAt" span="full">
        <template #icon><Users /></template>
        <template #actions>
            <WidgetHistoryControls v-model:time-range="timeRange" v-model:statistics-enabled="statisticsEnabled" />
        </template>

        <div v-if="loading && !hasData" class="flex h-[280px] items-center justify-center sm:h-[350px]">
            <Skeleton class="h-full w-full" />
        </div>

        <div v-else-if="series && !hasData" class="text-muted-foreground flex h-[280px] items-center justify-center text-center sm:h-[350px]">
            No data available for the selected time range.
        </div>

        <div v-else-if="hasData" class="flex flex-1 flex-col">
            <p class="sr-only" aria-live="polite">
                Area count history chart with {{ chartSeries.length }} series across {{ series?.length ?? 0 }} areas.
            </p>
            <ChartContainer
                class="widget-history-chart"
                :config="chartConfig"
                :class="[statisticsEnabled ? 'h-[220px]' : 'h-[240px]', 'w-full min-w-0 sm:h-[350px]']"
                role="img"
                aria-label="Area count history"
            >
                <VisXYContainer>
                    <template v-for="item in chartSeries" :key="item.key">
                        <VisLine
                            v-if="isSeriesVisible(item.key)"
                            :data="chartData"
                            :x="(point: ChartDataPoint) => point.date.getTime()"
                            :y="(point: ChartDataPoint) => seriesValue(point, item.key)"
                            :color="item.color"
                            :curve-type="CurveType.MonotoneX"
                            :line-width="2"
                        />
                    </template>
                    <VisPlotline
                        v-if="focusedSeries && focusedStatistics"
                        axis="y"
                        :value="focusedStatistics.average"
                        :color="focusedSeries.color"
                        :line-width="1"
                        :line-style="PlotlineLineStylePresets.ShortDash"
                        :label-text="`Avg ${formatCount(focusedStatistics.average)}`"
                        :label-position="PlotlineLabelPosition.TopRight"
                        :exclude-from-domain-calculation="true"
                    />
                    <VisScatter
                        v-if="focusedSeries && statisticMarkers.length"
                        :data="statisticMarkers"
                        :x="(point: WidgetChartStatisticMarker) => point.date.getTime()"
                        :y="(point: WidgetChartStatisticMarker) => point.value"
                        color="hsl(var(--foreground))"
                        :label="(point: WidgetChartStatisticMarker) => point.label"
                        label-color="hsl(var(--foreground))"
                        :label-position="(point: WidgetChartStatisticMarker) => point.position"
                        :label-hide-overlapping="false"
                        :size="8"
                        stroke-color="hsl(var(--background))"
                        :stroke-width="2"
                        :exclude-from-domain-calculation="true"
                    />
                    <VisAxis
                        type="x"
                        :tick-line="false"
                        :domain-line="false"
                        :grid-line="false"
                        :num-ticks="5"
                        :min-max-ticks-only-when-width-is-less="500"
                        :tick-text-hide-overlapping="true"
                        :tick-format="formatTickDate"
                    />
                    <VisAxis
                        type="y"
                        :tick-line="false"
                        :domain-line="false"
                        :grid-line="true"
                        :num-ticks="4"
                        :tick-format="(value: number) => Math.round(value).toString()"
                    />
                    <ChartTooltip />
                    <ChartCrosshair
                        ref="crosshairRef"
                        :template="
                            componentToString(chartConfig, ChartTooltipContent, {
                                indicator: 'line',
                                labelFormatter: (value: number | Date) => formatChartTooltip(value),
                            })
                        "
                        :color="seriesColors"
                    />
                </VisXYContainer>
            </ChartContainer>
            <WidgetChartLegend
                :series="chartSeries"
                :hidden-series-keys="hiddenSeriesKeys"
                :statistics-enabled="statisticsEnabled"
                :statistics="statisticsEnabled ? statistics : undefined"
                :format-value="formatCount"
                @select="selectSeries"
            />
        </div>
    </WidgetShell>
</template>
