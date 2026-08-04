<script lang="ts" setup>
import { Skeleton } from '@/components/ui/skeleton';
import WidgetNotice from '@/components/widgets/WidgetNotice.vue';
import WidgetShell from '@/components/widgets/WidgetShell.vue';
import { useWidgetPolling } from '@/composables/useWidgetPolling';
import { useHttp } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface AreaCount {
    id: number;
    name: string;
    event_name: string;
    count: number;
    net_change: number | null;
    net_change_time_ago: string | null;
    last_updated: string | null;
}

const props = defineProps<{
    organization: { id: number; slug: string; name: string };
}>();

const request = useHttp<Record<string, never>, AreaCount[]>({});
const {
    data: areaCounts,
    loading,
    error,
} = useWidgetPolling({
    interval: 20_000,
    load: () => request.get(route('peoplecount.area-aggregation.index', { organization: props.organization.slug })),
    errorMessage: 'Failed to load area counts',
});

const latestDataAt = computed(() => {
    const timestamps = (areaCounts.value ?? []).flatMap((area) => (area.last_updated ? [new Date(area.last_updated).getTime()] : []));
    return timestamps.length ? new Date(Math.max(...timestamps)) : null;
});

const isDataStale = computed(() => {
    if (!latestDataAt.value) {
        return false;
    }

    return Date.now() - latestDataAt.value.getTime() > 60_000;
});
</script>

<template>
    <WidgetShell title="Active Area Counts" :error="error" :last-updated="latestDataAt">
        <template #icon><Users /></template>

        <WidgetNotice v-if="isDataStale" class="mb-4" variant="warning">Data may be stale.</WidgetNotice>

        <div v-if="loading && !areaCounts?.length">
            <Skeleton v-for="i in 2" :key="i" class="mb-4 h-24 w-full" />
        </div>

        <div v-else-if="areaCounts && !areaCounts.length" class="text-muted-foreground py-8 text-center">No active areas found.</div>

        <div v-else-if="areaCounts?.length" class="flex flex-1 flex-col">
            <div class="divide-y">
                <div v-for="area in areaCounts" :key="area.id" class="area-item flex flex-col items-center py-4 text-center first:pt-0 last:pb-0">
                    <div class="mb-2 text-center">
                        <div class="text-lg font-medium">{{ area.name }}</div>
                        <div class="text-muted-foreground text-sm">{{ area.event_name }}</div>
                    </div>
                    <div class="count-display text-foreground my-4 text-6xl leading-none font-bold">{{ area.count }}</div>

                    <!-- Net change display -->
                    <div
                        v-if="area.net_change !== null"
                        :class="{
                            'text-emerald-700 dark:text-emerald-300/80': area.net_change > 0,
                            'text-rose-700 dark:text-rose-300/80': area.net_change < 0,
                            'text-gray-500': area.net_change === 0,
                        }"
                        class="net-change"
                    >
                        <span v-if="area.net_change > 0">+{{ area.net_change }}</span>
                        <span v-else>{{ area.net_change }}</span>
                        <span class="text-muted-foreground ml-1 text-xs">({{ area.net_change_time_ago }})</span>
                    </div>
                    <div v-else class="text-xs text-gray-500">No net change data</div>
                </div>
            </div>
        </div>
    </WidgetShell>
</template>
