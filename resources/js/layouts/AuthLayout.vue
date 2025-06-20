<script lang="ts" setup>
import SplitLayout from '@/layouts/auth/SplitLayout.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <SplitLayout :description="description" :title="title">
        <slot />
    </SplitLayout>
</template>
