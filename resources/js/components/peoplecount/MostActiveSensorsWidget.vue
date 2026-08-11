<script lang="ts" setup>
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import WidgetShell from '@/components/widgets/WidgetShell.vue';
import { useWidgetPolling } from '@/composables/useWidgetPolling';
import { useHttp } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    name: string | null;
    label: string | null;
    sums: Record<'10m' | '30m' | '1h' | '2h', SensorSums>;
}

interface AreaItem {
    id: number;
    name: string;
    event_name: string;
    sensors: SensorItem[];
    last_updated: string | null;
}

const props = defineProps<{ organization: { id: number; slug: string; name: string } }>();

const request = useHttp<Record<string, never>, AreaItem[]>({});
const { data, loading, error } = useWidgetPolling({
    interval: 20_000,
    load: () => request.get(route('peoplecount.most-active-sensors.index', { organization: props.organization.slug })),
    errorMessage: 'Failed to load most active sensors',
});
const selectedRange = ref<'10m' | '30m' | '1h' | '2h'>('10m');
const latestDataAt = computed(() => {
    const timestamps = (data.value ?? []).flatMap((area) => (area.last_updated ? [new Date(area.last_updated).getTime()] : []));
    return timestamps.length ? new Date(Math.max(...timestamps)) : null;
});

// holds the open accordion area id when multiple areas exist
const openArea = ref<string | undefined>(undefined);

const sortedAreas = computed(() => {
    // sort sensors within each area by selected range total desc
    return (data.value ?? []).map((area) => {
        const sensors = [...area.sensors].sort((a, b) => b.sums[selectedRange.value].total - a.sums[selectedRange.value].total);
        return { ...area, sensors };
    });
});

function summarizeSensors(sensors: SensorItem[]): SensorSums {
    return sensors.reduce(
        (totals, sensor) => {
            const sums = sensor.sums[selectedRange.value];
            totals.in += sums.in;
            totals.out += sums.out;
            totals.total += sums.total;

            return totals;
        },
        { in: 0, out: 0, total: 0 },
    );
}

const summarizedAreas = computed(() => sortedAreas.value.map((area) => ({ ...area, summary: summarizeSensors(area.sensors) })));
const totalSummary = computed(() => summarizeSensors(sortedAreas.value.flatMap((area) => area.sensors)));
const summaryMetricDefinitions = [
    { key: 'in', label: 'In', class: 'text-emerald-700 dark:text-emerald-300/80' },
    { key: 'out', label: 'Out', class: 'text-rose-700 dark:text-rose-300/80' },
    { key: 'total', label: 'Total', class: 'text-foreground' },
] as const;

// Keep only one area open at a time (Accordion handles this), but ensure a sensible default
watch(
    () => sortedAreas.value.map((a) => a.id).join(','),
    () => {
        const areas = summarizedAreas.value;
        if (areas.length <= 1) {
            openArea.value = undefined;
            return;
        }
        // preserve currently open if still present, else open first area
        if (!openArea.value || (openArea.value !== 'total' && !areas.some((a) => String(a.id) === openArea.value))) {
            openArea.value = String(areas[0].id);
        }
    },
    { immediate: true },
);
</script>

<template>
    <WidgetShell title="Recent Activity" :error="error" :last-updated="latestDataAt">
        <template #icon><Users /></template>
        <template #actions>
            <div class="flex flex-wrap items-center gap-1">
                <Button
                    :class="{ 'border-foreground/60 border': selectedRange === '10m' }"
                    :aria-pressed="selectedRange === '10m'"
                    size="sm"
                    variant="ghost"
                    @click="selectedRange = '10m'"
                    >10m</Button
                >
                <Button
                    :class="{ 'border-foreground/60 border': selectedRange === '30m' }"
                    :aria-pressed="selectedRange === '30m'"
                    size="sm"
                    variant="ghost"
                    @click="selectedRange = '30m'"
                    >30m</Button
                >
                <Button
                    :class="{ 'border-foreground/60 border': selectedRange === '1h' }"
                    :aria-pressed="selectedRange === '1h'"
                    size="sm"
                    variant="ghost"
                    @click="selectedRange = '1h'"
                    >1h</Button
                >
                <Button
                    :class="{ 'border-foreground/60 border': selectedRange === '2h' }"
                    :aria-pressed="selectedRange === '2h'"
                    size="sm"
                    variant="ghost"
                    @click="selectedRange = '2h'"
                    >2h</Button
                >
            </div>
        </template>

        <div v-if="loading && !data?.length">
            <Skeleton v-for="i in 2" :key="i" class="mb-4 h-24 w-full" />
        </div>

        <div v-else-if="data && !data.length" class="text-muted-foreground py-8 text-center">No active areas or sensors.</div>

        <div v-else-if="data?.length" class="flex flex-1 flex-col space-y-4">
            <!-- Single area: show expanded content without collapsible -->
            <div v-if="summarizedAreas.length === 1" class="rounded border">
                <div class="mb-2 flex items-center justify-between p-3">
                    <div>
                        <div class="text-sm font-medium">{{ summarizedAreas[0].name }}</div>
                        <div class="text-muted-foreground text-xs">{{ summarizedAreas[0].event_name }}</div>
                    </div>
                    <div class="text-muted-foreground text-xs">Sorted by total ({{ selectedRange }})</div>
                </div>
                <dl class="bg-muted/30 mx-3 mb-3 grid grid-cols-3 divide-x rounded py-2 text-center" aria-label="Area sensor counts">
                    <div v-for="metric in summaryMetricDefinitions" :key="metric.key">
                        <dt class="text-muted-foreground text-[11px]">{{ metric.label }}</dt>
                        <dd :class="metric.class" class="text-sm font-semibold tabular-nums">{{ summarizedAreas[0].summary[metric.key] }}</dd>
                    </div>
                </dl>
                <div v-if="!summarizedAreas[0].sensors.length" class="text-muted-foreground px-3 pb-3 text-xs">No sensors assigned.</div>
                <ul v-else class="divide-y px-3 pb-3 text-sm">
                    <li v-for="s in summarizedAreas[0].sensors" :key="s.id" class="py-2">
                        <div class="flex flex-col">
                            <div class="truncate">{{ s.label || s.name || `${s.vendor} ${s.model}` }}</div>
                            <div class="mt-1 flex gap-3 text-xs">
                                <span class="text-emerald-700 dark:text-emerald-300/80">In: {{ s.sums[selectedRange].in }}</span>
                                <span class="text-rose-700 dark:text-rose-300/80">Out: {{ s.sums[selectedRange].out }}</span>
                                <span class="text-muted-foreground">Total: {{ s.sums[selectedRange].total }}</span>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Multiple areas: use Accordion so only one open at a time -->
            <Accordion v-else v-model="openArea" class="w-full" collapsible type="single">
                <AccordionItem v-for="area in summarizedAreas" :key="area.id" :value="String(area.id)">
                    <AccordionTrigger class="px-3 py-2">
                        <div class="flex w-full items-center justify-between gap-2">
                            <div class="text-left">
                                <div class="text-sm font-medium">{{ area.name }}</div>
                                <div class="text-muted-foreground text-xs">{{ area.event_name }}</div>
                            </div>
                            <div class="text-muted-foreground text-xs">Sorted by total ({{ selectedRange }})</div>
                        </div>
                    </AccordionTrigger>
                    <AccordionContent>
                        <dl
                            class="bg-muted/30 mx-3 mb-3 grid grid-cols-3 divide-x rounded py-2 text-center"
                            :aria-label="`${area.name} sensor counts`"
                        >
                            <div v-for="metric in summaryMetricDefinitions" :key="metric.key">
                                <dt class="text-muted-foreground text-[11px]">{{ metric.label }}</dt>
                                <dd :class="metric.class" class="text-sm font-semibold tabular-nums">{{ area.summary[metric.key] }}</dd>
                            </div>
                        </dl>
                        <div v-if="!area.sensors.length" class="text-muted-foreground px-3 pb-3 text-xs">No sensors assigned.</div>
                        <ul v-else class="divide-y px-3 pb-3 text-sm">
                            <li v-for="s in area.sensors" :key="s.id" class="py-2">
                                <div class="flex flex-col">
                                    <div class="truncate">{{ s.label || s.name || `${s.vendor} ${s.model}` }}</div>
                                    <div class="mt-1 flex gap-3 text-xs">
                                        <span class="text-emerald-700 dark:text-emerald-300/80">In: {{ s.sums[selectedRange].in }}</span>
                                        <span class="text-rose-700 dark:text-rose-300/80">Out: {{ s.sums[selectedRange].out }}</span>
                                        <span class="text-muted-foreground">Total: {{ s.sums[selectedRange].total }}</span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </AccordionContent>
                </AccordionItem>
                <AccordionItem value="total">
                    <AccordionTrigger class="px-3 py-2">
                        <div class="flex w-full items-center justify-between gap-2">
                            <div class="text-left">
                                <div class="text-sm font-medium">All areas</div>
                                <div class="text-muted-foreground text-xs">Combined sensor totals</div>
                            </div>
                            <div class="text-muted-foreground text-xs">{{ selectedRange }}</div>
                        </div>
                    </AccordionTrigger>
                    <AccordionContent>
                        <dl class="bg-muted/30 mx-3 mb-3 grid grid-cols-3 divide-x rounded py-2 text-center" aria-label="Total sensor counts">
                            <div v-for="metric in summaryMetricDefinitions" :key="metric.key">
                                <dt class="text-muted-foreground text-[11px]">{{ metric.label }}</dt>
                                <dd :class="metric.class" class="text-sm font-semibold tabular-nums">{{ totalSummary[metric.key] }}</dd>
                            </div>
                        </dl>
                        <div class="text-muted-foreground px-3 pb-3 text-xs">
                            Combined counts across active assignments. Sensors assigned to multiple areas count once per assignment.
                        </div>
                    </AccordionContent>
                </AccordionItem>
            </Accordion>
        </div>
    </WidgetShell>
</template>
