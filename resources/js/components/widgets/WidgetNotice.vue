<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    variant: 'error' | 'warning';
}>();

const role = computed(() => (props.variant === 'error' ? 'alert' : 'status'));
const variantClass = computed(() =>
    props.variant === 'error'
        ? 'bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-400'
        : 'bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400',
);
</script>

<template>
    <div :role="role" :class="variantClass" class="rounded-md p-2 text-center text-sm">
        <slot />
    </div>
</template>
