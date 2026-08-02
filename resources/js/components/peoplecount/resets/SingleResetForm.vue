<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Organization, PeoplecountArea, PeoplecountAreaSingleReset } from '@/types';
import { datetimeLocalToUTCString, utcStringToDatetimeLocal } from '@/utils/dateTimeHelpers';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{
    reset?: PeoplecountAreaSingleReset;
    area: PeoplecountArea;
    organization: Organization;
}>();

const form = useForm({
    reset_value: props.reset?.reset_value || 0,
    effective_at: props.reset ? utcStringToDatetimeLocal(props.reset.effective_at) : utcStringToDatetimeLocal(new Date().toISOString()),
    notes: props.reset?.notes || '',
});

const submitForm = () =>
    form.transform((data) => ({
        ...data,
        effective_at: datetimeLocalToUTCString(data.effective_at),
    }));

const submit = () => {
    if (props.organization) {
        submitForm().post(
            route('peoplecount.areas.single-resets.store', {
                organization: props.organization.slug,
                area: props.area.id,
            }),
        );
    }
};
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid max-w-xl gap-6">
            <div class="grid gap-2">
                <Label for="reset_value">Reset Value</Label>
                <Input
                    id="reset_value"
                    v-model.number="form.reset_value"
                    :tabindex="1"
                    autofocus
                    min="0"
                    placeholder="Reset Value"
                    required
                    step="1"
                    type="number"
                />
                <InputError :message="form.errors.reset_value" />
                <p class="text-muted-foreground text-sm">Enter the new count value to reset to (must be a positive integer).</p>
            </div>

            <div class="grid gap-2">
                <Label for="effective_at">Effective Date & Time</Label>
                <Input id="effective_at" v-model="form.effective_at" :tabindex="2" required type="datetime-local" />
                <InputError :message="form.errors.effective_at" />
                <p class="text-muted-foreground text-sm">Select when this reset should take effect. Times are in your local timezone.</p>
            </div>

            <div class="grid gap-2">
                <Label for="notes">Notes</Label>
                <Textarea id="notes" v-model="form.notes" :tabindex="3" placeholder="Optional notes about this reset" rows="3" />
                <InputError :message="form.errors.notes" />
                <p class="text-muted-foreground text-sm">Add any additional context or reason for this reset (optional).</p>
            </div>

            <Button :disabled="form.processing || form.reset_value < 0 || !form.effective_at" class="mt-2 w-full" tabindex="4" type="submit">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>Create Reset</span>
            </Button>
        </div>
    </form>
</template>
