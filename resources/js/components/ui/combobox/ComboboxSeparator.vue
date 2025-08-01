<script lang="ts" setup>
import type { ComboboxSeparatorProps } from 'reka-ui';
import { ComboboxSeparator } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { cn } from '@/lib/utils';

const props = defineProps<ComboboxSeparatorProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = reactiveOmit(props, 'class')
</script>

<template>
  <ComboboxSeparator
    :class="cn('-mx-1 h-px bg-border', props.class)"
    v-bind="delegatedProps"
  >
    <slot />
  </ComboboxSeparator>
</template>
