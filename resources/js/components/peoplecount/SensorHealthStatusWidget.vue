<script lang="ts" setup>
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface IntervalCountItem {
    ts_from: string;
    ts_to: string;
    count_in: number;
    count_out: number;
}

interface SensorItem {
    id: number;
    serial: string;
    vendor: string;
    model: string;
    name: string | null;
    latest_ts: string | null;
    interval_counts: IntervalCountItem[];
}

interface HealthPayload {
    last_updated: string;
    total: number;
    all_healthy: boolean;
    healthy: SensorItem[];
    suspicious: SensorItem[];
    unhealthy: SensorItem[];
}

const props = defineProps<{ organization: { id: number; slug: string; name: string } }>();

const data = ref<HealthPayload | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
let refreshInterval: number | null = null;

const fetchHealth = async () => {
    try {
        loading.value = true;
        error.value = null;
        const response = await axios.get(`/${props.organization.slug}/peoplecount/sensor-health`);
        data.value = response.data;
    } catch (err) {
        error.value = 'Failed to load sensor health';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchHealth();
    refreshInterval = window.setInterval(fetchHealth, 10000);
});

onBeforeUnmount(() => {
    if (refreshInterval !== null) {
        clearInterval(refreshInterval);
    }
});

const isRecent = computed(() => {
    if (!data.value?.last_updated) return false;
    const last = new Date(data.value.last_updated).getTime();
    const now = Date.now();
    // treat as stale if older than 2 minutes
    return now - last <= 2 * 60 * 1000;
});

const lastUpdatedTime = computed(() => {
    if (!data.value?.last_updated) return 'N/A';
    return new Date(data.value.last_updated).toLocaleTimeString();
});
</script>

<style scoped>
.widget-card {
    /* Remove fixed min-height on small screens to avoid excessive whitespace */
    min-width: 250px;
}

/* Keep equal heights on wider screens for grid consistency */
@media (min-width: 768px) {
    .widget-card {
        min-height: 300px;
    }
}
.dot {
    width: 10px;
    height: 10px;
    border-radius: 9999px;
    display: inline-block;
    margin-right: 8px;
}
.dot-green {
    background-color: #22c55e;
    box-shadow: 0 0 8px rgba(34, 197, 94, 0.8);
}
.dot-orange {
    background-color: #f59e0b;
    box-shadow: 0 0 8px rgba(245, 158, 11, 0.8);
}
.dot-red {
    background-color: #ef4444;
    box-shadow: 0 0 8px rgba(239, 68, 68, 0.8);
}

@keyframes pulse-green {
    0% {
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(34, 197, 94, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
    }
}
.pulse-green {
    animation: pulse-green 2s infinite;
}

.stale-card {
    background-color: rgba(107, 114, 128, 0.08);
}
</style>

<template>
    <Card :class="{ 'stale-card': !isRecent, 'widget-card': true }" class="flex h-full flex-col">
        <CardHeader>
            <CardTitle>Peoplecount Sensor Health</CardTitle>
        </CardHeader>
        <CardContent class="flex flex-1 flex-col p-6">
            <div v-if="error" class="mb-4 rounded bg-red-50 p-2 text-center text-red-500">{{ error }}</div>

            <div v-if="loading && !data">
                <Skeleton v-for="i in 2" :key="i" class="mb-4 h-24 w-full" />
            </div>

            <div v-else-if="!data">
                <div class="text-muted-foreground py-8 text-center">No sensor data</div>
            </div>

            <div v-else class="flex flex-1 flex-col">
                <div v-if="data.total === 0" class="flex items-center justify-between p-2">
                    <div class="flex items-center">
                        <span class="mr-2">No active sensors</span>
                    </div>
                </div>

                <div v-else-if="data.all_healthy" class="flex items-center justify-between p-2">
                    <div class="flex items-center">
                        <span :class="{ 'pulse-green': isRecent }" class="dot dot-green"></span>
                        <span>All {{ data.total }} sensors are healthy</span>
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <!-- Do not list healthy sensors -->

                    <template v-if="data.suspicious.length">
                        <div class="flex items-center p-2">
                            <span class="dot dot-orange"></span>
                            <span class="font-medium">Suspicious</span>
                            <span class="text-muted-foreground ml-2">({{ data.suspicious.length }})</span>
                        </div>
                        <ul class="grid grid-cols-2 gap-2 text-sm">
                            <li v-for="s in data.suspicious" :key="s.id" class="p-2">
                                <div class="flex items-center justify-between">
                                    <div class="truncate">{{ s.name || `${s.vendor} ${s.model}` }} · {{ s.serial }}</div>
                                    <div class="text-muted-foreground text-xs">
                                        {{ s.latest_ts ? new Date(s.latest_ts).toLocaleTimeString() : 'N/A' }}
                                    </div>
                                </div>
                                <div class="text-muted-foreground mt-1 text-xs">Recent counts are all zero</div>
                            </li>
                        </ul>
                    </template>

                    <template v-if="data.unhealthy.length">
                        <div class="flex items-center p-2">
                            <span class="dot dot-red"></span>
                            <span class="font-medium">Unhealthy</span>
                            <span class="text-muted-foreground ml-2">({{ data.unhealthy.length }})</span>
                        </div>
                        <ul class="grid grid-cols-2 gap-2 text-sm">
                            <li v-for="s in data.unhealthy" :key="s.id" class="p-2">
                                <div class="flex items-center justify-between">
                                    <div class="truncate">{{ s.name || `${s.vendor} ${s.model}` }} · {{ s.serial }}</div>
                                    <div class="text-muted-foreground text-xs">
                                        {{ s.latest_ts ? new Date(s.latest_ts).toLocaleTimeString() : 'N/A' }}
                                    </div>
                                </div>
                                <div class="text-muted-foreground mt-1 text-xs">No recent counts</div>
                            </li>
                        </ul>
                    </template>
                </div>

                <div class="text-muted-foreground mt-2 text-center text-xs">
                    Healthy: {{ data.healthy.length }} • Suspicious: {{ data.suspicious.length }} • Unhealthy: {{ data.unhealthy.length }}
                </div>
                <div class="text-muted-foreground mt-auto border-t pt-3 text-center text-xs">Last updated: {{ lastUpdatedTime }}</div>
            </div>
        </CardContent>
    </Card>
</template>
