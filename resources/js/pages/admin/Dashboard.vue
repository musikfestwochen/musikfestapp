<script lang="ts" setup>
import PeoplecountAggregationControlsWidget from '@/components/admin/PeoplecountAggregationControlsWidget.vue';
import Layout from '@/layouts/admin/Layout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Admin Dashboard" />
    <Layout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h1 class="mb-4 text-2xl font-bold">Admin Dashboard</h1>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <PeoplecountAggregationControlsWidget />
            </div>
        </div>
    </Layout>
</template>
