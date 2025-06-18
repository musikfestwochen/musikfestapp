<script lang="ts" setup>
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Building2Icon, FolderGit2Icon, LayoutGrid, UnplugIcon, Users2Icon } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

// Import the usePermissions composable
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        route: 'admin.dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Organization Selection',
        route: 'organization-selection.index',
        icon: UnplugIcon,
    },
];

const allFooterNavItems: NavItem[] = [
    {
        title: 'Users',
        route: 'users.index',
        icon: Users2Icon,
        permission: 'users.index',
    },
    {
        title: 'Organizations',
        route: 'organizations.index',
        icon: Building2Icon,
        permission: 'organizations.index',
    },
    {
        title: 'Github Repo',
        url: 'https://github.com/musikfestwochen/musikfestapp',
        icon: FolderGit2Icon,
    },
];

// Filter items based on permissions
const footerNavItems = allFooterNavItems.filter((item) => {
    // If the item has a permission requirement, check if the user has that permission
    if (item.permission) {
        return can(item.permission);
    }
    // If no permission is required, always show the item
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
            <slot name="nav-main">
                <NavMain :items="mainNavItems" />
            </slot>
        </SidebarContent>

        <SidebarFooter>
            <slot name="nav-footer">
                <NavFooter :items="footerNavItems" />
            </slot>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
