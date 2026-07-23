<script setup lang="ts">
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import type { Organization, StageSafetySensor } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { sensorColumns } from './columns';

const props = defineProps<{
    sensors: StageSafetySensor[];
    organization: Organization;
}>();

const { can } = usePermissions();
const columns = sensorColumns(props.organization);
</script>

<template>
    <DataTable
        :columns="columns"
        :data="sensors"
        :row-href="
            (sensor) =>
                can('stage-safety.sensors.edit')
                    ? route('stage-safety.sensors.edit', {
                          organization: organization.slug,
                          stageSafetySensor: sensor.id,
                      })
                    : null
        "
        filter-column="name"
        search-placeholder="Search Stage Safety sensors..."
    >
        <template #actions>
            <Button v-if="can('stage-safety.sensors.create')" as-child size="sm">
                <Link :href="route('stage-safety.sensors.create', { organization: organization.slug })">
                    <Plus class="mr-1 size-4" />
                    Create Sensor
                </Link>
            </Button>
        </template>
    </DataTable>
</template>
