<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { adminFooterNavItems, adminMainNavItems } from '@/nav/adminNavItems';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppSidebarLayout :mainNavItems="adminMainNavItems" :footerNavItems="adminFooterNavItems" :breadcrumbs="breadcrumbs">
        <slot />
    </AppSidebarLayout>
</template>
