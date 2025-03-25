<script lang="ts" setup>
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-none text-sidebar-primary-foreground">
        <AppLogoIcon class="size-8 fill-black dark:fill-sidebar-primary-foreground" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">Musikfestapp</span>
    </div>
</template>
