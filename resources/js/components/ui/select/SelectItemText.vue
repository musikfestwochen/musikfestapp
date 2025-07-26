<script lang="ts" setup>
import { SelectItemText, type SelectItemTextProps } from 'reka-ui';

const props = defineProps<SelectItemTextProps>()
</script>

<template>
  <SelectItemText v-bind="props">
    <slot />
  </SelectItemText>
</template>
