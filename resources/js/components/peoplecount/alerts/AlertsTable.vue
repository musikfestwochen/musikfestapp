<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { formatLocalDateTime } from '@/utils/dateTimeHelpers';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

export type AlertType = 'occupancy_alert';
export type AlertChannel = 'email' | 'vonage';

export interface AlertDTO {
    id: number;
    area_id: number;
    type: AlertType;
    channel: AlertChannel;
    cooldown_minutes: number;
    occupancy_alert_threshold?: number | null;
    created_by?: number | null;
    creator?: { id: number; name: string } | null;
    recipients?: { id: number; name: string; email?: string }[];
    last_triggered_at?: string | null;
}

const props = defineProps<{
    alerts: AlertDTO[];
    orgSlug?: string;
    areaId?: number;
    onChanged?: () => void;
}>();

const confirmDialog = useConfirmDialog();

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

async function onDelete(alertId: number) {
    if (!props.orgSlug || !props.areaId) return;

    const ok = await confirmDialog.confirm({
        title: 'Delete alert?',
        description: 'Are you sure you want to delete this alert? This action cannot be undone.',
        confirmText: 'Delete Alert',
        cancelText: 'Cancel',
        variant: 'destructive',
    });
    if (!ok) return;

    router.delete(
        route('peoplecount.areas.alerts.destroy', {
            organization: props.orgSlug,
            area: props.areaId,
            alert: alertId,
        }),
        {
            preserveScroll: true,
            onSuccess: () => props.onChanged?.(),
        },
    );
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
                    <TableHead>Last triggered at</TableHead>
                    <TableHead>Actions</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="alert in rows" :key="alert.id">
                    <TableCell>{{ typeLabel(alert.type) }}</TableCell>
                    <TableCell>{{ channelLabel(alert.channel) }}</TableCell>
                    <TableCell>{{ alert.cooldown_minutes }} min</TableCell>
                    <TableCell>
                        <span v-if="alert.type === 'occupancy_alert'">{{ alert.occupancy_alert_threshold ?? '—' }}</span>
                        <span v-else>—</span>
                    </TableCell>
                    <TableCell>{{ recipientsDisplay(alert.recipients) }}</TableCell>
                    <TableCell>{{ alert.creator?.name ?? '—' }}</TableCell>
                    <TableCell>
                        <span v-if="alert.last_triggered_at">{{ formatLocalDateTime(alert.last_triggered_at) }}</span>
                        <span v-else>—</span>
                    </TableCell>
                    <TableCell class="whitespace-nowrap">
                        <div class="flex gap-2">
                            <Link
                                v-if="props.orgSlug && props.areaId"
                                :href="route('peoplecount.areas.alerts.edit', { organization: props.orgSlug, area: props.areaId, alert: alert.id })"
                                as="button"
                                method="get"
                                preserve-scroll
                            >
                                <Button size="sm" variant="secondary">Edit</Button>
                            </Link>
                            <Button v-if="props.orgSlug && props.areaId" size="sm" variant="destructive" @click="onDelete(alert.id)">Delete</Button>
                        </div>
                    </TableCell>
                </TableRow>
                <TableRow v-if="rows.length === 0">
                    <TableCell class="text-muted-foreground text-center" colspan="8"> No alerts yet. </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
