<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { Organization } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { organizationsColumns } from './columns';

defineProps<{
    organizations: Organization[];
}>();
</script>

<template>
    <DataTable :columns="organizationsColumns" :data="organizations" filter-column="name" search-placeholder="Search organizations...">
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
