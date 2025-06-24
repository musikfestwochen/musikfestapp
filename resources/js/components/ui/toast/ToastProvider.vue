<script lang="ts" setup>
import { ToastProvider, type ToastProviderProps } from 'reka-ui';

const props = defineProps<ToastProviderProps>();
</script>

<template>
    <ToastProvider v-bind="props">
        <slot />
    </ToastProvider>
</template>
