<script lang="ts" setup>
import type { User } from '@/types';

interface Props {
    user: User;
    showEmail?: boolean;
}

const { user, showEmail } = withDefaults(defineProps<Props>(), {
    showEmail: false,
});
</script>

<template>
    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
    </div>
</template>
