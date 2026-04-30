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
    <div
        :class="state == 'collapsed' ? 'size-8' : 'size-32'"
        class="text-sidebar-primary-foreground dark:text-sidebar-primary-foreground flex aspect-square items-center justify-center rounded-md bg-none dark:bg-none"
    >
        <AppLogoIcon class="dark:fill-sidebar-primary-foreground size-32 fill-black" />
    </div>
</template>
