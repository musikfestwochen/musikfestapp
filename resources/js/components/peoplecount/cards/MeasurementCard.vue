<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/utils/dateTimeHelpers';

interface MeasurementInterval {
    id: number;
    ts_from: string;
    ts_to: string;
    count_in: number;
    count_out: number;
}

defineProps<{
    measurement: MeasurementInterval;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Measurement Interval</CardTitle>
            <CardDescription>
                <div class="flex flex-wrap gap-1 md:flex-nowrap">
                    <span class="whitespace-nowrap"> From: {{ formatDateTime(measurement.ts_from) }} </span>
                    <span class="hidden md:inline">—</span>
                    <span class="whitespace-nowrap"> To: {{ formatDateTime(measurement.ts_to) }} </span>
                </div>
            </CardDescription>
        </CardHeader>
        <CardContent class="flex gap-4">
            <Badge variant="secondary">In: {{ measurement.count_in }}</Badge>
            <Badge variant="secondary">Out: {{ measurement.count_out }}</Badge>
        </CardContent>
    </Card>
</template>
