<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PeoplecountSensorFormData } from '@/types';
import { LoaderCircle } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        form: PeoplecountSensorFormData;
        errors?: Partial<Record<keyof PeoplecountSensorFormData, string>>;
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
    change: [values: Partial<PeoplecountSensorFormData>];
}>();

function updateNullableName(value: string | number): void {
    emit('change', { name: value === '' ? null : String(value) });
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="emit('submit')">
        <div class="grid max-w-80 gap-6">
            <div class="grid gap-2">
                <Label for="vendor">Vendor</Label>
                <Input
                    id="vendor"
                    :model-value="form.vendor"
                    :tabindex="1"
                    autocomplete="on"
                    autofocus
                    placeholder="Vendor Name"
                    required
                    type="text"
                    @update:model-value="(v) => emit('change', { vendor: String(v) })"
                />
                <InputError :message="errors?.vendor" />
            </div>

            <div class="grid gap-2">
                <Label for="model">Model</Label>
                <Input
                    id="model"
                    :model-value="form.model"
                    :tabindex="2"
                    autocomplete="on"
                    placeholder="Sensor Model"
                    required
                    type="text"
                    @update:model-value="(v) => emit('change', { model: String(v) })"
                />
                <InputError :message="errors?.model" />
            </div>

            <div class="grid gap-2">
                <Label for="serial">Serial Number</Label>
                <Input
                    id="serial"
                    :model-value="form.serial"
                    :tabindex="3"
                    autocomplete="off"
                    placeholder="Sensor Serial Number"
                    required
                    type="text"
                    @update:model-value="(v) => emit('change', { serial: String(v) })"
                />
                <InputError :message="errors?.serial" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    :model-value="form.name || ''"
                    :tabindex="4"
                    autocomplete="off"
                    placeholder="e.g. Main Entrance Counter"
                    type="text"
                    @update:model-value="updateNullableName"
                />
                <InputError :message="errors?.name" />
                <p class="text-muted-foreground text-sm">Optional. A human-readable name for this sensor.</p>
            </div>

            <Button :disabled="processing" class="mt-2 w-full" tabindex="5" type="submit">
                <LoaderCircle v-if="processing" class="h-4 w-4 animate-spin" />
                <span v-else>{{ submitLabel }}</span>
            </Button>
        </div>
    </form>
</template>
