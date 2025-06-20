<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MoreHorizontal } from 'lucide-vue-next';

defineProps<{
    row: any;
    actions: {
        label: string;
        icon?: any;
        onClick: (row: any) => void;
        permission?: string;
    }[];
}>();

defineEmits<{
    (e: 'action', action: string, row: any): void;
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" class="h-8 w-8 p-0" aria-label="Open menu">
                <span class="sr-only">Open menu</span>
                <MoreHorizontal class="h-4 w-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuLabel>Actions</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem v-for="action in actions" :key="action.label" @click="action.onClick(row)">
                <component :is="action.icon" v-if="action.icon" class="mr-2 h-4 w-4" />
                {{ action.label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
