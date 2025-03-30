<script lang="ts" setup>
import Icon from '@/components/Icon.vue';
import { SidebarMenuButton } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    item: NavItem;
}>();
</script>

<template>
    <SidebarMenuButton v-if="item.route" :is-active="route().current() == item.route" as-child>
        <Link :href="route(item.route)">
            <component :is="item.icon" />
            <span>{{ item.title }}</span>
        </Link>
    </SidebarMenuButton>
    <SidebarMenuButton v-else-if="item.url" as-child>
        <a :href="item.url" target="_blank">
            <component :is="item.icon" />
            <span class="flex space-x-2">
                <span>{{ item.title }}</span>
                <Icon name="ExternalLinkIcon" stroke-width="1"
            /></span>
        </a>
    </SidebarMenuButton>
</template>
