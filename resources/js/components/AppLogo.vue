<script lang="ts" setup>
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useSidebar } from '@/components/ui/sidebar';

interface Props {
    class?: string;
}

defineProps<Props>();

const { state } = useSidebar();
</script>

<template>
    <div class="flex aspect-square items-center justify-center rounded-md bg-none text-sidebar-primary-foreground dark:bg-none dark:text-sidebar-primary-foreground" :class="state == 'collapsed' ? 'size-8' : 'size-32'">

        <AppLogoIcon class="size-32 fill-black dark:fill-sidebar-primary-foreground" />
    </div>
</template>
