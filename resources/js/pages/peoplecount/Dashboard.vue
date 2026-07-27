<script setup lang="ts">
import SensorHealthWidget from '@/components/SensorHealthWidget.vue';
import ActiveAreaCountsWidget from '@/components/peoplecount/ActiveAreaCountsWidget.vue';
import AreaCountHistoryWidget from '@/components/peoplecount/AreaCountHistoryWidget.vue';
import MostActiveSensorsWidget from '@/components/peoplecount/MostActiveSensorsWidget.vue';
import { usePermissions } from '@/composables/usePermissions';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import type { BreadcrumbItem, Organization } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ organization: Organization }>();
const { can } = usePermissions();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: `/${props.organization.slug}/dashboard`,
    },
    {
        title: 'Peoplecount',
        href: `/${props.organization.slug}/peoplecount`,
    },
];
</script>

<template>
    <Head title="Peoplecount Dashboard" />
    <Layout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h1 class="text-2xl font-bold">Peoplecount Dashboard</h1>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <ActiveAreaCountsWidget v-if="can('peoplecount.widgets.active_area_counts')" :organization="organization" />
                <MostActiveSensorsWidget v-if="can('peoplecount.widgets.most_active_sensors')" :organization="organization" />
                <SensorHealthWidget
                    v-if="can('peoplecount.widgets.sensor_health')"
                    :organization="organization"
                    :show-peoplecount="true"
                    :show-stage-safety="false"
                />
                <AreaCountHistoryWidget v-if="can('peoplecount.widgets.area_count_history')" :organization="organization" />
            </div>
        </div>
    </Layout>
</template>
