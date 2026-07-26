<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    variant: 'error' | 'warning';
}>();

const role = computed(() => (props.variant === 'error' ? 'alert' : 'status'));
const variantClass = computed(() =>
    props.variant === 'error'
        ? 'bg-rose-50/70 text-rose-700 dark:bg-rose-950/20 dark:text-rose-300/80'
        : 'bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400',
);
</script>

<template>
    <div :role="role" :class="variantClass" class="rounded-md p-2 text-center text-sm">
        <slot />
    </div>
</template>
