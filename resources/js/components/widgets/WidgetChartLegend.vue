<script setup lang="ts">
import type { WidgetChartSeries } from '@/components/widgets/widgetChart';
import type { WidgetChartStatistics } from '@/components/widgets/widgetChart';

const props = defineProps<{
    series: WidgetChartSeries[];
    hiddenSeriesKeys: Set<string>;
    statisticsEnabled?: boolean;
    statistics?: Partial<Record<string, WidgetChartStatistics | null>>;
    formatValue?: (value: number) => string;
}>();

const emit = defineEmits<{
    select: [key: string, additive: boolean];
}>();

function isVisible(key: string): boolean {
    return !props.hiddenSeriesKeys.has(key);
}

function statisticValue(key: string, kind: 'minimum' | 'average' | 'maximum'): string {
    const statistics = props.statistics?.[key];

    if (!statistics) {
        return 'N/A';
    }

    const value = kind === 'average' ? statistics.average : statistics[kind].value;

    return props.formatValue?.(value) ?? value.toString();
}
</script>

<template>
    <div v-if="statisticsEnabled" class="mt-2 border-t pt-2 text-sm sm:mt-4 sm:pt-3">
        <div class="text-muted-foreground hidden grid-cols-[minmax(0,1fr)_repeat(3,minmax(4rem,auto))] gap-x-4 px-2 pb-1 text-xs sm:grid">
            <span>Series</span>
            <span class="text-right">Min</span>
            <span class="text-right">Avg</span>
            <span class="text-right">Max</span>
        </div>
        <button
            v-for="item in series"
            :key="item.key"
            type="button"
            class="hover:bg-accent grid w-full cursor-pointer grid-cols-3 gap-x-3 gap-y-2 rounded px-2 py-2 text-left sm:grid-cols-[minmax(0,1fr)_repeat(3,minmax(4rem,auto))] sm:gap-x-4 sm:gap-y-0"
            :aria-label="`Show only ${item.label}. Shift-click to add or remove this series.`"
            :aria-pressed="isVisible(item.key)"
            :data-series="item.key"
            :title="item.label"
            @click="emit('select', item.key, $event.shiftKey)"
        >
            <span class="col-span-3 flex min-w-0 items-center gap-2 sm:col-span-1">
                <svg width="24" height="8" :style="{ color: item.color }" :class="{ 'opacity-40': !isVisible(item.key) }" aria-hidden="true">
                    <line x1="0" y1="4" x2="24" y2="4" stroke="currentColor" stroke-width="2" :stroke-dasharray="item.dash?.join(',') ?? 'none'" />
                </svg>
                <span class="truncate" :class="{ 'text-muted-foreground line-through': !isVisible(item.key) }">{{ item.label }}</span>
            </span>
            <span class="tabular-nums" :class="{ 'text-muted-foreground': !isVisible(item.key) }">
                <span class="text-muted-foreground block text-xs sm:hidden">Min</span>
                <span class="block sm:text-right">{{ statisticValue(item.key, 'minimum') }}</span>
            </span>
            <span class="tabular-nums" :class="{ 'text-muted-foreground': !isVisible(item.key) }">
                <span class="text-muted-foreground block text-xs sm:hidden">Avg</span>
                <span class="block sm:text-right">{{ statisticValue(item.key, 'average') }}</span>
            </span>
            <span class="tabular-nums" :class="{ 'text-muted-foreground': !isVisible(item.key) }">
                <span class="text-muted-foreground block text-xs sm:hidden">Max</span>
                <span class="block sm:text-right">{{ statisticValue(item.key, 'maximum') }}</span>
            </span>
        </button>
    </div>

    <div v-else class="mt-2 flex flex-wrap gap-x-5 gap-y-2 border-t pt-2 text-sm sm:mt-4 sm:pt-3">
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
