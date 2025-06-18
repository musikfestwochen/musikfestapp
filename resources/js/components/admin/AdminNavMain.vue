<template>
    <NavMain :items="adminNavItems" />
</template>

<script lang="ts" setup>
import { LayoutGrid, UnplugIcon } from 'lucide-vue-next';
import NavMain from '../NavMain.vue';

const adminNavItems = [
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
</script>
