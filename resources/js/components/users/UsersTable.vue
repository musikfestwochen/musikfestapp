<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import TableLoadingSkeleton from '@/components/ui/table-loading-skeleton/TableLoadingSkeleton.vue';
import { User } from '@/types';
import { Deferred, Link } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import { computed } from 'vue';
import { usersColumns } from './columns';

const props = defineProps<{ users?: User[] }>();

const usersData = computed(() => props.users ?? []);
</script>

<template>
    <Deferred data="users">
        <template #fallback>
            <TableLoadingSkeleton :columns="usersColumns.length" />
        </template>

        <template #default>
            <DataTable :columns="usersColumns" :data="usersData" filter-column="name" search-placeholder="Search users...">
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
    </Deferred>
</template>
