<script lang="ts" setup>
import ActiveAreaCountsWidget from '@/components/peoplecount/ActiveAreaCountsWidget.vue';
import AreaCountHistoryWidget from '@/components/peoplecount/AreaCountHistoryWidget.vue';
import MostActiveSensorsWidget from '@/components/peoplecount/MostActiveSensorsWidget.vue';
import SensorHealthWidget from '@/components/SensorHealthWidget.vue';
import CurrentWindWidget from '@/components/stage-safety/CurrentWindWidget.vue';
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
const canViewPeoplecountHealth = computed(() => can('peoplecount.widgets.sensor_health'));
const canViewStageSafety = computed(() => can('stage-safety.monitoring.view'));
const canViewSensorHealth = computed(() => canViewPeoplecountHealth.value || canViewStageSafety.value);

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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 dark:[--card-foreground:0_0%_85%] dark:[--foreground:0_0%_90%]">
            <div class="mb-4">
                <h1 class="text-2xl font-bold">{{ props.organization.name }} Dashboard</h1>
            </div>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <ActiveAreaCountsWidget v-if="can('peoplecount.widgets.active_area_counts')" :organization="organization" />
                <MostActiveSensorsWidget v-if="can('peoplecount.widgets.most_active_sensors')" :organization="organization" />
                <CurrentWindWidget v-if="canViewStageSafety" :organization="organization" />
                <SensorHealthWidget
                    v-if="canViewSensorHealth"
                    :organization="organization"
                    :show-peoplecount="canViewPeoplecountHealth"
                    :show-stage-safety="canViewStageSafety"
                />
                <AreaCountHistoryWidget v-if="can('peoplecount.widgets.area_count_history')" :organization="organization" />
                <WindHistoryWidget v-if="canViewStageSafety" :organization="organization" />
            </div>
        </div>
    </Layout>
</template>
