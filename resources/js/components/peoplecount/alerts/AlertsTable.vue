<script setup lang="ts">
import { computed } from 'vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatLocalDateTime } from '@/utils/dateTimeHelpers';

export type AlertType = 'occupancy_alert';
export type AlertChannel = 'email' | 'vonage';

export interface AlertDTO {
    id: number;
    area_id: number;
    type: AlertType;
    channel: AlertChannel;
    cooldown_seconds: number;
    occupancy_alert_threshold?: number | null;
    created_by?: number | null;
    creator?: { id: number; name: string } | null;
    recipients?: { id: number; name: string; email?: string }[];
    created_at?: string;
}

const props = defineProps<{
    alerts: AlertDTO[];
}>();

const typeLabel = (t: AlertType) => {
    switch (t) {
        case 'occupancy_alert':
            return 'Occupancy alert';
        default:
            return t;
    }
};

const channelLabel = (c: AlertChannel) => {
    switch (c) {
        case 'email':
            return 'Email';
        case 'vonage':
            return 'Vonage';
        default:
            return c;
    }
};

function recipientsDisplay(recipients?: { id: number; name: string }[]) {
    if (!recipients || recipients.length === 0) return '—';
    if (recipients.length <= 3) return recipients.map((r) => r.name).join(', ');
    const first = recipients
        .slice(0, 3)
        .map((r) => r.name)
        .join(', ');
    return `${first} (+${recipients.length - 3} more)`;
}

const rows = computed(() => props.alerts || []);
</script>

<template>
    <div class="w-full overflow-x-auto">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Type</TableHead>
                    <TableHead>Channel</TableHead>
                    <TableHead>Cooldown</TableHead>
                    <TableHead>Threshold</TableHead>
                    <TableHead>Recipients</TableHead>
                    <TableHead>Creator</TableHead>
                    <TableHead>Created at</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="alert in rows" :key="alert.id">
                    <TableCell>{{ typeLabel(alert.type) }}</TableCell>
                    <TableCell>{{ channelLabel(alert.channel) }}</TableCell>
                    <TableCell>{{ alert.cooldown_seconds }}s</TableCell>
                    <TableCell>
                        <span v-if="alert.type === 'occupancy_alert'">{{ alert.occupancy_alert_threshold ?? '—' }}</span>
                        <span v-else>—</span>
                    </TableCell>
                    <TableCell>{{ recipientsDisplay(alert.recipients) }}</TableCell>
                    <TableCell>{{ alert.creator?.name ?? '—' }}</TableCell>
                    <TableCell>
                        <span v-if="alert.created_at">{{ formatLocalDateTime(alert.created_at) }}</span>
                        <span v-else>—</span>
                    </TableCell>
                </TableRow>
                <TableRow v-if="rows.length === 0">
                    <TableCell class="text-center text-muted-foreground" colspan="7"> No alerts yet. </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
