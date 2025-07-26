<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

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

const formatTimestamp = (iso: string) =>
    new Date(iso).toLocaleString(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    });
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Measurement Interval</CardTitle>
            <CardDescription>
                <div class="flex flex-wrap gap-1 md:flex-nowrap">
                    <span class="whitespace-nowrap"> From: {{ formatTimestamp(measurement.ts_from) }} </span>
                    <span class="hidden md:inline">—</span>
                    <span class="whitespace-nowrap"> To: {{ formatTimestamp(measurement.ts_to) }} </span>
                </div>
            </CardDescription>
        </CardHeader>
        <CardContent class="flex gap-4">
            <Badge variant="secondary">In: {{ measurement.count_in }}</Badge>
            <Badge variant="secondary">Out: {{ measurement.count_out }}</Badge>
        </CardContent>
    </Card>
</template>
