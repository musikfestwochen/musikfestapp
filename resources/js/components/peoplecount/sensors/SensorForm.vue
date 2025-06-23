<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Organization, PeoplecountSensor } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{ sensor?: PeoplecountSensor; organization: Organization }>();

const form = useForm({
    vendor: props.sensor?.vendor || '',
    model: props.sensor?.model || '',
    serial: props.sensor?.serial || '',
});

const submit = () => {
    if (props.sensor && props.organization) {
        form.put(
            route('peoplecount.sensors.update', {
                sensor: props.sensor.id,
                organization: props.organization.slug,
            }),
        );
    } else if (props.organization) {
        form.post(route('peoplecount.sensors.store', { organization: props.organization.slug }));
    }
};
</script>
<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid max-w-80 gap-6">
            <div class="grid gap-2">
                <Label for="vendor">Vendor</Label>
                <Input id="vendor" v-model="form.vendor" :tabindex="1" autocomplete="on" autofocus placeholder="Vendor Name" required type="text" />
                <InputError :message="form.errors.vendor" />
            </div>

            <div class="grid gap-2">
                <Label for="model">Model</Label>
                <Input id="model" v-model="form.model" :tabindex="2" autocomplete="on" placeholder="Sensor Model" required type="text" />
                <InputError :message="form.errors.model" />
            </div>

            <div class="grid gap-2">
                <Label for="serial">Serial Number</Label>
                <Input id="serial" v-model="form.serial" :tabindex="3" autocomplete="off" placeholder="Sensor Serial Number" required type="text" />
                <InputError :message="form.errors.serial" />
            </div>

            <Button :disabled="form.processing" class="mt-2 w-full" tabindex="5" type="submit">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>{{ props.sensor ? 'Update Sensor' : 'Create Sensor' }}</span>
            </Button>
        </div>
    </form>
</template>
