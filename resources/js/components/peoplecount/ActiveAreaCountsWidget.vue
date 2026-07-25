<script lang="ts" setup>
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Skeleton } from '@/components/ui/skeleton';
import { usePermissions } from '@/composables/usePermissions';
import axios from 'axios';
import { Users } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface AreaCount {
    id: number;
    name: string;
    event_name: string;
    count: number;
    net_change: number | null;
    net_change_time_ago: number;
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

const areaCounts = ref<AreaCount[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
let refreshInterval: number | null = null;

// Check if user can view debug counts
const canViewDebugCounts = computed(() => can('peoplecount.areas.*'));

const fetchAreaCounts = async () => {
    try {
        loading.value = true;
        error.value = null;

        const response = await axios.get(`/${props.organization.slug}/peoplecount/area-aggregation`);
        areaCounts.value = response.data;
    } catch (err) {
        error.value = 'Failed to load area counts';
        console.error(err);
        // We don't clear areaCounts here, so the last known counts will still be displayed
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchAreaCounts();
    // Set up auto-refresh every 10 seconds
    refreshInterval = window.setInterval(fetchAreaCounts, 10000);
});

onBeforeUnmount(() => {
    // Clean up interval when component is unmounted
    if (refreshInterval !== null) {
        clearInterval(refreshInterval);
    }
});

const formatDate = (dateString: string | null) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleTimeString();
};

// Check if the last update is more than one minute old
const isDataStale = computed(() => {
    if (!areaCounts.value.length || !areaCounts.value[0]?.last_updated) return false;

    const lastUpdated = new Date(areaCounts.value[0].last_updated);
    const now = new Date();

    const diffInMs = now.getTime() - lastUpdated.getTime();
    const diffInMinutes = diffInMs / (1000 * 60);

    return diffInMinutes > 1;
});

// Last server response timestamp
const lastUpdatedTime = computed(() => {
    return areaCounts.value.length > 0 ? formatDate(areaCounts.value[0]?.last_updated) : 'N/A';
});
</script>

<style scoped>
.counts-widget {
    min-height: 300px;
    min-width: 250px;
}

.counts-widget-content {
    padding: 1.5rem;
}

.area-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1rem 0;
}

.count-display {
    font-size: 4rem;
    font-weight: 700;
    line-height: 1;
    margin: 1rem 0;
}

.stale-card {
    background-color: rgba(239, 68, 68, 0.1); /* Light red background */
    border-color: rgb(239, 68, 68); /* Red border */
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
    }
}

.pulse {
    animation: pulse 2s infinite;
}
</style>

<template>
    <Card :class="{ 'stale-card pulse': isDataStale, 'counts-widget': true }" class="flex h-full flex-col">
        <CardHeader>
            <CardTitle class="flex items-center gap-2"><Users class="size-4" aria-hidden="true" /> Active Area Counts</CardTitle>
        </CardHeader>
        <CardContent class="counts-widget-content flex flex-1 flex-col">
            <!-- Show error message as a banner if there's an error -->
            <div v-if="error" class="mb-4 rounded bg-red-50 p-2 text-center text-red-500">
                {{ error }}
            </div>

            <div v-if="loading && !areaCounts.length">
                <Skeleton v-for="i in 2" :key="i" class="mb-4 h-24 w-full" />
            </div>

            <div v-else-if="!areaCounts.length" class="text-muted-foreground py-8 text-center">No active areas found.</div>

            <div v-else class="flex flex-1 flex-col">
                <div class="divide-y">
                    <div v-for="area in areaCounts" :key="area.id" class="area-item py-4 first:pt-0 last:pb-0">
                        <div class="mb-2 text-center">
                            <div class="text-lg font-medium">{{ area.name }}</div>
                            <div class="text-muted-foreground text-sm">{{ area.event_name }}</div>
                        </div>
                        <div class="count-display">{{ area.count }}</div>

                        <!-- Net change display -->
                        <div
                            v-if="area.net_change !== null"
                            :class="{
                                'text-green-600': area.net_change > 0,
                                'text-red-600': area.net_change < 0,
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
                                        <div class="rounded bg-green-50 p-2 text-center">
                                            <div class="font-medium text-green-700">In</div>
                                            <div class="text-green-600">{{ area.debug_counts.in }}</div>
                                        </div>
                                        <div class="rounded bg-red-50 p-2 text-center">
                                            <div class="font-medium text-red-700">Out</div>
                                            <div class="text-red-600">{{ area.debug_counts.out }}</div>
                                        </div>
                                        <div class="rounded bg-blue-50 p-2 text-center">
                                            <div class="font-medium text-blue-700">Net</div>
                                            <div class="text-blue-600">{{ area.debug_counts.net }}</div>
                                        </div>
                                    </div>
                                    <div v-if="area.debug_counts.last_reset_type" class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                        <div class="rounded bg-gray-50 p-2">
                                            <div class="font-medium text-gray-700">Last reset</div>
                                            <div class="text-gray-600 capitalize">{{ area.debug_counts.last_reset_type.replace('_', ' ') }}</div>
                                        </div>
                                        <div class="rounded bg-gray-50 p-2">
                                            <div class="font-medium text-gray-700">At</div>
                                            <div class="text-gray-600">{{ formatDate(area.debug_counts.last_reset_at || null) }}</div>
                                        </div>
                                        <div class="rounded bg-gray-50 p-2">
                                            <div class="font-medium text-gray-700">Reset value</div>
                                            <div class="text-gray-600">{{ area.debug_counts.last_reset_value }}</div>
                                        </div>
                                        <div class="rounded bg-gray-50 p-2">
                                            <div class="font-medium text-gray-700">Net + reset</div>
                                            <div class="text-gray-600">{{ area.debug_counts.net_plus_reset }}</div>
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </div>
                    </div>
                </div>

                <div class="text-muted-foreground mt-auto border-t pt-3 text-center text-xs">Last updated: {{ lastUpdatedTime }}</div>
            </div>
        </CardContent>
    </Card>
</template>
