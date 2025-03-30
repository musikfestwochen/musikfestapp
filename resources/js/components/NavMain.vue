<script lang="ts" setup>
import { SidebarGroup, SidebarMenu, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import SidebarMenuAutoInternalExternalButton from '@/components/SidebarMenuAutoInternalExternalButton.vue';

defineProps<{
    items: NavItem[];
}>();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuAutoInternalExternalButton :item="item" />
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
