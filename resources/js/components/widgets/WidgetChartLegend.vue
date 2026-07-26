<script setup lang="ts">
import type { WidgetChartSeries } from '@/components/widgets/widgetChart';

const props = defineProps<{
    series: WidgetChartSeries[];
    hiddenSeriesKeys: Set<string>;
}>();

const emit = defineEmits<{
    select: [key: string, additive: boolean];
}>();

function isVisible(key: string): boolean {
    return !props.hiddenSeriesKeys.has(key);
}
</script>

<template>
    <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 border-t pt-3 text-sm">
        <button
            v-for="item in series"
            :key="item.key"
            type="button"
            class="hover:bg-accent flex cursor-pointer items-center gap-2 rounded px-1 py-0.5 text-left"
            :aria-label="`Show only ${item.label}. Shift-click to add or remove this series.`"
            :aria-pressed="isVisible(item.key)"
            :data-series="item.key"
            :title="item.label"
            @click="emit('select', item.key, $event.shiftKey)"
        >
            <svg width="24" height="8" :style="{ color: item.color }" :class="{ 'opacity-40': !isVisible(item.key) }" aria-hidden="true">
                <line x1="0" y1="4" x2="24" y2="4" stroke="currentColor" stroke-width="2" :stroke-dasharray="item.dash?.join(',') ?? 'none'" />
            </svg>
            <span :class="{ 'text-muted-foreground line-through': !isVisible(item.key) }">{{ item.label }}</span>
        </button>
    </div>
</template>
