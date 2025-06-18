<template>
    <NavMain :items="orgNavItems" />
</template>

<script lang="ts" setup>
import { Organization } from '@/types';
import { LayoutGrid } from 'lucide-vue-next';
import NavMain from '../NavMain.vue';

const props = defineProps<{ organization: Organization }>();

const orgNavItems = [
    {
        title: 'Dashboard',
        route: 'organization.dashboard',
        params: { organization: props.organization.slug },
        icon: LayoutGrid,
    },
    // Add more org-specific nav items here
];
</script>
