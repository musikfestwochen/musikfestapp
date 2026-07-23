<script setup lang="ts">
import { HoverCardTrigger, type HoverCardTriggerProps, useForwardProps } from 'radix-vue';

const props = defineProps<HoverCardTriggerProps>();
const forwardedProps = useForwardProps(props);
</script>

<template>
    <HoverCardTrigger v-bind="forwardedProps">
        <slot />
    </HoverCardTrigger>
</template>
