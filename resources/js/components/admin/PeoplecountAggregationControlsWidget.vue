<script lang="ts" setup>
import ConfirmActionButton from '@/components/ConfirmActionButton.vue';
import WidgetShell from '@/components/widgets/WidgetShell.vue';
import { usePermissions } from '@/composables/usePermissions';
import { Database, RefreshCw, Trash2 } from 'lucide-vue-next';

const { can } = usePermissions();
</script>

<template>
    <WidgetShell
        v-if="can('admin.peoplecount_aggregations.update') || can('admin.peoplecount_aggregations.destroy')"
        title="Peoplecount Aggregations"
        subtitle="Update aggregated area counts outside the scheduler."
    >
        <template #icon><Database /></template>

        <div class="flex flex-wrap gap-2">
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
        </div>
    </WidgetShell>
</template>
