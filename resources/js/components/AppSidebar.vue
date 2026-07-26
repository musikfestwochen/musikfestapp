<script lang="ts" setup>
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader } from '@/components/ui/sidebar';
import UserNav from '@/components/users/UserNav.vue';
import { usePermissions } from '@/composables/usePermissions';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const props = defineProps<{
    mainNavItems: NavItem[];
    footerNavItems: NavItem[];
}>();

const { can } = usePermissions();

const filteredMainNavItems = computed(() =>
    props.mainNavItems
        .map((item) => ({
            ...item,
            children: item.children?.filter((child) => !child.permission || can(child.permission)),
        }))
        .filter((item) => (!item.permission || can(item.permission)) && (!item.children || item.children.length > 0)),
);

const filteredFooterNavItems = props.footerNavItems.filter((item) => {
    if (item.permission) {
        return can(item.permission);
    }
    return true;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="self-center">
            <Link :href="route('home')">
                <AppLogo />
            </Link>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="filteredMainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="filteredFooterNavItems" />
            <UserNav />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
