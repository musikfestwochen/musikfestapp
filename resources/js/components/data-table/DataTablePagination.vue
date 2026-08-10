<script lang="ts" setup generic="TData extends RowData">
import type { RowData, Table } from '@tanstack/vue-table';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-vue-next';
import { computed } from 'vue';
import type { DataTableFeatures } from './features';

const props = defineProps<{
    table: Table<DataTableFeatures, TData>;
}>();

const table = props.table;
const pageIndex = computed(() => table.atoms.pagination.get().pageIndex);
</script>

<template>
    <div class="flex items-center justify-end px-2 py-4">
        <div class="flex items-center space-x-6 lg:space-x-8">
            <div class="flex w-[100px] items-center justify-center text-sm font-medium">
                Page {{ pageIndex + 1 }} of
                {{ table.getPageCount() }}
            </div>
            <div class="flex items-center space-x-2">
                <Button :disabled="!table.getCanPreviousPage()" class="hidden h-8 w-8 p-0 lg:flex" variant="outline" @click="table.setPageIndex(0)">
                    <span class="sr-only">Go to first page</span>
                    <ChevronsLeft class="h-4 w-4" />
                </Button>
                <Button :disabled="!table.getCanPreviousPage()" class="h-8 w-8 p-0" variant="outline" @click="table.previousPage()">
                    <span class="sr-only">Go to previous page</span>
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <Button :disabled="!table.getCanNextPage()" class="h-8 w-8 p-0" variant="outline" @click="table.nextPage()">
                    <span class="sr-only">Go to next page</span>
                    <ChevronRight class="h-4 w-4" />
                </Button>
                <Button
                    :disabled="!table.getCanNextPage()"
                    class="hidden h-8 w-8 p-0 lg:flex"
                    variant="outline"
                    @click="table.setPageIndex(table.getPageCount() - 1)"
                >
                    <span class="sr-only">Go to last page</span>
                    <ChevronsRight class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
