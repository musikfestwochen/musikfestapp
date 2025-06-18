<script lang="ts" setup>
import AppSidebar from '@/components/AppSidebar.vue';
import OrgNavFooter from '@/components/orgmgmt/OrgNavFooter.vue';
import OrgNavMain from '@/components/orgmgmt/OrgNavMain.vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, Organization } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    organization: Organization;
}>();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: props.organization.name,
        href: `/${props.organization.slug}/dashboard`,
    },
    {
        title: 'Dashboard',
        href: `/${props.organization.slug}/dashboard`,
    },
]);
</script>

<template>
    <Head :title="`${props.organization.name} Dashboard`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AppSidebar>
            <template #nav-main>
                <OrgNavMain :organization="props.organization" />
            </template>
            <template #nav-footer>
                <OrgNavFooter />
            </template>
        </AppSidebar>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="mb-4">
                <h1 class="text-2xl font-bold">{{ props.organization.name }} Dashboard</h1>
            </div>
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <PlaceholderPattern />
            </div>
        </div>
    </AppLayout>
</template>
