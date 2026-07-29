<script setup lang="ts">
import { type Table } from '@tanstack/vue-table';
import DataTableFilter from './DataTableFilter.vue';
import DataTableViewOptions from './DataTableViewOptions.vue';

withDefaults(
    defineProps<{
        table: Table<any>;
        filterColumn?: string;
        searchPlaceholder?: string;
        showFilter?: boolean;
    }>(),
    {
        filterColumn: undefined,
        searchPlaceholder: undefined,
        showFilter: true,
    },
);
</script>

<template>
    <div class="flex flex-col gap-3 py-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex min-w-0 flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-center">
            <DataTableFilter
                v-if="filterColumn && showFilter !== false"
                :table="table"
                :filter-column="filterColumn"
                :placeholder="searchPlaceholder"
            />
            <div class="flex min-w-0 items-center gap-2">
                <div class="min-w-0 flex-1">
                    <slot name="filters"></slot>
                </div>
                <div class="lg:hidden">
                    <DataTableViewOptions :table="table" compact />
                </div>
            </div>
        </div>
        <div class="hidden items-center gap-2 lg:flex">
            <!-- Allow custom actions to be inserted -->
            <slot name="actions"></slot>
            <DataTableViewOptions :table="table" />
        </div>
    </div>
</template>
