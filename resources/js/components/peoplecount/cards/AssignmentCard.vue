<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PeoplecountAssignment } from '@/types';
import { formatLocalDateTime } from '@/utils/dateTimeHelpers';
import { Users } from 'lucide-vue-next';

const props = defineProps<{
    assignment: PeoplecountAssignment;
    showAreaName?: boolean; // If true, shows area name as title, sensor as subtitle
    showSensorName?: boolean; // If true, shows sensor as title, area as subtitle
}>();

const getTitle = () => {
    if (props.showAreaName) {
        return props.assignment.area?.name || 'Unknown Area';
    }
    if (props.showSensorName) {
        return `${props.assignment.sensor?.vendor} ${props.assignment.sensor?.model}`;
    }
    return `${props.assignment.sensor?.vendor} ${props.assignment.sensor?.model}`;
};

const getSubtitle = () => {
    if (props.showAreaName) {
        return `${props.assignment.sensor?.vendor} ${props.assignment.sensor?.model}`;
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
                    <p><strong>Active from:</strong> {{ formatLocalDateTime(assignment.active_from) }}</p>
                    <p><strong>Active to:</strong> {{ formatLocalDateTime(assignment.active_to) }}</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
