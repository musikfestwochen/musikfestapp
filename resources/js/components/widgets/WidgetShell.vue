<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import WidgetNotice from '@/components/widgets/WidgetNotice.vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        subtitle?: string;
        error?: string | null;
        lastUpdated?: Date | null;
        span?: 'single' | 'full';
    }>(),
    {
        subtitle: undefined,
        error: null,
        lastUpdated: null,
        span: 'single',
    },
);

const cardClass = computed(() => (props.span === 'full' ? 'col-span-full' : undefined));

function formatLastUpdated(date: Date): string {
    const pad = (value: number): string => value.toString().padStart(2, '0');

    return `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}
</script>

<template>
    <Card :class="cardClass" class="flex h-full min-w-0 flex-col">
        <CardHeader class="flex flex-col gap-3 pb-4 sm:flex-row sm:items-start sm:justify-between sm:space-y-0">
            <div class="min-w-0">
                <CardTitle class="flex items-center gap-2">
                    <span v-if="$slots.icon" class="shrink-0 [&>svg]:size-4" aria-hidden="true">
                        <slot name="icon" />
                    </span>
                    <span>{{ title }}</span>
                </CardTitle>
                <p v-if="subtitle" class="text-muted-foreground mt-1 text-sm">{{ subtitle }}</p>
            </div>
            <div v-if="$slots.actions" class="shrink-0">
                <slot name="actions" />
            </div>
        </CardHeader>

        <CardContent class="flex min-w-0 flex-1 flex-col">
            <WidgetNotice v-if="error" class="mb-4" variant="error">{{ error }}</WidgetNotice>
            <slot />
            <div v-if="lastUpdated" class="mt-auto pt-4">
                <div class="text-muted-foreground border-t pt-3 text-center text-xs">
                    Latest data:
                    <time :datetime="lastUpdated.toISOString()">{{ formatLastUpdated(lastUpdated) }}</time>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
