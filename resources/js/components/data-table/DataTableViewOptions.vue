<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Table } from '@tanstack/vue-table';
import { SlidersHorizontal } from 'lucide-vue-next';
import { computed } from 'vue';

interface DataTableViewOptionsProps<TData> {
    table: Table<TData>;
}

const props = withDefaults(defineProps<DataTableViewOptionsProps<any> & { compact?: boolean }>(), {
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
