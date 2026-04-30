<script lang="ts" setup>
import { Card, CardContent } from '@/components/ui/card';

defineProps<{
    message: string;
}>();
</script>

<template>
    <Card>
        <CardContent class="pt-6">
            <p class="text-muted-foreground text-center">{{ message }}</p>
        </CardContent>
    </Card>
</template>
