<script lang="ts" setup>
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { ToastTitle, type ToastTitleProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<ToastTitleProps & { class?: HTMLAttributes['class'] }>();

const delegatedProps = reactiveOmit(props, 'class');
</script>

<template>
    <ToastTitle :class="cn('text-sm font-semibold', props.class)" v-bind="delegatedProps">
        <slot />
    </ToastTitle>
</template>
