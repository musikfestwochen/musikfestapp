<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import TableLoadingSkeleton from '@/components/ui/table-loading-skeleton/TableLoadingSkeleton.vue';
import { Organization } from '@/types';
import { Deferred, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';
import { organizationsColumns } from './columns';

const props = defineProps<{
    organizations?: Organization[];
}>();

const organizationsData = computed(() => props.organizations ?? []);
</script>

<template>
    <Deferred data="organizations">
        <template #fallback>
            <TableLoadingSkeleton :columns="organizationsColumns.length" />
        </template>

        <template #default>
            <DataTable :columns="organizationsColumns" :data="organizationsData" filter-column="name" search-placeholder="Search organizations...">
                <template #actions>
                    <Button as-child size="sm" variant="default">
                        <Link :href="route('admin.organizations.create')">
                            <Plus class="mr-1 h-4 w-4" />
                            Create Organization
                        </Link>
                    </Button>
                </template>
            </DataTable>
        </template>
    </Deferred>
</template>
