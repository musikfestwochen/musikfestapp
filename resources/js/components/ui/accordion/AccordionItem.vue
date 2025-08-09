<script lang="ts" setup>
import type { AccordionItemProps } from 'reka-ui';
import { AccordionItem, useForwardProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { cn } from '@/lib/utils';

const props = defineProps<AccordionItemProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <AccordionItem
    :class="cn('border-b', props.class)"
    v-bind="forwardedProps"
  >
    <slot />
  </AccordionItem>
</template>
