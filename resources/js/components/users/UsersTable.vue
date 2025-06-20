<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { Organization, User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import { usersColumns } from './columns';

const props = defineProps<{
    users: User[];
    organization?: Organization;
}>();

const columns = usersColumns(props.organization);
</script>

<template>
    <DataTable :columns="columns" :data="users" filter-column="name" search-placeholder="Search users...">
        <template #actions>
            <Button as-child size="sm" variant="default">
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
