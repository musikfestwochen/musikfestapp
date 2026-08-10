<script setup lang="ts" generic="TData extends RowData">
import type { RowData, Table } from '@tanstack/vue-table';
import { Input } from '@/components/ui/input';
import type { DataTableFeatures } from './features';

defineProps<{
    table: Table<DataTableFeatures, TData>;
    filterColumn: string;
    placeholder?: string;
}>();
</script>

<template>
    <div class="w-full sm:w-72">
        <Input
            :placeholder="placeholder || `Filter ${filterColumn}...`"
            :value="table.getColumn(filterColumn)?.getFilterValue() as string"
            @input="(e: Event) => table.getColumn(filterColumn)?.setFilterValue((e.target as HTMLInputElement).value)"
            class="w-full"
        />
    </div>
</template>
