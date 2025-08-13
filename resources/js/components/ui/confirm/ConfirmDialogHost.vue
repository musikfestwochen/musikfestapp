<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle
} from '@/components/ui/alert-dialog';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { computed } from 'vue';

const dialog = useConfirmDialog()

// Compute plain-string values to ensure stable, reactive content for the slots
const title = computed(() => dialog.current.value?.title ?? '')
const description = computed(() => dialog.current.value?.description ?? '')
const confirmText = computed(() => dialog.current.value?.confirmText ?? 'Continue')
const cancelText = computed(() => dialog.current.value?.cancelText ?? 'Cancel')
const isDestructive = computed(() => dialog.current.value?.variant === 'destructive')
</script>

<template>
  <AlertDialog :open="dialog.isOpen" @update:open="(val) => { if (!val) dialog.onCancel() }">
    <AlertDialogContent :key="title + '|' + description">
      <AlertDialogHeader>
        <AlertDialogTitle>{{ title }}</AlertDialogTitle>
        <AlertDialogDescription>{{ description }}</AlertDialogDescription>
      </AlertDialogHeader>

      <AlertDialogFooter>
        <AlertDialogCancel @click="dialog.onCancel">
          {{ cancelText }}
        </AlertDialogCancel>

        <AlertDialogAction
          :class="isDestructive ? 'bg-destructive text-destructive-foreground hover:bg-destructive/90' : ''"
          @click="dialog.onConfirm"
        >
          {{ confirmText }}
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
