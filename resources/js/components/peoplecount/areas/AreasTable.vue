<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { Organization, PeoplecountArea } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { areasColumns } from './columns';

const props = defineProps<{
    areas: PeoplecountArea[];
    organization: Organization;
}>();

const columns = areasColumns(props.organization);
</script>

<template>
    <DataTable :columns="columns" :data="areas" filter-column="name" search-placeholder="Search areas...">
        <template #actions>
            <Button as-child size="sm" variant="default">
                <Link :href="route('peoplecount.areas.create', { organization: props.organization.slug })">
                    <Plus class="mr-1 h-4 w-4" />
                    Create Area
                </Link>
            </Button>
        </template>
    </DataTable>
</template>
