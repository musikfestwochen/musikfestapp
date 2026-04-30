<script lang="ts" setup>
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { ToastAction, type ToastActionProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<ToastActionProps & { class?: HTMLAttributes['class'] }>();

const delegatedProps = reactiveOmit(props, 'class');
</script>

<template>
    <ToastAction
        :class="cn('inline-flex h-8 shrink-0 items-center justify-center rounded-md border bg-transparent px-3 text-sm font-medium ring-offset-background transition-colors hover:bg-secondary focus:outline-hidden focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 group-[.destructive]:border-muted/40 hover:group-[.destructive]:border-destructive/30 hover:group-[.destructive]:bg-destructive hover:group-[.destructive]:text-destructive-foreground focus:group-[.destructive]:ring-destructive', props.class)"
        v-bind="delegatedProps">
        <slot />
    </ToastAction>
</template>
