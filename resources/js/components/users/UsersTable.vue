<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import { usersColumns } from './columns';
import UserRoleFilters from './UserRoleFilters.vue';

const props = defineProps<{
    users: User[];
    organization?: Organization;
}>();

const columns = usersColumns(props.organization);
const { can } = usePermissions();
</script>

<template>
    <DataTable
        :columns="columns"
        :data="users"
        :row-href="
            (user) =>
                props.organization
                    ? can('orgmgmt.users.edit')
                        ? route('orgmgmt.users.edit', { organization: props.organization.slug, user: user.id })
                        : null
                    : can('admin.users.edit')
                      ? route('admin.users.edit', { user: user.id })
                      : null
        "
        filter-column="name"
        search-placeholder="Search by name or email..."
        title="Users"
        description="See all your users"
        :initial-sorting="[{ id: 'name', desc: false }]"
    >
        <template #filters="{ table }">
            <UserRoleFilters v-if="props.organization" :table="table" :users="users" />
        </template>
        <template #actions>
            <Button v-if="props.organization ? can('orgmgmt.users.create') : can('admin.users.create')" as-child size="sm" variant="default">
                <Link
                    :href="
                        props.organization ? route('orgmgmt.users.create', { organization: props.organization.slug }) : route('admin.users.create')
                    "
                >
                    <UserPlus class="mr-1 h-4 w-4" />
                    Create User
                </Link>
            </Button>
        </template>
    </DataTable>
</template>
