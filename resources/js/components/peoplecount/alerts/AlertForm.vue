<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TagsCombobox, type TagsComboboxItem } from '@/components/ui/tags-combobox';
import InputError from '@/components/InputError.vue';
import type { User } from '@/types';

// Types local to Peoplecount Alerts
export type AlertType = 'occupancy_alert';
export type AlertChannel = 'email' | 'vonage';

const props = defineProps<{
    orgSlug: string;
    areaId: number;
    onCreated?: () => void;
}>();

// Try to source available users for recipients from shared Inertia page props.
// The controller is expected to provide a minimal users list (id, name, email, phone).
const page = usePage();
const availableUsers = computed<User[]>(() => {
    const p: any = page.props;
    // Try a few common keys that might be used to pass users down
    return (p?.users || p?.organization_users || p?.peoplecount_users || p?.alerts_users || []) as User[];
});

const items = computed<TagsComboboxItem[]>(() => {
    return (availableUsers.value || []).map((u) => ({
        value: String(u.id),
        // Use a unique label to allow round-trip back to id
        label: u.email ? `${u.name} <${u.email}>` : `${u.name} (#${u.id})`,
    }));
});

// Map label back to id for payload
const labelToId = computed<Record<string, number>>(() => {
    const map: Record<string, number> = {};
    for (const u of availableUsers.value || []) {
        const label = u.email ? `${u.name} <${u.email}>` : `${u.name} (#${u.id})`;
        map[label] = u.id;
    }
    return map;
});

const recipientsTags = ref<string[]>([]);

const form = useForm({
    type: 'occupancy_alert' as AlertType,
    channel: 'email' as AlertChannel,
    cooldown_seconds: 0 as number,
    occupancy_alert_threshold: null as number | null,
});

const isOccupancy = computed(() => form.type === 'occupancy_alert');

function resetForm() {
    form.reset();
    // Reset back to sensible defaults
    form.type = 'occupancy_alert';
    form.channel = 'email';
    form.cooldown_seconds = 0;
    form.occupancy_alert_threshold = null;
    recipientsTags.value = [];
}

function onSubmit() {
    const recipients: number[] = recipientsTags.value
        .map((label) => labelToId.value[label])
        .filter((v): v is number => typeof v === 'number' && !Number.isNaN(v));

    const data: Record<string, any> = {
        type: form.type,
        channel: form.channel,
        cooldown_seconds: form.cooldown_seconds,
        ...(isOccupancy.value ? { occupancy_alert_threshold: form.occupancy_alert_threshold ?? 0 } : {}),
        recipients,
    };

    router.post(
        route('peoplecount.areas.alerts.store', {
            organization: props.orgSlug,
            area: props.areaId,
        }),
        data,
        {
            preserveScroll: true,
            onSuccess: () => {
                resetForm();
                props.onCreated?.();
            },
        },
    );
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="onSubmit">
        <div class="grid max-w-2xl gap-6">
            <div class="grid gap-2">
                <Label for="alert_type">Type</Label>
                <Select id="alert_type" v-model="form.type" required>
                    <SelectTrigger>
                        <SelectValue placeholder="Select a type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="occupancy_alert">Occupancy alert</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.type" />
            </div>

            <div class="grid gap-2">
                <Label for="alert_channel">Channel</Label>
                <Select id="alert_channel" v-model="form.channel" required>
                    <SelectTrigger>
                        <SelectValue placeholder="Select a channel" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="email">Email</SelectItem>
                        <SelectItem value="vonage">Vonage</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.channel" />
            </div>

            <div class="grid gap-2">
                <Label for="cooldown_seconds">Cooldown (seconds)</Label>
                <Input id="cooldown_seconds" v-model.number="form.cooldown_seconds" min="0" required type="number" />
                <InputError :message="form.errors.cooldown_seconds" />
                <p class="text-sm text-muted-foreground">Minimum interval between repeated alerts.</p>
            </div>

            <div v-if="isOccupancy" class="grid gap-2">
                <Label for="occupancy_alert_threshold">Occupancy threshold</Label>
                <Input id="occupancy_alert_threshold" v-model.number="form.occupancy_alert_threshold" min="0" required type="number" />
                <InputError :message="form.errors.occupancy_alert_threshold" />
                <p class="text-sm text-muted-foreground">Alert when occupancy reaches or exceeds this value.</p>
            </div>

            <div class="grid gap-2">
                <Label>Recipients</Label>
                <TagsCombobox v-model="recipientsTags" :items="items" placeholder="Search users..." :max="20" input-class="min-w-[260px]" />
                <p class="text-sm text-muted-foreground">Select up to 20 recipients.</p>
            </div>

            <div>
                <Button :disabled="form.processing" type="submit"> Create Alert </Button>
            </div>
        </div>
    </form>
</template>
