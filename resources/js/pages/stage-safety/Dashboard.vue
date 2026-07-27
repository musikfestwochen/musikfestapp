<script setup lang="ts">
import SensorHealthWidget from '@/components/SensorHealthWidget.vue';
import CurrentWindWidget from '@/components/stage-safety/CurrentWindWidget.vue';
import LqiHistoryWidget from '@/components/stage-safety/LqiHistoryWidget.vue';
import WindHistoryWidget from '@/components/stage-safety/WindHistoryWidget.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import type { BreadcrumbItem, Organization } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ organization: Organization }>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: `/${props.organization.slug}/dashboard`,
    },
    {
        title: 'Stage Safety',
        href: `/${props.organization.slug}/stage-safety`,
    },
];
</script>

<template>
    <Head title="Stage Safety Dashboard" />
    <Layout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h1 class="text-2xl font-bold">Stage Safety Dashboard</h1>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <CurrentWindWidget :organization="organization" />
                <SensorHealthWidget :organization="organization" :show-peoplecount="false" :show-stage-safety="true" />
                <WindHistoryWidget :organization="organization" />
                <LqiHistoryWidget :organization="organization" />
            </div>
        </div>
    </Layout>
</template>
