<template>
    <NavFooter :items="adminFooterNavItems" />
</template>

<script lang="ts" setup>
import { Building2Icon, FolderGit2Icon, Users2Icon } from 'lucide-vue-next';
import NavFooter from '../NavFooter.vue';

const adminFooterNavItems = [
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
</script>
