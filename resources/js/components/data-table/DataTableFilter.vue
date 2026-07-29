<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { type Table } from '@tanstack/vue-table';

defineProps<{
    table: Table<any>;
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
