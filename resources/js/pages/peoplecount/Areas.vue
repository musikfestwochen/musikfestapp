<script lang="ts" setup>
import AreasTable from '@/components/peoplecount/areas/AreasTable.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { type BreadcrumbItem, Organization, PeoplecountArea } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    areas: PeoplecountArea[];
    status?: string;
    organization: Organization;
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
        title: 'Areas',
        href: route('peoplecount.areas.index', { organization: props.organization.slug }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Areas" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="px-4 py-6">
            <AreasTable :areas="areas" :organization="props.organization" />
        </div>
    </Layout>
</template>
