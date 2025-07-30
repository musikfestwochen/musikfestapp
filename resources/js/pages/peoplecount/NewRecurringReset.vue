<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import RecurringResetForm from '@/components/peoplecount/resets/RecurringResetForm.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
        title: 'Create Recurring Reset',
        href: route('peoplecount.areas.recurring-resets.create', {
            organization: props.organization.slug,
            area: props.area.id,
        }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Create Recurring Reset" />

        <div class="px-4 py-6">
            <Heading :description="`Create a new recurring reset schedule for ${props.area.name} area`" title="Create Recurring Reset" />

            <div class="mt-6">
                <!-- Form Card -->
                <Card class="mx-auto max-w-4xl">
                    <CardHeader>
                        <CardTitle>Recurring Reset Schedule</CardTitle>
                        <CardDescription>
                            Set up a recurring reset schedule that will automatically reset the area's occupancy count at specified intervals.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <RecurringResetForm :area="props.area" :organization="props.organization" />
                    </CardContent>
                </Card>
            </div>
        </div>
    </Layout>
</template>
