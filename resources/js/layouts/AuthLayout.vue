<script lang="ts" setup>
import AuthLayout from '@/layouts/auth/AuthSplitLayout.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AuthLayout :description="description" :title="title">
        <slot />
    </AuthLayout>
</template>
