<script lang="ts" setup>
import ActiveAreaCountsWidget from '@/components/peoplecount/ActiveAreaCountsWidget.vue';
import AreaCountHistoryWidget from '@/components/peoplecount/AreaCountHistoryWidget.vue';
import MostActiveSensorsWidget from '@/components/peoplecount/MostActiveSensorsWidget.vue';
import SensorHealthStatusWidget from '@/components/peoplecount/SensorHealthStatusWidget.vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { usePermissions } from '@/composables/usePermissions';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { type BreadcrumbItem, Organization } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    organization: Organization;
}>();

const { can } = usePermissions();

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
    <Layout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="mb-4">
                <h1 class="text-2xl font-bold">{{ props.organization.name }} Dashboard</h1>
            </div>
            <div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-3">
                <ActiveAreaCountsWidget v-if="can('peoplecount.widgets.active_area_counts')" :organization="organization" />
                <SensorHealthStatusWidget v-if="can('peoplecount.widgets.sensor_health')" :organization="organization" />
                <MostActiveSensorsWidget v-if="can('peoplecount.widgets.most_active_sensors')" :organization="organization" />
                <AreaCountHistoryWidget v-if="can('peoplecount.widgets.area_count_history')" :organization="organization" />
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-screen flex-1 rounded-xl border md:min-h-min">
                <PlaceholderPattern />
            </div>
        </div>
    </Layout>
</template>
