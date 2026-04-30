<script lang="ts" setup>
import type { TagsInputInputProps } from 'reka-ui';
import { TagsInputInput, useForwardProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { cn } from '@/lib/utils';

const props = defineProps<TagsInputInputProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <TagsInputInput :class="cn('text-sm min-h-6 focus:outline-hidden flex-1 bg-transparent px-1', props.class)" v-bind="forwardedProps" />
</template>
