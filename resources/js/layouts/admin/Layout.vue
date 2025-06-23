<script lang="ts" setup>
import SidebarLayout from '@/layouts/app/SidebarLayout.vue';
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
    <SidebarLayout :breadcrumbs="breadcrumbs" :footer-nav-items="adminFooterNavItems" :main-nav-items="adminMainNavItems">
        <slot />
    </SidebarLayout>
</template>
