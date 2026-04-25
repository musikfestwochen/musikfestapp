<script lang="ts" setup>
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import axios from 'axios';
import type { TooltipItem } from 'chart.js';
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    TimeScale,
    Title,
    Tooltip,
} from 'chart.js';
import 'chartjs-adapter-date-fns';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Line } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, TimeScale, PointElement, LineElement, Title, Tooltip, Legend, Filler);

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

const props = defineProps<{
    organization: { id: number; slug: string; name: string };
}>();

const series = ref<AreaSeries[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const timeRange = ref('1h');
const customRange = ref<[Date, Date] | null>(null);
let refreshInterval: number | null = null;

const AREA_COLORS = [
    { border: 'rgb(59, 130, 246)', background: 'rgba(59, 130, 246, 0.1)' },
    { border: 'rgb(239, 68, 68)', background: 'rgba(239, 68, 68, 0.1)' },
    { border: 'rgb(34, 197, 94)', background: 'rgba(34, 197, 94, 0.1)' },
    { border: 'rgb(168, 85, 247)', background: 'rgba(168, 85, 247, 0.1)' },
    { border: 'rgb(249, 115, 22)', background: 'rgba(249, 115, 22, 0.1)' },
    { border: 'rgb(20, 184, 166)', background: 'rgba(20, 184, 166, 0.1)' },
];

const timeRangeOptions = [
    { value: '1h', label: 'Last hour' },
    { value: '3h', label: 'Last 3 hours' },
    { value: '6h', label: 'Last 6 hours' },
    { value: '12h', label: 'Last 12 hours' },
    { value: '24h', label: 'Last 24 hours' },
    { value: 'custom', label: 'Custom range' },
];

const isCustomRange = computed(() => timeRange.value === 'custom');

function getTimeParams(): { from: string; to: string } {
    if (timeRange.value === 'custom' && customRange.value) {
        return {
            from: customRange.value[0].toISOString(),
            to: customRange.value[1].toISOString(),
        };
    }

    const now = new Date();
    const hours = parseInt(timeRange.value);
    const from = new Date(now.getTime() - hours * 60 * 60 * 1000);
    return { from: from.toISOString(), to: now.toISOString() };
}

const chartData = computed(() => {
    return {
        datasets: series.value.map((area, index) => {
            const color = AREA_COLORS[index % AREA_COLORS.length];
            return {
                label: area.name,
                data: area.data.map((d) => ({ x: new Date(d.time).getTime(), y: d.count })),
                borderColor: color.border,
                backgroundColor: color.background,
                borderWidth: 2,
                pointRadius: area.data.length > 100 ? 0 : 3,
                pointHoverRadius: 5,
                tension: 0.3,
                fill: true,
            };
        }),
    };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index' as const,
        intersect: false,
    },
    scales: {
        x: {
            type: 'time' as const,
            time: {
                tooltipFormat: 'HH:mm',
                displayFormats: {
                    minute: 'HH:mm',
                    hour: 'HH:mm',
                },
            },
            title: {
                display: true,
                text: 'Time',
            },
            grid: {
                display: false,
            },
        },
        y: {
            beginAtZero: true,
            title: {
                display: true,
                text: 'People count',
            },
            ticks: {
                precision: 0,
            },
        },
    },
    plugins: {
        legend: {
            position: 'top' as const,
        },
        tooltip: {
            callbacks: {
                title: (items: TooltipItem<'line'>[]) => {
                    if (!items.length || items[0].parsed.x == null) return '';
                    const date = new Date(items[0].parsed.x);
                    return date.toLocaleTimeString();
                },
            },
        },
    },
}));

async function fetchHistory() {
    if (timeRange.value === 'custom' && !customRange.value) return;

    try {
        error.value = null;
        const params = getTimeParams();
        const response = await axios.get(`/${props.organization.slug}/peoplecount/area-count-history`, { params });
        series.value = response.data;
    } catch (err) {
        error.value = 'Failed to load area count history';
        console.error(err);
    } finally {
        loading.value = false;
    }
}

watch([timeRange, customRange], () => {
    loading.value = true;
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
    <Card class="col-span-full">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle>Area Count History</CardTitle>
            <div class="flex items-center gap-2">
                <Select v-model="timeRange">
                    <SelectTrigger class="w-[160px]">
                        <SelectValue placeholder="Select range" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="option in timeRangeOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <VueDatePicker
                    v-if="isCustomRange"
                    v-model="customRange"
                    range
                    :enable-time-picker="true"
                    :auto-apply="true"
                    :dark="false"
                    input-class-name="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                    class="w-[280px]"
                />
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="error" class="mb-4 rounded bg-red-50 p-2 text-center text-red-500">
                {{ error }}
            </div>

            <div v-if="loading && !series.length" class="flex h-[350px] items-center justify-center">
                <Skeleton class="h-full w-full" />
            </div>

            <div v-else-if="!series.length || series.every((s) => s.data.length === 0)" class="flex h-[350px] items-center justify-center text-muted-foreground">
                No data available for the selected time range.
            </div>

            <div v-else class="h-[350px]">
                <Line :data="chartData" :options="chartOptions" />
            </div>
        </CardContent>
    </Card>
</template>
