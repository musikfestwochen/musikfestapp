<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { orgFooterNavItems, orgMainNavItems } from '@/nav/orgNavItems';
import type { BreadcrumbItemType, Organization } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

const { breadcrumbs } = defineProps<Props>();

// Get organization from the page props
const page = usePage();
const organization = computed(() => page.props.organization as Organization);

// Generate navigation items with the current organization
const navItems = computed(() => {
    if (!organization.value?.slug) return [];
    return orgMainNavItems(organization.value.slug);
});
</script>

<template>
    <AppSidebarLayout :mainNavItems="navItems" :footerNavItems="orgFooterNavItems" :breadcrumbs="breadcrumbs">
        <slot />
    </AppSidebarLayout>
</template>
