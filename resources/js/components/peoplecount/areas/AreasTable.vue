<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountArea } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { areasColumns } from './columns';

const props = defineProps<{
    areas: PeoplecountArea[];
    organization: Organization;
}>();

const columns = areasColumns(props.organization);
const { can } = usePermissions();
</script>

<template>
    <DataTable
        :columns="columns"
        :data="areas"
        :row-href="
            (area) =>
                can('peoplecount.areas.edit') ? route('peoplecount.areas.edit', { organization: props.organization.slug, area: area.id }) : null
        "
        filter-column="name"
        search-placeholder="Search areas..."
        title="Areas"
        description="Manage areas within your events for people counting"
        :initial-sorting="[{ id: 'name', desc: false }]"
    >
        <template #actions>
            <Button v-if="can('peoplecount.areas.create')" as-child size="sm" variant="default">
                <Link :href="route('peoplecount.areas.create', { organization: props.organization.slug })">
                    <Plus class="mr-1 h-4 w-4" />
                    Create Area
                </Link>
            </Button>
        </template>
    </DataTable>
</template>
