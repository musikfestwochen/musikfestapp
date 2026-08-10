<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { User } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    table: any;
    users: User[];
}>();

const filters = computed(() => [
    { value: undefined, label: 'All', count: props.users.length },
    {
        value: 'OrganizationAdmin',
        label: 'Organization admins',
        count: props.users.filter((user) => user.organization_roles?.some((role) => role.name === 'OrganizationAdmin')).length,
    },
    {
        value: 'PeopleCountViewer',
        label: 'People count',
        count: props.users.filter((user) => user.organization_roles?.some((role) => role.name === 'PeopleCountViewer')).length,
    },
    {
        value: 'StageSafetyViewer',
        label: 'Stage Safety',
        count: props.users.filter((user) => user.organization_roles?.some((role) => role.name === 'StageSafetyViewer')).length,
    },
    {
        value: 'none',
        label: 'No role',
        count: props.users.filter((user) => !user.organization_roles?.length).length,
    },
]);

const activeFilter = computed(() => props.table.getColumn('roles')?.getFilterValue() as string | undefined);
const mobileFilter = computed({
    get: () => activeFilter.value ?? 'all',
    set: (value: string) => setFilter(value === 'all' ? undefined : value),
});

function setFilter(value: string | undefined): void {
    props.table.getColumn('roles')?.setFilterValue(value);
}
</script>

<template>
    <div class="hidden flex-wrap gap-1.5 lg:flex" role="group" aria-label="Filter users by role">
        <Button
            v-for="filter in filters"
            :key="filter.value ?? 'all'"
            :aria-pressed="activeFilter === filter.value"
            :data-role-filter="filter.value ?? 'all'"
            :variant="activeFilter === filter.value ? 'default' : 'outline'"
            size="sm"
            type="button"
            @click="setFilter(filter.value)"
        >
            {{ filter.label }}
            <span :class="activeFilter === filter.value ? 'text-primary-foreground/75' : 'text-muted-foreground'">{{ filter.count }}</span>
        </Button>
    </div>

    <Select v-model="mobileFilter">
        <SelectTrigger class="w-full sm:w-72 lg:hidden" aria-label="Filter users by role">
            <SelectValue placeholder="Filter by role" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-for="filter in filters" :key="filter.value ?? 'all'" :value="filter.value ?? 'all'">
                {{ filter.label }} ({{ filter.count }})
            </SelectItem>
        </SelectContent>
    </Select>
</template>
