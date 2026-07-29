<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountAssignment } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { assignmentsColumns } from './columns';

const props = defineProps<{
    assignments: PeoplecountAssignment[];
    organization: Organization;
}>();

const columns = assignmentsColumns(props.organization);
const { can } = usePermissions();
</script>

<template>
    <DataTable
        :columns="columns"
        :data="assignments"
        :row-href="
            (assignment) =>
                can('peoplecount.assignments.edit')
                    ? route('peoplecount.assignments.edit', { organization: props.organization.slug, assignment: assignment.id })
                    : null
        "
        filter-column="event"
        search-placeholder="Search assignments..."
        title="Assignments"
        description="Manage sensor assignments to events and areas"
    >
        <template #actions>
            <Button v-if="can('peoplecount.assignments.create')" as-child size="sm" variant="default">
                <Link :href="route('peoplecount.assignments.create', { organization: props.organization.slug })">
                    <Plus class="mr-1 h-4 w-4" />
                    Create Assignment
                </Link>
            </Button>
        </template>
    </DataTable>
</template>
