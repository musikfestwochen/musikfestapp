<script lang="ts" setup>
import type { HTMLAttributes } from 'vue';
import { computed, ref, watch } from 'vue';
import { useFilter } from 'reka-ui';
import { cn } from '@/lib/utils';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxList
} from '@/components/ui/combobox';
import {
    TagsInput,
    TagsInputInput,
    TagsInputItem,
    TagsInputItemDelete,
    TagsInputItemText
} from '@/components/ui/tags-input';

export type TagsComboboxItem = { value: string; label: string }

const props = defineProps<{
  modelValue: string[]
  items: TagsComboboxItem[]
  placeholder?: string
  max?: number
  class?: HTMLAttributes['class']
  inputClass?: HTMLAttributes['class']
  listClass?: HTMLAttributes['class']
  emptyText?: string
  disabled?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string[]): void
}>()

const open = ref(false)
const searchTerm = ref('')

// local copy to be able to push/pop easily while still emitting updates
const localModel = ref<string[]>([...props.modelValue])

watch(
  () => props.modelValue,
  (nv) => {
    if (nv !== localModel.value) localModel.value = [...nv]
  }
)

watch(localModel, (nv) => emit('update:modelValue', nv), { deep: true })

const { contains } = useFilter({ sensitivity: 'base' })

const filteredItems = computed(() => {
  const options = props.items.filter((i) => !localModel.value.includes(i.label))
  return searchTerm.value
    ? options.filter((option) => contains(option.label, searchTerm.value))
    : options
})

function onSelect(ev: CustomEvent) {
  const val = (ev as any).detail?.value
  if (typeof val === 'string') {
    // prevent duplicates
    if (!localModel.value.includes(val)) {
      localModel.value.push(val)
    }
    searchTerm.value = ''
    // close popover if nothing left
    if (filteredItems.value.length === 0) {
      open.value = false
    }
  }
}

function removeTag(idx: number) {
  const copy = [...localModel.value]
  copy.splice(idx, 1)
  localModel.value = copy
}
</script>

<template>
  <Combobox v-model="localModel" v-model:open="open" :disabled="disabled" :ignore-filter="true">
    <ComboboxAnchor as-child>
      <TagsInput
        v-model="localModel"
        :class="cn('px-2 gap-2 w-80', props.class)"
        :disabled="disabled as any"
        :max="max"
      >
        <div class="flex gap-2 flex-wrap items-center">
          <TagsInputItem v-for="(item, i) in localModel" :key="item" :value="item">
            <TagsInputItemText />
            <TagsInputItemDelete @click.stop.prevent="removeTag(i)" />
          </TagsInputItem>
        </div>

        <ComboboxInput v-model="searchTerm" as-child>
          <TagsInputInput
            :class="cn('min-w-[200px] w-full p-0 border-none focus-visible:ring-0 h-auto', inputClass)"
            :placeholder="placeholder ?? 'Select...'"
            @keydown.enter.prevent
          />
        </ComboboxInput>
      </TagsInput>

      <ComboboxList :class="cn('w-(--reka-popper-anchor-width)', listClass)">
        <ComboboxEmpty>{{ emptyText ?? 'No results' }}</ComboboxEmpty>
        <ComboboxGroup>
          <ComboboxItem
            v-for="opt in filteredItems"
            :key="opt.value"
            :value="opt.label"
            @select.prevent="onSelect"
          >
            {{ opt.label }}
          </ComboboxItem>
        </ComboboxGroup>
      </ComboboxList>
    </ComboboxAnchor>
  </Combobox>
</template>
