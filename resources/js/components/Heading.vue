<script lang="ts" setup>
import { Separator } from '@/components/ui/separator';

interface Props {
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="mb-8 flex flex-col items-start justify-between max-lg:space-y-4 lg:flex-row">
        <div class="space-y-0.5">
            <h2 class="text-xl font-semibold tracking-tight">{{ title }}</h2>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div>
            <slot />
        </div>
    </div>

    <Separator class="my-6" />
</template>
