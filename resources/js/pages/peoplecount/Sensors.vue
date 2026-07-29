<script lang="ts" setup>
import SensorsTable from '@/components/peoplecount/sensors/SensorsTable.vue';
import { Button } from '@/components/ui/button';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { type BreadcrumbItem, Organization, PeoplecountSensor } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    sensors: PeoplecountSensor[];
    status?: string;
    organization: Organization;
    showArchived: boolean;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: `/${props.organization.slug}/dashboard`,
    },
    {
        title: 'People Counting',
        href: `/${props.organization.slug}/peoplecount`,
    },
    {
        title: 'Sensors',
        href: route('peoplecount.sensors.index', { organization: props.organization.slug }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Sensors" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="px-4 py-6">
            <SensorsTable :organization="props.organization" :sensors="sensors">
                <template #heading-actions>
                    <Button as-child size="sm" variant="outline">
                        <Link :href="route('peoplecount.sensors.index', { organization: props.organization.slug, archived: !props.showArchived })">
                            {{ props.showArchived ? 'Show Active' : 'Show Archived' }}
                        </Link>
                    </Button>
                </template>
            </SensorsTable>
        </div>
    </Layout>
</template>
