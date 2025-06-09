<script lang="ts" setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, Organization } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';

import { computed } from 'vue';

const props = defineProps<{
    organization?: Organization;
}>();

const breadcrumbs = computed((): BreadcrumbItem[] => {
    if (props.organization) {
        return [
            {
                title: props.organization.name,
                href: `/${props.organization.slug}/dashboard`,
            },
            {
                title: 'Dashboard',
                href: `/${props.organization.slug}/dashboard`,
            },
        ];
    }

    return [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
    ];
});
</script>

<template>
    <Head :title="organization ? `${organization.name} Dashboard` : 'Dashboard'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div v-if="organization" class="mb-4">
                <h1 class="text-2xl font-bold">{{ organization.name }} Dashboard</h1>
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
