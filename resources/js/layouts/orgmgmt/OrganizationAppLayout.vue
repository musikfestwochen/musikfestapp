<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { orgFooterNavItems, orgMainNavItems } from '@/nav/orgNavItems';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppSidebarLayout :mainNavItems="orgMainNavItems" :footerNavItems="orgFooterNavItems" :breadcrumbs="breadcrumbs">
        <slot />
    </AppSidebarLayout>
</template>
