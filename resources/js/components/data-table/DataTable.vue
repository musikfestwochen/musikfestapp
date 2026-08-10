<script setup lang="ts" generic="TData extends RowData">
import type { ColumnDef, ColumnFiltersState, ColumnVisibilityState, RowData, SortingState } from '@tanstack/vue-table';
import { FlexRender, useTable } from '@tanstack/vue-table';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Heading from '@/components/Heading.vue';
import { valueUpdater } from '@/lib/utils';
import DataTablePagination from './DataTablePagination.vue';
import DataTableToolbar from './DataTableToolbar.vue';
import { dataTableFeatures, type DataTableFeatures } from './features';

const props = withDefaults(
    defineProps<{
        columns: ColumnDef<DataTableFeatures, TData>[];
        data: TData[];
        title?: string;
        description?: string;
        initialSorting?: SortingState;
        filterColumn?: string;
        searchPlaceholder?: string;
        rowHref?: (row: TData) => string | null | undefined;
    }>(),
    {
        title: undefined,
        description: undefined,
        initialSorting: () => [],
        filterColumn: undefined,
        searchPlaceholder: undefined,
        rowHref: undefined,
    },
);

const sorting = ref<SortingState>([...props.initialSorting]);
const columnFilters = ref<ColumnFiltersState>([]);
const columnVisibility = ref<ColumnVisibilityState>({});

const table = useTable({
    features: dataTableFeatures,
    get data() {
        return props.data;
    },
    get columns() {
        return props.columns;
    },
    onSortingChange: (updaterOrValue) => valueUpdater(updaterOrValue, sorting),
    onColumnFiltersChange: (updaterOrValue) => valueUpdater(updaterOrValue, columnFilters),
    onColumnVisibilityChange: (updaterOrValue) => valueUpdater(updaterOrValue, columnVisibility),
    state: {
        get sorting() {
            return sorting.value;
        },
        get columnFilters() {
            return columnFilters.value;
        },
        get columnVisibility() {
            return columnVisibility.value;
        },
    },
});

function openRow(row: TData, event: MouseEvent | KeyboardEvent): void {
    const target = event.target as HTMLElement;
    if (!props.rowHref || target.closest('a, button, input, select, textarea, [role="button"], [data-row-action]')) {
        return;
    }

    const href = props.rowHref(row);
    if (href) {
        router.visit(href);
    }
}
</script>

<template>
    <div>
        <Heading v-if="title" :description="description" :title="title">
            <div class="flex items-center gap-2">
                <div v-if="$slots.actions" class="lg:hidden">
                    <slot name="actions" :table="table"></slot>
                </div>
                <slot name="heading-actions"></slot>
            </div>
        </Heading>

        <DataTableToolbar :table="table" :filter-column="filterColumn" :search-placeholder="searchPlaceholder">
            <template #filters>
                <slot name="filters" :table="table"></slot>
            </template>
            <template #actions>
                <slot name="actions" :table="table"></slot>
            </template>
        </DataTableToolbar>

        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                        <TableHead v-for="header in headerGroup.headers" :key="header.id">
                            <FlexRender v-if="!header.isPlaceholder" :header="header" />
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="table.getRowModel().rows?.length">
                        <TableRow
                            v-for="row in table.getRowModel().rows"
                            :key="row.id"
                            :class="rowHref ? 'cursor-pointer' : undefined"
                            :tabindex="rowHref ? 0 : undefined"
                            @click="openRow(row.original, $event)"
                            @keydown.enter="openRow(row.original, $event)"
                            @keydown.space.prevent="openRow(row.original, $event)"
                        >
                            <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                                <FlexRender :cell="cell" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <template v-else>
                        <TableRow>
                            <TableCell :colspan="columns.length" class="h-24 text-center"> No results. </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>

        <DataTablePagination :table="table" />
    </div>
</template>
