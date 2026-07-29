<script lang="ts" setup>
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Skeleton } from '@/components/ui/skeleton';
import WidgetNotice from '@/components/widgets/WidgetNotice.vue';
import WidgetShell from '@/components/widgets/WidgetShell.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useWidgetPolling } from '@/composables/useWidgetPolling';
import { APP_LOCALE } from '@/utils/dateTimeHelpers';
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
    debug_counts: {
        in: number;
        out: number;
        net: number;
        last_reset_type?: string;
        last_reset_at?: string; // will be serialized from Carbon
        last_reset_value?: number;
        net_plus_reset?: number;
    };
    last_updated: string | null;
}

const props = defineProps<{
    organization: { id: number; slug: string; name: string };
}>();

const { can } = usePermissions();

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

// Check if user can view debug counts
const canViewDebugCounts = computed(() => can('peoplecount.areas.*'));
const latestDataAt = computed(() => {
    const timestamps = (areaCounts.value ?? []).flatMap((area) => (area.last_updated ? [new Date(area.last_updated).getTime()] : []));
    return timestamps.length ? new Date(Math.max(...timestamps)) : null;
});

function formatDate(dateString: string | null): string {
    if (!dateString) {
        return 'N/A';
    }

    return new Date(dateString).toLocaleTimeString(APP_LOCALE);
}

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

                    <!-- Debug counts collapsible section -->
                    <div v-if="canViewDebugCounts" class="mt-4 w-full">
                        <Collapsible>
                            <CollapsibleTrigger
                                class="text-muted-foreground hover:text-foreground flex w-full items-center justify-center text-xs transition-colors"
                            >
                                <span>Debug Counts</span>
                                <svg class="ml-1 h-3 w-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M19 9l-7 7-7-7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
                                </svg>
                            </CollapsibleTrigger>
                            <CollapsibleContent class="mt-2">
                                <div class="grid grid-cols-3 gap-2 text-xs">
                                    <div class="rounded bg-emerald-50/70 p-2 text-center dark:bg-emerald-950/20">
                                        <div class="font-medium text-emerald-700 dark:text-emerald-300/80">In</div>
                                        <div class="text-emerald-700 dark:text-emerald-300/80">{{ area.debug_counts.in }}</div>
                                    </div>
                                    <div class="rounded bg-rose-50/70 p-2 text-center dark:bg-rose-950/20">
                                        <div class="font-medium text-rose-700 dark:text-rose-300/80">Out</div>
                                        <div class="text-rose-700 dark:text-rose-300/80">{{ area.debug_counts.out }}</div>
                                    </div>
                                    <div class="rounded bg-blue-50 p-2 text-center dark:bg-blue-950/30">
                                        <div class="font-medium text-blue-700 dark:text-blue-400">Net</div>
                                        <div class="text-blue-600 dark:text-blue-400">{{ area.debug_counts.net }}</div>
                                    </div>
                                </div>
                                <div v-if="area.debug_counts.last_reset_type" class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                    <div class="bg-muted/50 rounded p-2">
                                        <div class="text-foreground font-medium">Last reset</div>
                                        <div class="text-muted-foreground capitalize">{{ area.debug_counts.last_reset_type.replace('_', ' ') }}</div>
                                    </div>
                                    <div class="bg-muted/50 rounded p-2">
                                        <div class="text-foreground font-medium">At</div>
                                        <div class="text-muted-foreground">{{ formatDate(area.debug_counts.last_reset_at || null) }}</div>
                                    </div>
                                    <div class="bg-muted/50 rounded p-2">
                                        <div class="text-foreground font-medium">Reset value</div>
                                        <div class="text-muted-foreground">{{ area.debug_counts.last_reset_value }}</div>
                                    </div>
                                    <div class="bg-muted/50 rounded p-2">
                                        <div class="text-foreground font-medium">Net + reset</div>
                                        <div class="text-muted-foreground">{{ area.debug_counts.net_plus_reset }}</div>
                                    </div>
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    </div>
                </div>
            </div>
        </div>
    </WidgetShell>
</template>
