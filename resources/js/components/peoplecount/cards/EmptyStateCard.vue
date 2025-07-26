<script lang="ts" setup>
import { Card, CardContent } from '@/components/ui/card';

defineProps<{
    message: string;
}>();
</script>

<template>
    <Card>
        <CardContent class="pt-6">
            <p class="text-center text-muted-foreground">{{ message }}</p>
        </CardContent>
    </Card>
</template>
