<script lang="ts" setup>
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Building2Icon, FolderGit2Icon, LayoutGrid, Users2Icon } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        route: 'dashboard',
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Users',
        route: 'users.index',
        icon: Users2Icon,
    },
    {
        title: 'Organizations',
        route: 'organizations.index',
        icon: Building2Icon,
    },
    {
        title: 'Github Repo',
        url: 'https://github.com/musikfestwochen/musikfestapp',
        icon: FolderGit2Icon,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="self-center">
            <Link :href="route('dashboard')">
                <AppLogo />
            </Link>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
