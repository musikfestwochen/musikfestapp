<script lang="ts" setup>
defineProps<{
    columns: number;
    rows?: number;
}>();
</script>

<template>
    <div class="overflow-hidden rounded-md border">
        <table class="w-full animate-pulse">
            <thead>
                <tr>
                    <th v-for="i in columns" :key="'header-' + i" class="h-12 bg-muted p-4">
                        <div class="h-4 w-24 rounded bg-gray-300" />
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="i in rows || 5" :key="'row-' + i">
                    <td v-for="j in columns" :key="'cell-' + i + '-' + j" class="p-4">
                        <div class="h-4 w-full rounded bg-gray-200" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
