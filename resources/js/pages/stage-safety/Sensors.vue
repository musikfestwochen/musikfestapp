<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import SensorsTable from '@/components/stage-safety/sensors/SensorsTable.vue';
import { Button } from '@/components/ui/button';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import type { BreadcrumbItem, Organization, StageSafetySensor } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    sensors: StageSafetySensor[];
    organization: Organization;
    showArchived: boolean;
    status?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: route('organization.dashboard', { organization: props.organization.slug }),
    },
    {
        title: 'Stage Safety Sensors',
        href: route('stage-safety.sensors.index', { organization: props.organization.slug }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbs">
        <Head title="Stage Safety Sensors" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600 dark:text-green-400">
            {{ status }}
        </div>

        <div class="px-4 py-6">
            <Heading description="Manage safety sensors, installation details, and API tokens" title="Stage Safety Sensors">
                <Button as-child size="sm" variant="outline">
                    <Link
                        :href="
                            route('stage-safety.sensors.index', {
                                organization: organization.slug,
                                archived: !showArchived,
                            })
                        "
                    >
                        {{ showArchived ? 'Show Active' : 'Show Archived' }}
                    </Link>
                </Button>
            </Heading>

            <div class="mt-4">
                <SensorsTable :organization="organization" :sensors="sensors" />
            </div>
        </div>
    </Layout>
</template>
