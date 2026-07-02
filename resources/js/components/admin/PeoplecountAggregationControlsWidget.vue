<script lang="ts" setup>
import ConfirmActionButton from '@/components/ConfirmActionButton.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/composables/usePermissions';
import { RefreshCw, Trash2 } from 'lucide-vue-next';

const { can } = usePermissions();
</script>

<template>
    <Card v-if="can('admin.peoplecount_aggregations.update') || can('admin.peoplecount_aggregations.destroy')" class="h-full">
        <CardHeader>
            <CardTitle>Peoplecount Aggregations</CardTitle>
            <CardDescription>Update aggregated area counts outside the scheduler.</CardDescription>
        </CardHeader>
        <CardContent class="flex flex-wrap gap-2">
            <ConfirmActionButton
                v-if="can('admin.peoplecount_aggregations.update')"
                :href="route('admin.peoplecount-aggregations.update')"
                :icon="RefreshCw"
                confirm-label="Update aggregations"
                description="This runs the same aggregation as the scheduler. It will be skipped if another aggregation is already running."
                label="Update Aggregations"
                method="patch"
                title="Update peoplecount aggregations?"
                variant="outline"
            />
            <ConfirmActionButton
                v-if="can('admin.peoplecount_aggregations.destroy')"
                :href="route('admin.peoplecount-aggregations.destroy')"
                :icon="Trash2"
                confirm-label="Rebuild aggregations"
                description="This clears existing aggregated counts and immediately recalculates them. It will be skipped if another aggregation is already running."
                label="Rebuild Aggregations"
                method="delete"
                title="Rebuild peoplecount aggregations?"
            />
        </CardContent>
    </Card>
</template>
