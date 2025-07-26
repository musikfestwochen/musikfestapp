<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import AreaForm from '@/components/peoplecount/areas/AreaForm.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization, PeoplecountArea, PeoplecountEvent } from '@/types';
import { formatLocalDateTime } from '@/utils/eventHelpers';
import { Head } from '@inertiajs/vue3';
import { Calendar, Users } from 'lucide-vue-next';

const props = defineProps<{
    area: PeoplecountArea;
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
        title: 'Edit',
        href: route('peoplecount.areas.edit', {
            organization: props.organization.slug,
            area: props.area.id,
        }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Areas" />

        <div class="px-4 py-6">
            <Heading title="Edit Area" />
            <AreaForm :area="props.area" :events="props.events" :organization="props.organization" />

            <!-- Event Information -->
            <div v-if="area.event" class="mt-8">
                <Heading title="Event Details" />
                <div class="mt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-4 w-4" />
                                {{ area.event.name }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2 text-sm text-muted-foreground">
                                <p><strong>Start:</strong> {{ formatLocalDateTime(area.event.starts_at) }}</p>
                                <p><strong>End:</strong> {{ formatLocalDateTime(area.event.ends_at) }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Assignments Information (if available) -->
            <div v-if="area.assignments && area.assignments.length > 0" class="mt-8">
                <Heading title="Assignments" />
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="assignment in area.assignments" :key="assignment.id">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Users class="h-4 w-4" />
                                {{ assignment.sensor?.vendor }} {{ assignment.sensor?.model }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <Badge v-if="assignment.direction_flipped" variant="destructive">direction flipped</Badge>

                                <div class="text-xs text-muted-foreground">
                                    <p><strong>Active from:</strong> {{ formatLocalDateTime(assignment.active_from) }}</p>
                                    <p><strong>Active to:</strong> {{ formatLocalDateTime(assignment.active_to) }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- No Assignments Message -->
            <div v-else class="mt-8">
                <Heading title="Assignments" />
                <div class="mt-4">
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-center text-muted-foreground">No assignments found for this area.</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </Layout>
</template>
