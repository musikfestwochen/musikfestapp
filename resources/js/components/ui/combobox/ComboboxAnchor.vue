<script lang="ts" setup>
import type { ComboboxAnchorProps } from 'reka-ui';
import { ComboboxAnchor, useForwardProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { cn } from '@/lib/utils';

const props = defineProps<ComboboxAnchorProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = reactiveOmit(props, 'class')

const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <ComboboxAnchor
    :class="cn('w-[200px]', props.class)"
    v-bind="forwarded"
  >
    <slot />
  </ComboboxAnchor>
</template>
