<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import AreaForm from '@/components/peoplecount/areas/AreaForm.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization, PeoplecountEvent } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    organization: Organization;
    events: PeoplecountEvent[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: route('organization.dashboard', { organization: props.organization.slug }),
    },
    {
        title: 'Areas',
        href: route('peoplecount.areas.index', { organization: props.organization.slug }),
    },
    {
        title: 'Create',
        href: route('peoplecount.areas.create', { organization: props.organization.slug }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Areas" />

        <div class="px-4 py-6">
            <Heading description="Create a new area within an event" title="Create Area" />
            <AreaForm :events="props.events" :organization="props.organization" />
        </div>
    </Layout>
</template>
