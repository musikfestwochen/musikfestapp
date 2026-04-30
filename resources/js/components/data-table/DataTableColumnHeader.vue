<script lang="ts" setup>
import type { Column } from '@tanstack/vue-table';
// import is not needed as cn is not used in this component
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import { ArrowDown, ArrowUp, ArrowUpDown, EyeOff } from 'lucide-vue-next';

interface DataTableColumnHeaderProps<TData, TValue> {
    column: Column<TData, TValue>;
    title: string;
}

defineProps<DataTableColumnHeaderProps<any, any>>();
</script>

<template>
    <div v-if="column.getCanSort()" class="flex items-center space-x-2">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button class="data-[state=open]:bg-accent -ml-3 h-8" size="sm" variant="ghost">
                    <span>{{ title }}</span>
                    <ArrowDown v-if="column.getIsSorted() === 'desc'" class="ml-2 h-4 w-4" />
                    <ArrowUp v-else-if="column.getIsSorted() === 'asc'" class="ml-2 h-4 w-4" />
                    <ArrowUpDown v-else class="ml-2 h-4 w-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuItem @click="column.toggleSorting(false)">
                    <ArrowUp class="text-muted-foreground/70 mr-2 h-3.5 w-3.5" />
                    Asc
                </DropdownMenuItem>
                <DropdownMenuItem @click="column.toggleSorting(true)">
                    <ArrowDown class="text-muted-foreground/70 mr-2 h-3.5 w-3.5" />
                    Desc
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="column.toggleVisibility(false)">
                    <EyeOff class="text-muted-foreground/70 mr-2 h-3.5 w-3.5" />
                    Hide
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>

    <div v-else>
        {{ title }}
    </div>
</template>
