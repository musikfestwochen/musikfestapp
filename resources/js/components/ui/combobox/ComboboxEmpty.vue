<script lang="ts" setup>
import type { ComboboxEmptyProps } from 'reka-ui';
import { ComboboxEmpty } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { cn } from '@/lib/utils';

const props = defineProps<ComboboxEmptyProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = reactiveOmit(props, 'class')
</script>

<template>
  <ComboboxEmpty :class="cn('py-6 text-center text-sm', props.class)" v-bind="delegatedProps">
    <slot />
  </ComboboxEmpty>
</template>
