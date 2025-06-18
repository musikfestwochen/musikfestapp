<script setup lang="ts">
import { type Table } from '@tanstack/vue-table';
import DataTableFilter from './DataTableFilter.vue';
import DataTableViewOptions from './DataTableViewOptions.vue';

defineProps<{
    table: Table<any>;
    filterColumn?: string;
    searchPlaceholder?: string;
    showFilter?: boolean;
}>();
</script>

<template>
    <div class="flex items-center justify-between py-4">
        <DataTableFilter v-if="filterColumn && showFilter !== false" :table="table" :filter-column="filterColumn" :placeholder="searchPlaceholder" />
        <div class="flex-1"></div>
        <div class="flex items-center gap-2">
            <!-- Allow custom actions to be inserted -->
            <slot name="actions"></slot>
            <DataTableViewOptions :table="table" />
        </div>
    </div>
</template>
