<script lang="ts" setup>
import SidebarLayout from '@/layouts/app/SidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <SidebarLayout :breadcrumbs="breadcrumbs">
        <slot />
    </SidebarLayout>
</template>
