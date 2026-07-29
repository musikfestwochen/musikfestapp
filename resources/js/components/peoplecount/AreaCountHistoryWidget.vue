<script lang="ts" setup>
import { ChartContainer, ChartCrosshair, ChartTooltip, ChartTooltipContent, componentToString, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import WidgetChartLegend from '@/components/widgets/WidgetChartLegend.vue';
import WidgetShell from '@/components/widgets/WidgetShell.vue';
import WidgetTimeRangeSelect from '@/components/widgets/WidgetTimeRangeSelect.vue';
import { WIDGET_CHART_COLORS, WIDGET_TIME_RANGE_MINUTES, type WidgetChartSeries, type WidgetTimeRange } from '@/components/widgets/widgetChart';
import { useChartSeriesVisibility } from '@/composables/useChartSeriesVisibility';
import { useWidgetPolling } from '@/composables/useWidgetPolling';
import { APP_LOCALE } from '@/utils/dateTimeHelpers';
import { CurveType } from '@unovis/ts';
import { VisAxis, VisLine, VisXYContainer } from '@unovis/vue';
import { useHttp } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    const to = new Date();
    const from = new Date(to.getTime() - WIDGET_TIME_RANGE_MINUTES[timeRange.value] * 60 * 1000);
    return { from: from.toISOString(), to: to.toISOString() };
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
const hasData = computed(() => chartData.value.length > 0);
const latestDataAt = computed(() => chartData.value.at(-1)?.date ?? null);
const seriesColors = computed(() => visibleChartSeries.value.map((item) => item.color));

function seriesValue(point: ChartDataPoint, key: string): number | undefined {
    const value = point[key as `area_${number}`];
    return typeof value === 'number' ? value : undefined;
}

function formatTickDate(value: number): string {
    return new Date(value).toLocaleTimeString(APP_LOCALE, { hour: '2-digit', minute: '2-digit' });
}

watch(timeRange, refresh);
</script>

<template>
    <WidgetShell title="Area Count History" :error="error" :last-updated="latestDataAt" span="full">
        <template #icon><Users /></template>
        <template #actions><WidgetTimeRangeSelect v-model="timeRange" /></template>

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
            <ChartContainer :config="chartConfig" class="h-[280px] w-full min-w-0 sm:h-[350px]" role="img" aria-label="Area count history">
                <VisXYContainer :data="chartData" :margin="{ top: 8, right: 8, bottom: 24, left: 32 }">
                    <template v-for="item in chartSeries" :key="item.key">
                        <VisLine
                            v-if="isSeriesVisible(item.key)"
                            :x="(point: ChartDataPoint) => point.date.getTime()"
                            :y="(point: ChartDataPoint) => seriesValue(point, item.key)"
                            :color="item.color"
                            :curve-type="CurveType.MonotoneX"
                            :line-width="2"
                        />
                    </template>
                    <VisAxis type="x" :tick-line="false" :domain-line="false" :grid-line="false" :tick-format="formatTickDate" />
                    <VisAxis
                        type="y"
                        :tick-line="false"
                        :domain-line="false"
                        :grid-line="true"
                        :tick-format="(value: number) => Math.round(value).toString()"
                    />
                    <ChartTooltip />
                    <ChartCrosshair
                        :template="
                            componentToString(chartConfig, ChartTooltipContent, {
                                indicator: 'line',
                                labelFormatter: (value: number | Date) =>
                                    new Date(typeof value === 'number' ? value : value.getTime()).toLocaleString(APP_LOCALE, {
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
            <WidgetChartLegend :series="chartSeries" :hidden-series-keys="hiddenSeriesKeys" @select="selectSeries" />
        </div>
    </WidgetShell>
</template>
