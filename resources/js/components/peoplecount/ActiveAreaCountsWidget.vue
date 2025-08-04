<script lang="ts" setup>
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface AreaCount {
    id: number;
    name: string;
    event_name: string;
    count: number;
    last_updated: string | null;
}

const props = defineProps<{
    organization: { id: number; slug: string; name: string };
}>();

const areaCounts = ref<AreaCount[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
let refreshInterval: number | null = null;

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
    <Card :class="{ 'stale-card pulse': isDataStale, 'counts-widget': true }">
        <CardHeader>
            <CardTitle>Active Area Counts</CardTitle>
        </CardHeader>
        <CardContent class="counts-widget-content">
            <!-- Show error message as a banner if there's an error -->
            <div v-if="error" class="mb-4 rounded bg-red-50 p-2 text-center text-red-500">
                {{ error }}
            </div>

            <div v-if="loading && !areaCounts.length">
                <Skeleton v-for="i in 2" :key="i" class="mb-4 h-24 w-full" />
            </div>

            <div v-else-if="!areaCounts.length" class="py-8 text-center text-muted-foreground">No active areas found.</div>

            <div v-else class="space-y-6">
                <div v-for="area in areaCounts" :key="area.id" class="area-item border-b pb-4">
                    <div class="mb-2 text-center">
                        <div class="text-lg font-medium">{{ area.name }}</div>
                        <div class="text-sm text-muted-foreground">{{ area.event_name }}</div>
                    </div>
                    <div class="count-display">{{ area.count }}</div>
                </div>

                <div class="mt-4 text-center text-xs text-muted-foreground">Last updated: {{ lastUpdatedTime }}</div>
            </div>
        </CardContent>
    </Card>
</template>
