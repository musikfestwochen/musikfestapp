<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import { usersColumns } from './columns';

defineProps<{ users: User[] }>();
</script>

<template>
    <DataTable :columns="usersColumns" :data="users" filter-column="name" search-placeholder="Search users...">
        <template #actions>
            <Button as-child size="sm" variant="default">
                <Link :href="route('admin.users.create')">
                    <UserPlus class="mr-1 h-4 w-4" />
                    Create User
                </Link>
            </Button>
        </template>
    </DataTable>
</template>
