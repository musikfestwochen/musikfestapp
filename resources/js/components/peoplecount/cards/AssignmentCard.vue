<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PeoplecountAssignment } from '@/types';
import { formatDateTime } from '@/utils/dateTimeHelpers';
import { Users } from 'lucide-vue-next';

const props = defineProps<{
    assignment: PeoplecountAssignment;
    showAreaName?: boolean; // If true, shows area name as title, sensor as subtitle
    showSensorName?: boolean; // If true, shows sensor as title, area as subtitle
}>();

const getSensorDisplay = () => {
    const sensor = props.assignment.sensor;
    if (!sensor) return 'Unknown Sensor';
    return sensor.name || `${sensor.vendor} ${sensor.model}`;
};

const getTitle = () => {
    if (props.showAreaName) {
        return props.assignment.area?.name || 'Unknown Area';
    }
    if (props.showSensorName) {
        return getSensorDisplay();
    }
    return props.assignment.label || getSensorDisplay();
};

const getSubtitle = () => {
    if (props.showAreaName) {
        return props.assignment.label || getSensorDisplay();
    }
    if (props.showSensorName) {
        return props.assignment.label || null;
    }
    return null;
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Users class="h-4 w-4" />
                {{ getTitle() }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-2">
                <p v-if="getSubtitle()" class="text-sm font-medium">{{ getSubtitle() }}</p>

                <Badge v-if="assignment.direction_flipped" variant="destructive"> direction flipped </Badge>

                <div class="text-muted-foreground text-xs">
                    <p><strong>Active from:</strong> {{ formatDateTime(assignment.active_from) }}</p>
                    <p><strong>Active to:</strong> {{ formatDateTime(assignment.active_to) }}</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
