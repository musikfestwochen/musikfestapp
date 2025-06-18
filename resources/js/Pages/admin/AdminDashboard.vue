<script lang="ts" setup>
import AdminNavFooter from '@/components/admin/AdminNavFooter.vue';
import AdminNavMain from '@/components/admin/AdminNavMain.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Admin Dashboard" />
    <AppLayout>
        <AppSidebar>
            <template #nav-main>
                <AdminNavMain />
            </template>
            <template #nav-footer>
                <AdminNavFooter />
            </template>
        </AppSidebar>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h1 class="mb-4 text-2xl font-bold">Admin Dashboard</h1>
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
