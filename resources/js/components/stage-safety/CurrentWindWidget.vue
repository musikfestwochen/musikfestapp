<script setup lang="ts">
import CurrentWindDisplay from '@/components/stage-safety/CurrentWindDisplay.vue';
import { Skeleton } from '@/components/ui/skeleton';
import WidgetShell from '@/components/widgets/WidgetShell.vue';
import { useWidgetPolling } from '@/composables/useWidgetPolling';
import type { Organization, StageSafetyCurrentWindPayload } from '@/types';
import { useHttp } from '@inertiajs/vue3';
import { Wind } from 'lucide-vue-next';

const props = defineProps<{ organization: Organization }>();
const request = useHttp<Record<string, never>, StageSafetyCurrentWindPayload>({});
const { data, loading, error, lastUpdated } = useWidgetPolling({
    interval: 10_000,
    load: () => request.get(route('stage-safety.current-wind.index', { organization: props.organization.slug })),
    errorMessage: 'Failed to load current wind.',
});
</script>

<template>
    <WidgetShell title="Current Wind" :error="error" :last-updated="lastUpdated">
        <template #icon><Wind /></template>

        <div v-if="loading && !data">
            <Skeleton class="h-40 w-full rounded-xl" />
        </div>
        <div v-else-if="data && !data.sensors.length" class="text-muted-foreground flex min-h-48 items-center justify-center text-center">
            No sensors currently report fresh wind data.
        </div>
        <CurrentWindDisplay v-else-if="data" :sensors="data.sensors" />
    </WidgetShell>
</template>
