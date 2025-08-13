<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TagsCombobox, type TagsComboboxItem } from '@/components/ui/tags-combobox';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import type { BreadcrumbItem, Organization } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Local Peoplecount types
export type AlertType = 'occupancy_alert';
export type AlertChannel = 'email' | 'vonage';

interface AreaDTO {
    id: number;
    name: string;
}
interface RecipientDTO {
    id: number;
    name: string;
    email?: string;
}

interface AlertDTO {
    id: number;
    area_id: number;
    type: AlertType;
    channel: AlertChannel;
    cooldown_seconds: number;
    occupancy_alert_threshold?: number | null;
    recipients?: RecipientDTO[];
}

const props = defineProps<{
    organization: Organization;
    area: AreaDTO;
    alert: AlertDTO;
    status?: string | null;
}>();

const page = usePage();
const availableUsers = computed<any[]>(() => {
    const p: any = page.props;
    return (p?.users || p?.organization_users || p?.peoplecount_users || p?.alerts_users || []) as any[];
});

// Options (fallbacks if not provided via shared props)

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
    return (availableUsers.value || []).map((u: any) => ({
        value: String(u.id),
        label: u.email ? `${u.name} <${u.email}>` : `${u.name} (#${u.id})`,
    }));
});

const labelToId = computed<Record<string, number>>(() => {
    const map: Record<string, number> = {};
    for (const u of availableUsers.value || []) {
        const label = u.email ? `${u.name} <${u.email}>` : `${u.name} (#${u.id})`;
        map[label] = u.id as number;
    }
    return map;
});

// Form state
const form = ref({
    type: props.alert.type as AlertType,
    channel: props.alert.channel as AlertChannel,
    cooldown_seconds: props.alert.cooldown_seconds ?? 0,
    occupancy_alert_threshold: props.alert.occupancy_alert_threshold ?? (null as number | null),
});

const recipientsTags = ref<string[]>((props.alert.recipients || []).map((r) => (r.email ? `${r.name} <${r.email}>` : `${r.name} (#${r.id})`)));

const isOccupancy = computed(() => form.value.type === 'occupancy_alert');

function onSubmit() {
    const recipients: number[] = (recipientsTags.value || [])
        .map((label) => labelToId.value[label])
        .filter((v): v is number => typeof v === 'number' && !Number.isNaN(v));

    const data: Record<string, any> = {
        type: form.value.type,
        channel: form.value.channel,
        cooldown_seconds: form.value.cooldown_seconds,
        ...(isOccupancy.value ? { occupancy_alert_threshold: form.value.occupancy_alert_threshold ?? 0 } : {}),
        recipients,
    };

    router.put(
        route('peoplecount.areas.alerts.update', {
            organization: props.organization.slug,
            area: props.area.id,
            alert: props.alert.id,
        }),
        data,
        { preserveScroll: true },
    );
}

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: route('organization.dashboard', { organization: props.organization.slug }),
    },
    {
        title: 'Areas',
        href: route('peoplecount.areas.index', { organization: props.organization.slug }),
    },
    {
        title: 'Edit Area',
        href: route('peoplecount.areas.edit', {
            organization: props.organization.slug,
            area: props.area.id,
        }),
    },
    {
        title: 'Edit Alert',
        href: route('peoplecount.areas.alerts.edit', {
            organization: props.organization.slug,
            area: props.area.id,
            alert: props.alert.id,
        }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Edit Alert" />

        <div class="px-4 py-6">
            <div class="flex items-center justify-between">
                <Heading title="Edit Alert" />
                <div class="flex gap-2">
                    <Link
                        :href="route('peoplecount.areas.edit', { organization: organization.slug, area: area.id })"
                        class="text-sm text-muted-foreground hover:underline"
                        >Back to Area</Link
                    >
                </div>
            </div>

            <form class="mt-6 grid max-w-2xl gap-6" @submit.prevent="onSubmit">
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
                    <InputError :message="undefined" />
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
                    <InputError :message="undefined" />
                </div>

                <div class="grid gap-2">
                    <Label for="cooldown_seconds">Cooldown (seconds)</Label>
                    <Input id="cooldown_seconds" v-model.number="form.cooldown_seconds" min="0" required type="number" />
                    <p class="text-sm text-muted-foreground">Minimum interval between repeated alerts.</p>
                </div>

                <div v-if="isOccupancy" class="grid gap-2">
                    <Label for="occupancy_alert_threshold">Occupancy threshold</Label>
                    <Input id="occupancy_alert_threshold" v-model.number="form.occupancy_alert_threshold" min="0" required type="number" />
                    <p class="text-sm text-muted-foreground">Alert when occupancy reaches or exceeds this value.</p>
                </div>

                <div class="grid gap-2">
                    <Label>Recipients</Label>
                    <TagsCombobox v-model="recipientsTags" :items="items" placeholder="Search users..." :max="20" input-class="min-w-[260px]" />
                    <p class="text-sm text-muted-foreground">Select up to 20 recipients.</p>
                </div>

                <div>
                    <Button type="submit">Save Changes</Button>
                </div>
            </form>
        </div>
    </Layout>
</template>
