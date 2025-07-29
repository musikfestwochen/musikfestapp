<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import SingleResetForm from '@/components/peoplecount/resets/SingleResetForm.vue';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization, PeoplecountArea } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    organization: Organization;
    area: PeoplecountArea;
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
        title: props.area.name,
        href: route('peoplecount.areas.edit', { organization: props.organization.slug, area: props.area.id }),
    },
    {
        title: 'Create Manual Reset',
        href: route('peoplecount.areas.single-resets.create', {
            organization: props.organization.slug,
            area: props.area.id,
        }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Create Manual Reset" />

        <div class="px-4 py-6">
            <Heading :description="`Create a new manual reset for ${props.area.name} area`" title="Create Manual Reset" />

            <div class="mt-6">
                <SingleResetForm :area="props.area" :organization="props.organization" />
            </div>
        </div>
    </Layout>
</template>
