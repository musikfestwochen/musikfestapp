<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountEvent } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { eventsColumns } from './columns';

const props = defineProps<{
    events: PeoplecountEvent[];
    organization: Organization;
}>();

const columns = eventsColumns(props.organization);
const { can } = usePermissions();
</script>

<template>
    <DataTable
        :columns="columns"
        :data="events"
        :row-href="
            (event) =>
                can('peoplecount.events.edit') ? route('peoplecount.events.edit', { organization: props.organization.slug, event: event.id }) : null
        "
        filter-column="name"
        search-placeholder="Search events..."
    >
        <template #actions>
            <Button v-if="can('peoplecount.events.create')" as-child size="sm" variant="default">
                <Link :href="route('peoplecount.events.create', { organization: props.organization.slug })">
                    <Plus class="mr-1 h-4 w-4" />
                    Create Event
                </Link>
            </Button>
        </template>
    </DataTable>
</template>
