<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountSensor } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { sensorsColumns } from './columns';

const props = defineProps<{
    sensors: PeoplecountSensor[];
    organization: Organization;
}>();

const columns = sensorsColumns(props.organization);
const { can } = usePermissions();
</script>

<template>
    <DataTable
        :columns="columns"
        :data="sensors"
        :row-href="
            (sensor) =>
                can('peoplecount.sensors.edit')
                    ? route('peoplecount.sensors.edit', { organization: props.organization.slug, sensor: sensor.id })
                    : null
        "
        filter-column="sensors"
        search-placeholder="Search sensors..."
    >
        <template #actions>
            <Button v-if="can('peoplecount.sensors.create')" as-child size="sm" variant="default">
                <Link :href="route('peoplecount.sensors.create', { organization: props.organization.slug })">
                    <Plus class="mr-1 h-4 w-4" />
                    Create Sensor
                </Link>
            </Button>
        </template>
    </DataTable>
</template>
