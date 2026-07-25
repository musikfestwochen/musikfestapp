<script lang="ts" setup>
import ActiveAreaCountsWidget from '@/components/peoplecount/ActiveAreaCountsWidget.vue';
import AreaCountHistoryWidget from '@/components/peoplecount/AreaCountHistoryWidget.vue';
import MostActiveSensorsWidget from '@/components/peoplecount/MostActiveSensorsWidget.vue';
import SensorHealthStatusWidget from '@/components/peoplecount/SensorHealthStatusWidget.vue';
import CurrentWindWidget from '@/components/stage-safety/CurrentWindWidget.vue';
import SensorHealthWidget from '@/components/stage-safety/SensorHealthWidget.vue';
import WindHistoryWidget from '@/components/stage-safety/WindHistoryWidget.vue';
import { usePermissions } from '@/composables/usePermissions';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { type BreadcrumbItem, Organization } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    organization: Organization;
}>();

const { can } = usePermissions();
const canViewPeoplecountWidgets = computed(() =>
    [
        'peoplecount.widgets.active_area_counts',
        'peoplecount.widgets.sensor_health',
        'peoplecount.widgets.most_active_sensors',
        'peoplecount.widgets.area_count_history',
    ].some((permission) => can(permission)),
);

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
            <section v-if="can('stage-safety.monitoring.view')" class="space-y-4">
                <h2 class="text-lg font-semibold">Stage Safety</h2>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <CurrentWindWidget :organization="organization" />
                    <SensorHealthWidget :organization="organization" />
                    <WindHistoryWidget :organization="organization" />
                </div>
            </section>

            <section v-if="canViewPeoplecountWidgets" class="space-y-4">
                <h2 class="text-lg font-semibold">People Count</h2>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <ActiveAreaCountsWidget v-if="can('peoplecount.widgets.active_area_counts')" :organization="organization" />
                    <SensorHealthStatusWidget v-if="can('peoplecount.widgets.sensor_health')" :organization="organization" />
                    <MostActiveSensorsWidget v-if="can('peoplecount.widgets.most_active_sensors')" :organization="organization" />
                    <AreaCountHistoryWidget v-if="can('peoplecount.widgets.area_count_history')" :organization="organization" />
                </div>
            </section>
        </div>
    </Layout>
</template>
