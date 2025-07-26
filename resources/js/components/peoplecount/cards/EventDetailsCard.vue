<script lang="ts" setup>
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PeoplecountEvent } from '@/types';
import { formatLocalDateTime } from '@/utils/eventHelpers';
import { Calendar } from 'lucide-vue-next';

defineProps<{
    event: PeoplecountEvent;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Calendar class="h-4 w-4" />
                {{ event.name }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-2 text-sm text-muted-foreground">
                <p><strong>Start:</strong> {{ formatLocalDateTime(event.starts_at) }}</p>
                <p><strong>End:</strong> {{ formatLocalDateTime(event.ends_at) }}</p>
            </div>
        </CardContent>
    </Card>
</template>
