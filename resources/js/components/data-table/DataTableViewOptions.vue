<script setup lang="ts" generic="TData extends RowData">
import type { RowData, Table } from '@tanstack/vue-table';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SlidersHorizontal } from 'lucide-vue-next';
import { computed } from 'vue';
import type { DataTableFeatures } from './features';

interface DataTableViewOptionsProps<TData extends RowData> {
    table: Table<DataTableFeatures, TData>;
}

const props = withDefaults(defineProps<DataTableViewOptionsProps<TData> & { compact?: boolean }>(), {
    compact: false,
});

const columns = computed(() => props.table.getAllColumns().filter((column) => typeof column.accessorFn !== 'undefined' && column.getCanHide()));
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" :class="compact ? 'size-10 px-0' : 'ml-auto h-8'">
                <SlidersHorizontal :class="compact ? 'h-4 w-4' : 'mr-2 h-4 w-4'" />
                <span :class="compact ? 'sr-only' : undefined">View</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-[150px]">
            <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuCheckboxItem
                v-for="column in columns"
                :key="column.id"
                class="capitalize"
                :checked="column.getIsVisible()"
                @update:checked="(value: boolean) => column.toggleVisibility(!!value)"
            >
                {{ column.id }}
            </DropdownMenuCheckboxItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
