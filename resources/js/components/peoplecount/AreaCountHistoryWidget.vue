<script lang="ts" setup>
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartCrosshair, ChartTooltip, ChartTooltipContent, componentToString, type ChartConfig } from '@/components/ui/chart';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { CurveType } from '@unovis/ts';
import { VisAxis, VisLine, VisXYContainer } from '@unovis/vue';
import { useHttp } from '@inertiajs/vue3';
import { useStorage } from '@vueuse/core';
import { ChartColumnIncreasing, ChartSpline, Users } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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

const series = ref<AreaSeries[]>([]);
const request = useHttp<{ from: string; to: string }, AreaSeries[]>({ from: '', to: '' });
const loading = ref(true);
const error = ref<string | null>(null);
const timeRange = ref('1h');
const chartStyle = useStorage<'spline' | 'step'>('peoplecount.area-count-history.chart-style', 'spline');
const hiddenAreaIds = ref<Set<number>>(new Set());
const lastUpdated = ref<Date | null>(null);
let refreshInterval: number | null = null;

function toggleChartStyle(): void {
    chartStyle.value = chartStyle.value === 'spline' ? 'step' : 'spline';
}

function toggleAreaVisibility(id: number): void {
    const next = new Set(hiddenAreaIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    hiddenAreaIds.value = next;
}

function isAreaVisible(id: number): boolean {
    return !hiddenAreaIds.value.has(id);
}

function toggleLegendArea(id: number, event: MouseEvent): void {
    if (event.shiftKey) {
        toggleAreaVisibility(id);
        return;
    }

    const visibleIds = series.value.filter((area) => isAreaVisible(area.id)).map((area) => area.id);
    hiddenAreaIds.value =
        visibleIds.length === 1 && visibleIds[0] === id ? new Set() : new Set(series.value.map((area) => area.id).filter((areaId) => areaId !== id));
}

const LINE_DASH_PATTERNS: (number[] | undefined)[] = [undefined, [6, 3], [2, 3], [10, 4, 2, 4], [10, 4]];

function lineDashForIndex(index: number): number[] | undefined {
    return LINE_DASH_PATTERNS[index % LINE_DASH_PATTERNS.length];
}

function shortenName(name: string, maxLength = 18): string {
    if (name.length <= maxLength) {
        return name;
    }
    return `${name.slice(0, maxLength - 1).trimEnd()}…`;
}

const AREA_COLORS = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

const timeRangeOptions = [
    { value: '1h', label: 'Last hour' },
    { value: '3h', label: 'Last 3 hours' },
    { value: '6h', label: 'Last 6 hours' },
    { value: '12h', label: 'Last 12 hours' },
    { value: '24h', label: 'Last 24 hours' },
];

function getTimeParams(): { from: string; to: string } {
    const now = new Date();
    const hours = parseInt(timeRange.value);
    const from = new Date(now.getTime() - hours * 60 * 60 * 1000);
    return { from: from.toISOString(), to: now.toISOString() };
}

const chartConfig = computed<ChartConfig>(() => {
    const config: ChartConfig = {
        outline: {
            theme: { light: '#0a0a0a', dark: '#fafafa' },
        },
    };
    series.value.forEach((area, index) => {
        config[`area_${area.id}`] = {
            label: shortenName(area.name),
            color: AREA_COLORS[index % AREA_COLORS.length],
        };
    });
    return config;
});

const chartData = computed<ChartDataPoint[]>(() => {
    const buckets = new Map<number, ChartDataPoint>();
    for (const area of series.value) {
        for (const point of area.data) {
            const ts = new Date(point.time).getTime();
            if (!buckets.has(ts)) {
                buckets.set(ts, { date: new Date(ts) });
            }
            const row = buckets.get(ts) as ChartDataPoint;
            row[`area_${area.id}`] = point.count;
        }
    }
    return Array.from(buckets.values()).sort((a, b) => a.date.getTime() - b.date.getTime());
});

const hasData = computed(() => chartData.value.length > 0);
const areaColors = computed(() => series.value.map((_, index) => AREA_COLORS[index % AREA_COLORS.length]));
const areaAccessors = computed(() => series.value.map((area) => (d: ChartDataPoint) => (d[`area_${area.id}`] ?? undefined) as number | undefined));
const lastUpdatedTime = computed(() => lastUpdated.value?.toLocaleTimeString() ?? 'N/A');

function formatTickDate(d: number): string {
    return new Date(d).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

async function fetchHistory() {
    try {
        const params = getTimeParams();
        Object.assign(request, params);
        const response = await request.get(route('peoplecount.area-count-history.index', { organization: props.organization.slug }));
        error.value = null;
        series.value = response;
        lastUpdated.value = new Date();
    } catch (err) {
        error.value = 'Failed to load area count history';
        console.error(err);
    } finally {
        loading.value = false;
    }
}

watch(timeRange, () => {
    fetchHistory();
});

onMounted(() => {
    fetchHistory();
    refreshInterval = window.setInterval(fetchHistory, 30000);
});

onBeforeUnmount(() => {
    if (refreshInterval !== null) {
        clearInterval(refreshInterval);
    }
});
</script>

<template>
    <Card class="col-span-full flex h-full min-w-0 flex-col">
        <CardHeader class="flex flex-col gap-3 pb-4 sm:flex-row sm:items-center sm:justify-between sm:gap-2 sm:space-y-0">
            <CardTitle class="flex items-center gap-2"><Users class="size-4" aria-hidden="true" /> Area Count History</CardTitle>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <Button
                    variant="ghost"
                    size="icon"
                    class="self-start sm:self-auto"
                    :aria-label="chartStyle === 'spline' ? 'Switch to step line chart' : 'Switch to spline chart'"
                    :title="chartStyle === 'spline' ? 'Switch to step line chart' : 'Switch to spline chart'"
                    @click="toggleChartStyle"
                >
                    <ChartSpline v-if="chartStyle === 'spline'" class="h-5 w-5" />
                    <ChartColumnIncreasing v-else class="h-5 w-5" />
                </Button>
                <Select v-model="timeRange">
                    <SelectTrigger class="w-full sm:w-[160px]">
                        <SelectValue placeholder="Select range" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="option in timeRangeOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </CardHeader>
        <CardContent class="flex min-w-0 flex-1 flex-col">
            <div v-if="error" class="mb-4 rounded bg-red-50 p-2 text-center text-red-500">
                {{ error }}
            </div>

            <div v-if="loading && !hasData" class="flex h-[280px] items-center justify-center sm:h-[350px]">
                <Skeleton class="h-full w-full" />
            </div>

            <div v-else-if="!hasData" class="text-muted-foreground flex h-[280px] items-center justify-center sm:h-[350px]">
                No data available for the selected time range.
            </div>

            <div v-else class="flex flex-1 flex-col">
                <ChartContainer :config="chartConfig" class="h-[280px] w-full min-w-0 sm:h-[350px]">
                    <VisXYContainer :data="chartData" :margin="{ top: 8, right: 8, bottom: 24, left: 32 }">
                        <template v-for="(area, index) in series" :key="`line-${area.id}`">
                            <VisLine
                                v-if="isAreaVisible(area.id)"
                                :x="(d: ChartDataPoint) => d.date.getTime()"
                                :y="areaAccessors[index]"
                                color="var(--color-outline)"
                                :curve-type="chartStyle === 'spline' ? CurveType.MonotoneX : CurveType.Step"
                                :line-width="2"
                                :line-dash-array="lineDashForIndex(index)"
                            />
                        </template>
                        <VisAxis type="x" :tick-line="false" :domain-line="false" :grid-line="false" :tick-format="formatTickDate" />
                        <VisAxis
                            type="y"
                            :tick-line="false"
                            :domain-line="false"
                            :grid-line="true"
                            :tick-format="(d: number) => Math.round(d).toString()"
                        />
                        <ChartTooltip />
                        <ChartCrosshair
                            :template="
                                componentToString(chartConfig, ChartTooltipContent, {
                                    indicator: 'line',
                                    labelFormatter: (d: number | Date) =>
                                        new Date(typeof d === 'number' ? d : d.getTime()).toLocaleString([], {
                                            month: 'short',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        }),
                                })
                            "
                            :color="areaColors"
                        />
                    </VisXYContainer>
                </ChartContainer>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    <button
                        v-for="(area, index) in series"
                        :key="`legend-${area.id}`"
                        type="button"
                        class="hover:bg-accent flex cursor-pointer items-center gap-2 rounded px-1 py-0.5 text-left"
                        :aria-label="`Show only ${area.name}. Shift-click to toggle visibility.`"
                        :title="`${area.name} (${area.event_name})`"
                        @click="toggleLegendArea(area.id, $event)"
                    >
                        <svg :width="24" :height="8" :class="{ 'opacity-40': !isAreaVisible(area.id) }">
                            <line
                                x1="0"
                                y1="4"
                                x2="24"
                                y2="4"
                                stroke="currentColor"
                                stroke-width="2"
                                :stroke-dasharray="lineDashForIndex(index)?.join(',') ?? 'none'"
                            />
                        </svg>
                        <span :class="{ 'text-muted-foreground line-through': !isAreaVisible(area.id) }">
                            {{ shortenName(area.name) }}
                        </span>
                    </button>
                </div>
                <div class="text-muted-foreground mt-auto border-t pt-3 text-center text-xs">Last updated: {{ lastUpdatedTime }}</div>
            </div>
        </CardContent>
    </Card>
</template>
