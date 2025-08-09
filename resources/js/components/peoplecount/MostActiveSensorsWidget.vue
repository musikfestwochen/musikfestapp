<script lang="ts" setup>
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface SensorSums {
    in: number;
    out: number;
    total: number;
}

interface SensorItem {
    id: number;
    serial: string;
    vendor: string;
    model: string;
    sums: Record<'10m' | '30m' | '1h' | '2h', SensorSums>;
}

interface AreaItem {
    id: number;
    name: string;
    event_name: string;
    sensors: SensorItem[];
    last_updated: string;
}

const props = defineProps<{ organization: { id: number; slug: string; name: string } }>();

const loading = ref(true);
const error = ref<string | null>(null);
const data = ref<AreaItem[]>([]);
const selectedRange = ref<'10m' | '30m' | '1h' | '2h'>('10m');
let refreshInterval: number | null = null;

// holds the open accordion area id when multiple areas exist
const openArea = ref<string | undefined>(undefined);

const fetchData = async () => {
    try {
        loading.value = true;
        error.value = null;
        const response = await axios.get(`/${props.organization.slug}/peoplecount/most-active-sensors`);
        data.value = response.data;
    } catch (err) {
        error.value = 'Failed to load most active sensors';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchData();
    refreshInterval = window.setInterval(fetchData, 10000);
});

onBeforeUnmount(() => {
    if (refreshInterval !== null) {
        clearInterval(refreshInterval);
    }
});

const lastUpdatedTime = computed(() => {
    if (!data.value.length) return 'N/A';
    return new Date(data.value[0].last_updated).toLocaleTimeString();
});

const sortedAreas = computed(() => {
    // sort sensors within each area by selected range total desc
    return data.value.map((area) => {
        const sensors = [...area.sensors].sort((a, b) => b.sums[selectedRange.value].total - a.sums[selectedRange.value].total);
        return { ...area, sensors };
    });
});

// Keep only one area open at a time (Accordion handles this), but ensure a sensible default
watch(
    () => sortedAreas.value.map((a) => a.id).join(','),
    () => {
        const areas = sortedAreas.value;
        if (areas.length <= 1) {
            openArea.value = undefined;
            return;
        }
        // preserve currently open if still present, else open the first
        if (!openArea.value || !areas.some((a) => String(a.id) === openArea.value)) {
            openArea.value = String(areas[0].id);
        }
    },
    { immediate: true },
);
</script>

<style scoped>
.widget-card {
    min-width: 250px;
}
</style>

<template>
    <Card class="widget-card">
        <CardHeader>
            <div class="flex items-center justify-between">
                <CardTitle>Most Active Sensors</CardTitle>
                <div class="inline-flex items-center gap-1">
                    <Button
                        :class="{ 'bg-primary text-primary-foreground': selectedRange === '10m' }"
                        size="sm"
                        variant="outline"
                        @click="selectedRange = '10m'"
                        >10m</Button
                    >
                    <Button
                        :class="{ 'bg-primary text-primary-foreground': selectedRange === '30m' }"
                        size="sm"
                        variant="outline"
                        @click="selectedRange = '30m'"
                        >30m</Button
                    >
                    <Button
                        :class="{ 'bg-primary text-primary-foreground': selectedRange === '1h' }"
                        size="sm"
                        variant="outline"
                        @click="selectedRange = '1h'"
                        >1h</Button
                    >
                    <Button
                        :class="{ 'bg-primary text-primary-foreground': selectedRange === '2h' }"
                        size="sm"
                        variant="outline"
                        @click="selectedRange = '2h'"
                        >2h</Button
                    >
                </div>
            </div>
        </CardHeader>
        <CardContent class="p-6">
            <div v-if="error" class="mb-4 rounded bg-red-50 p-2 text-center text-red-500">{{ error }}</div>

            <div v-if="loading && !data.length">
                <Skeleton v-for="i in 2" :key="i" class="mb-4 h-24 w-full" />
            </div>

            <div v-else-if="!data.length" class="py-8 text-center text-muted-foreground">No active areas or sensors.</div>

            <div v-else class="space-y-4">
                <!-- Single area: show expanded content without collapsible -->
                <div v-if="sortedAreas.length === 1" class="rounded border">
                    <div class="mb-2 flex items-center justify-between p-3">
                        <div>
                            <div class="text-sm font-medium">{{ sortedAreas[0].name }}</div>
                            <div class="text-xs text-muted-foreground">{{ sortedAreas[0].event_name }}</div>
                        </div>
                        <div class="text-xs text-muted-foreground">Sorted by total ({{ selectedRange }})</div>
                    </div>
                    <div v-if="!sortedAreas[0].sensors.length" class="px-3 pb-3 text-xs text-muted-foreground">No sensors assigned.</div>
                    <ul v-else class="divide-y px-3 pb-3 text-sm">
                        <li v-for="s in sortedAreas[0].sensors" :key="s.id" class="py-2">
                            <div class="flex flex-col">
                                <div class="truncate">{{ s.vendor }} {{ s.model }}</div>
                                <div class="mt-1 flex gap-3 text-xs">
                                    <span class="text-green-600">In: {{ s.sums[selectedRange].in }}</span>
                                    <span class="text-red-600">Out: {{ s.sums[selectedRange].out }}</span>
                                    <span class="text-muted-foreground">Total: {{ s.sums[selectedRange].total }}</span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Multiple areas: use Accordion so only one open at a time -->
                <Accordion v-else v-model="openArea" class="w-full" collapsible type="single">
                    <AccordionItem v-for="area in sortedAreas" :key="area.id" :value="String(area.id)">
                        <AccordionTrigger class="px-3 py-2">
                            <div class="flex w-full items-center justify-between">
                                <div class="text-left">
                                    <div class="text-sm font-medium">{{ area.name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ area.event_name }}</div>
                                </div>
                                <div class="text-xs text-muted-foreground">Sorted by total ({{ selectedRange }})</div>
                            </div>
                        </AccordionTrigger>
                        <AccordionContent>
                            <div v-if="!area.sensors.length" class="px-3 pb-3 text-xs text-muted-foreground">No sensors assigned.</div>
                            <ul v-else class="divide-y px-3 pb-3 text-sm">
                                <li v-for="s in area.sensors" :key="s.id" class="py-2">
                                    <div class="flex flex-col">
                                        <div class="truncate">{{ s.vendor }} {{ s.model }}</div>
                                        <div class="mt-1 flex gap-3 text-xs">
                                            <span class="text-green-600">In: {{ s.sums[selectedRange].in }}</span>
                                            <span class="text-red-600">Out: {{ s.sums[selectedRange].out }}</span>
                                            <span class="text-muted-foreground">Total: {{ s.sums[selectedRange].total }}</span>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>

                <div class="mt-2 text-center text-xs text-muted-foreground">Last updated: {{ lastUpdatedTime }}</div>
            </div>
        </CardContent>
    </Card>
</template>
