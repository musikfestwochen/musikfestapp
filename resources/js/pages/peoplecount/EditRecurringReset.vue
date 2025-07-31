<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import RecurringResetForm from '@/components/peoplecount/resets/RecurringResetForm.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { type BreadcrumbItem, Organization, PeoplecountArea, PeoplecountAreaRecurringReset } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    organization: Organization;
    area: PeoplecountArea;
    recurringReset: PeoplecountAreaRecurringReset;
    timezones: Array<{ value: string; label: string }>;
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
        title: 'Edit Recurring Reset',
        href: route('peoplecount.areas.recurring-resets.edit', {
            organization: props.organization.slug,
            area: props.area.id,
            recurring_reset: props.recurringReset.id,
        }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Edit Recurring Reset" />

        <div class="px-4 py-6">
            <Heading :description="`Update the recurring reset schedule for ${props.area.name} area`" title="Edit Recurring Reset" />

            <div class="mt-6">
                <!-- Form Card -->
                <Card class="mx-auto max-w-4xl">
                    <CardHeader>
                        <CardTitle>Edit Recurring Reset Schedule</CardTitle>
                        <CardDescription>
                            Modify the recurring reset schedule that automatically resets the area's occupancy count at specified intervals. Changes
                            will apply to past and future occurrences.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <RecurringResetForm
                            :area="props.area"
                            :organization="props.organization"
                            :recurring-reset="props.recurringReset"
                            :timezones="props.timezones"
                        />
                    </CardContent>
                </Card>
            </div>
        </div>
    </Layout>
</template>
