<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/components/ui/hover-card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { StageSafetySensorFormData, StageSafetySensorType } from '@/types';
import deviceIdHelpImage from '../../../../img/stage-safety/bw-wss-device-id.gif';
import { CircleHelp, LoaderCircle } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        form: StageSafetySensorFormData;
        sensorTypes: StageSafetySensorType[];
        errors?: Partial<Record<keyof StageSafetySensorFormData, string>>;
        processing?: boolean;
        submitLabel: string;
    }>(),
    {
        errors: () => ({}),
        processing: false,
    },
);

const emit = defineEmits<{
    submit: [];
    change: [values: Partial<StageSafetySensorFormData>];
}>();

function selectSensorType(event: Event): void {
    const sensorType = props.sensorTypes[Number((event.target as HTMLSelectElement).value)];

    if (!sensorType) {
        return;
    }

    emit('change', {
        manufacturer: sensorType.manufacturer,
        model: sensorType.model,
    });
}

function updateIdentifier(value: string | number): void {
    emit('change', { identifier: String(value).toUpperCase() });
}

function updateNullableString(field: 'name' | 'location', value: string | number): void {
    emit('change', { [field]: value === '' ? null : String(value) });
}

function updateStaleAfter(value: string | number): void {
    emit('change', { stale_after_seconds: Number(value) });
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="emit('submit')">
        <div class="grid max-w-xl gap-6 sm:grid-cols-2">
            <div class="grid gap-2 sm:col-span-2">
                <Label for="sensor_type">Sensor Type</Label>
                <select
                    id="sensor_type"
                    class="border-input bg-background ring-offset-background focus-visible:ring-ring h-10 rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    required
                    @change="selectSensorType"
                >
                    <option
                        v-for="(sensorType, index) in sensorTypes"
                        :key="`${sensorType.manufacturer}:${sensorType.model}`"
                        :selected="sensorType.manufacturer === form.manufacturer && sensorType.model === form.model"
                        :value="index"
                    >
                        {{ sensorType.label }}
                    </option>
                </select>
                <InputError :message="errors?.manufacturer || errors?.model" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <div class="flex items-center gap-1.5">
                    <Label for="identifier">BroadWeigh Device ID</Label>
                    <HoverCard :open-delay="200">
                        <HoverCardTrigger as-child>
                            <button
                                aria-label="Where to find the BroadWeigh Device ID"
                                class="text-muted-foreground hover:text-foreground focus-visible:ring-ring rounded-sm focus-visible:ring-2 focus-visible:outline-none"
                                type="button"
                            >
                                <CircleHelp class="size-4" />
                            </button>
                        </HoverCardTrigger>
                        <HoverCardContent align="start" class="w-auto max-w-[calc(100vw-2rem)] p-2">
                            <img
                                :src="deviceIdHelpImage"
                                alt="BroadWeigh BW-WSS label with the six-character Device ID highlighted in the upper-right corner"
                                class="h-auto w-[571px] max-w-full rounded-sm"
                                height="232"
                                width="571"
                            />
                        </HoverCardContent>
                    </HoverCard>
                </div>
                <Input
                    id="identifier"
                    :model-value="form.identifier"
                    autocomplete="off"
                    autofocus
                    maxlength="6"
                    pattern="[0-9A-Fa-f]{6}"
                    placeholder="FF1234"
                    required
                    spellcheck="false"
                    @update:model-value="updateIdentifier"
                />
                <InputError :message="errors?.identifier" />
                <p class="text-muted-foreground text-sm">Six-character hexadecimal ID printed on the sensor, not its serial number.</p>
            </div>

            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    :model-value="form.name || ''"
                    autocomplete="off"
                    placeholder="Main Stage Wind"
                    @update:model-value="updateNullableString('name', $event)"
                />
                <InputError :message="errors?.name" />
                <p class="text-muted-foreground text-sm">Optional operator-facing name.</p>
            </div>

            <div class="grid gap-2">
                <Label for="location">Location</Label>
                <Input
                    id="location"
                    :model-value="form.location || ''"
                    autocomplete="off"
                    placeholder="Main Stage Roof"
                    @update:model-value="updateNullableString('location', $event)"
                />
                <InputError :message="errors?.location" />
                <p class="text-muted-foreground text-sm">Optional physical installation point.</p>
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="stale_after_seconds">Mark data stale after</Label>
                <div class="flex items-center gap-3">
                    <Input
                        id="stale_after_seconds"
                        :model-value="form.stale_after_seconds"
                        class="max-w-40"
                        max="86400"
                        min="1"
                        required
                        type="number"
                        @update:model-value="updateStaleAfter"
                    />
                    <span class="text-muted-foreground text-sm">seconds</span>
                </div>
                <InputError :message="errors?.stale_after_seconds" />
                <p class="text-muted-foreground text-sm">Data is stale when no sensor data has been received within this period.</p>
            </div>

            <Button :disabled="processing" class="w-full sm:col-span-2 sm:w-fit" type="submit">
                <LoaderCircle v-if="processing" class="h-4 w-4 animate-spin" />
                <span v-else>{{ submitLabel }}</span>
            </Button>
        </div>
    </form>
</template>
