<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { Organization, PeoplecountAssignment } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { assignmentsColumns } from './columns';

const props = defineProps<{
    assignments: PeoplecountAssignment[];
    organization: Organization;
}>();

const columns = assignmentsColumns(props.organization);
</script>

<template>
    <DataTable :columns="columns" :data="assignments" filter-column="event" search-placeholder="Search assignments...">
        <template #actions>
            <Button as-child size="sm" variant="default">
                <Link :href="route('peoplecount.assignments.create', { organization: props.organization.slug })">
                    <Plus class="mr-1 h-4 w-4" />
                    Create Assignment
                </Link>
            </Button>
        </template>
    </DataTable>
</template>
