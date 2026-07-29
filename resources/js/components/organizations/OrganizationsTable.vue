<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { organizationsColumns } from './columns';

defineProps<{
    organizations: Organization[];
}>();

const { can } = usePermissions();
</script>

<template>
    <DataTable
        :columns="organizationsColumns"
        :data="organizations"
        :row-href="(organization) => (can('admin.organizations.edit') ? route('admin.organizations.edit', { id: organization.id }) : null)"
        filter-column="name"
        search-placeholder="Search organizations..."
        title="Organizations"
        description="See all your organizations"
    >
        <template #actions>
            <Button v-if="can('admin.organizations.create')" as-child size="sm" variant="default">
                <Link :href="route('admin.organizations.create')">
                    <Plus class="mr-1 h-4 w-4" />
                    Create Organization
                </Link>
            </Button>
        </template>
    </DataTable>
</template>
