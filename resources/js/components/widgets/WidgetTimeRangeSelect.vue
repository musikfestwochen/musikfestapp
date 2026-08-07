<script setup lang="ts">
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { WidgetTimeRange } from '@/components/widgets/widgetChart';
import { computed } from 'vue';

const props = defineProps<{
    modelValue: WidgetTimeRange;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: WidgetTimeRange];
}>();

const selectedRange = computed({
    get: () => props.modelValue,
    set: (value: WidgetTimeRange) => emit('update:modelValue', value),
});

const options: Array<{ value: WidgetTimeRange; label: string }> = [
    { value: '30m', label: 'Last 30 minutes' },
    { value: '1h', label: 'Last hour' },
    { value: '3h', label: 'Last 3 hours' },
    { value: '6h', label: 'Last 6 hours' },
    { value: '12h', label: 'Last 12 hours' },
    { value: '24h', label: 'Last 24 hours' },
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: 'day-before-yesterday', label: 'Day before yesterday' },
    { value: 'this-day-last-week', label: 'This day last week' },
];
</script>

<template>
    <Select v-model="selectedRange">
        <SelectTrigger class="hover:bg-accent/50 w-full border-transparent bg-transparent px-2 shadow-none sm:w-40" aria-label="History time range">
            <SelectValue placeholder="Select range" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
