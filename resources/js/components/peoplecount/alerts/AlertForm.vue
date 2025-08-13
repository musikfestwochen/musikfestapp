<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TagsCombobox, type TagsComboboxItem } from '@/components/ui/tags-combobox';
import type { User } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

// Enum options from page props or sensible fallbacks
// Keep in sync with backend enums' display names and descriptions
// to ensure consistent UX even if page props are missing.

type AlertOption<T extends string> = { value: T; displayName: string; description: string };

const alertTypeOptions = computed<AlertOption<AlertType>[]>(() => {
    const p: any = page.props;
    const list = (p?.alertTypeOptions || []) as AlertOption<AlertType>[];
    if (Array.isArray(list) && list.length) return list;
    return [{ value: 'occupancy_alert', displayName: 'Occupancy Alert', description: 'Alert when occupancy exceeds a specified threshold.' }];
});

const alertChannelOptions = computed<AlertOption<AlertChannel>[]>(() => {
    const p: any = page.props;
    const list = (p?.alertChannelOptions || []) as AlertOption<AlertChannel>[];
    if (Array.isArray(list) && list.length) return list;
    return [
        { value: 'email', displayName: 'Email', description: 'Send alerts via email.' },
        { value: 'vonage', displayName: 'SMS', description: 'Send alerts via SMS.' },
    ];
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

const selectedTypeOption = computed(() => alertTypeOptions.value.find((o) => o.value === form.type));
const selectedChannelOption = computed(() => alertChannelOptions.value.find((o) => o.value === form.channel));

const form = useForm({
    type: 'occupancy_alert' as AlertType,
    channel: 'email' as AlertChannel,
    cooldown_minutes: 30 as number,
    occupancy_alert_threshold: null as number | null,
});

const isOccupancy = computed(() => form.type === 'occupancy_alert');

function resetForm() {
    form.reset();
    // Reset back to sensible defaults
    form.type = 'occupancy_alert';
    form.channel = 'email';
    form.cooldown_minutes = 30;
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
        cooldown_minutes: form.cooldown_minutes,
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
                        <SelectItem v-for="opt in alertTypeOptions" :key="opt.value" :value="opt.value">{{ opt.displayName }}</SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="selectedTypeOption" class="text-sm text-muted-foreground">{{ selectedTypeOption.description }}</p>
                <InputError :message="form.errors.type" />
            </div>

            <div class="grid gap-2">
                <Label for="alert_channel">Channel</Label>
                <Select id="alert_channel" v-model="form.channel" required>
                    <SelectTrigger>
                        <SelectValue placeholder="Select a channel" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="opt in alertChannelOptions" :key="opt.value" :value="opt.value">{{ opt.displayName }}</SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="selectedChannelOption" class="text-sm text-muted-foreground">{{ selectedChannelOption.description }}</p>
                <InputError :message="form.errors.channel" />
            </div>

            <div class="grid gap-2">
                <Label for="cooldown_minutes">Cooldown (minutes)</Label>
                <Input id="cooldown_minutes" v-model.number="form.cooldown_minutes" min="30" required type="number" />
                <InputError :message="form.errors.cooldown_minutes" />
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
                <TagsCombobox v-model="recipientsTags" :items="items" :max="20" input-class="min-w-[260px]" placeholder="Search users..." />
            </div>

            <div>
                <Button :disabled="form.processing" type="submit"> Create Alert </Button>
            </div>
        </div>
    </form>
</template>
