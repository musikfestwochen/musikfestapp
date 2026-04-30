<script lang="ts" setup>
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PeoplecountArea } from '@/types';
import { MapPin } from 'lucide-vue-next';

defineProps<{
    area: PeoplecountArea;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <MapPin class="h-4 w-4" />
                {{ area.name }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <p class="text-muted-foreground text-sm">
                {{ area.assignments?.length || 0 }} assignment{{ (area.assignments?.length || 0) !== 1 ? 's' : '' }}
            </p>
        </CardContent>
    </Card>
</template>
