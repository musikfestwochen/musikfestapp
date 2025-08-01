<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Organization, PeoplecountArea, PeoplecountAreaSingleReset } from '@/types';
import { getLocalDateFromUTC, getUTCStringFromLocal } from '@/utils/dateTimeHelpers';
import { useForm } from '@inertiajs/vue3';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    reset?: PeoplecountAreaSingleReset;
    area: PeoplecountArea;
    organization: Organization;
}>();

// Initialize effective date from existing reset or current time for new resets
const initialEffectiveDate = props.reset ? getLocalDateFromUTC(props.reset.effective_at) : new Date(); // Default to current time for new resets

const effectiveDate = ref(initialEffectiveDate);

const form = useForm({
    area_id: props.area.id,
    reset_value: props.reset?.reset_value || 0,
    effective_at: props.reset?.effective_at || '',
    notes: props.reset?.notes || '',
});

// Handle date change
const handleDateChange = (date: Date | null) => {
    effectiveDate.value = date;
    if (date) {
        form.effective_at = getUTCStringFromLocal(date);
    } else {
        form.effective_at = '';
    }
};

const submit = () => {
    if (props.organization) {
        form.post(
            route('peoplecount.areas.single-resets.store', {
                organization: props.organization.slug,
                area: props.area.id,
            }),
        );
    }
};

// Date picker configuration
const datePickerConfig = {
    enableTimePicker: true,
    format: 'dd/MM/yyyy HH:mm',
    previewFormat: 'dd/MM/yyyy HH:mm',
    placeholder: 'Select effective date and time',
    autoApply: true,
    closeOnAutoApply: true,
    utc: false, // We handle UTC conversion manually
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
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
                    autocomplete="off"
                    autofocus
                    min="0"
                    placeholder="Reset Value"
                    required
                    step="1"
                    type="number"
                />
                <InputError :message="form.errors.reset_value" />
                <p class="text-sm text-muted-foreground">Enter the new count value to reset to (must be a positive integer).</p>
            </div>

            <div class="grid gap-2">
                <Label for="effective_at">Effective Date & Time</Label>
                <VueDatePicker
                    id="effective_at"
                    v-model="effectiveDate"
                    :tabindex="2"
                    class="dp-custom-input"
                    v-bind="datePickerConfig"
                    @update:model-value="handleDateChange"
                />
                <InputError :message="form.errors.effective_at" />
                <p class="text-sm text-muted-foreground">Select when this reset should take effect. Times are in your local timezone.</p>
            </div>

            <div class="grid gap-2">
                <Label for="notes">Notes</Label>
                <Textarea id="notes" v-model="form.notes" :tabindex="3" placeholder="Optional notes about this reset" rows="3" />
                <InputError :message="form.errors.notes" />
                <p class="text-sm text-muted-foreground">Add any additional context or reason for this reset (optional).</p>
            </div>

            <Button :disabled="form.processing || form.reset_value < 0 || !effectiveDate" class="mt-2 w-full" tabindex="4" type="submit">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>Create Reset</span>
            </Button>
        </div>
    </form>
</template>

<style scoped>
/* Custom styling for the date picker to match the design system */
:deep(.dp__input) {
    @apply flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50;
}

:deep(.dp__input_wrap) {
    @apply w-full;
}

:deep(.dp__main) {
    @apply font-sans;
}
</style>
