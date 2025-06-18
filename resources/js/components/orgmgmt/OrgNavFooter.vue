<template>
    <NavFooter :items="orgFooterNavItems" />
</template>

<script lang="ts" setup>
import { FolderGit2Icon } from 'lucide-vue-next';
import NavFooter from '../NavFooter.vue';

const orgFooterNavItems = [
    {
        title: 'Github Repo',
        url: 'https://github.com/musikfestwochen/musikfestapp',
        icon: FolderGit2Icon,
    },
    // Add more org-specific footer items here
];
</script>
