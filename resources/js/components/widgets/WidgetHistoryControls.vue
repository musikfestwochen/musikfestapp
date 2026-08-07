<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import WidgetTimeRangeSelect from '@/components/widgets/WidgetTimeRangeSelect.vue';
import type { WidgetTimeRange } from '@/components/widgets/widgetChart';
import { useId } from 'vue';

defineProps<{
    timeRange: WidgetTimeRange;
    statisticsEnabled: boolean;
}>();

const emit = defineEmits<{
    'update:timeRange': [value: WidgetTimeRange];
    'update:statisticsEnabled': [value: boolean];
}>();

const statisticsId = useId();
</script>

<template>
    <div class="flex w-full items-center gap-2 sm:w-auto">
        <div class="min-w-0 flex-1 sm:flex-none">
            <WidgetTimeRangeSelect :model-value="timeRange" @update:model-value="emit('update:timeRange', $event)" />
        </div>
        <div class="flex h-10 shrink-0 items-center gap-2 px-1">
            <Label :for="statisticsId" class="cursor-pointer">Statistics</Label>
            <Switch
                :id="statisticsId"
                :model-value="statisticsEnabled"
                aria-label="Show chart statistics"
                @update:model-value="emit('update:statisticsEnabled', $event)"
            />
        </div>
    </div>
</template>
