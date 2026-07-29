<script lang="ts" setup>
import { Separator } from '@/components/ui/separator';

interface Props {
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 space-y-0.5">
            <h2 class="text-xl font-semibold tracking-tight">{{ title }}</h2>
            <p v-if="description" class="text-muted-foreground text-sm">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.default" class="shrink-0">
            <slot />
        </div>
    </div>

    <Separator class="my-6" />
</template>
