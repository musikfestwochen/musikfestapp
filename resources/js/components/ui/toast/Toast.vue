<script lang="ts" setup>
import { reactiveOmit } from '@vueuse/core';
import { ToastRoot, type ToastRootEmits, useForwardPropsEmits } from 'reka-ui';
import { cn } from '@/lib/utils';
import { type ToastProps, toastVariants } from '.';

const props = defineProps<ToastProps>();

const emits = defineEmits<ToastRootEmits>();

const delegatedProps = reactiveOmit(props, 'class');

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <ToastRoot
        :class="cn(toastVariants({ variant }), props.class)"
        v-bind="forwarded"
        @update:open="onOpenChange"
    >
        <slot />
    </ToastRoot>
</template>
