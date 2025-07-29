<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Organization, PeoplecountEvent } from '@/types';
import { getLocalDateFromUTC, getUTCStringFromLocal } from '@/utils/dateTimeHelpers';
import { useForm } from '@inertiajs/vue3';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{ event?: PeoplecountEvent; organization: Organization }>();

// Initialize date range from existing event or null for new events
const initialDateRange = props.event ? [getLocalDateFromUTC(props.event.starts_at), getLocalDateFromUTC(props.event.ends_at)] : null;

const dateRange = ref(initialDateRange);

const form = useForm({
    name: props.event?.name || '',
    starts_at: props.event?.starts_at || '',
    ends_at: props.event?.ends_at || '',
});

// Computed property to handle date range changes
const handleDateRangeChange = (range: [Date, Date] | null) => {
    dateRange.value = range;
    if (range && range.length === 2) {
        form.starts_at = getUTCStringFromLocal(range[0]);
        form.ends_at = getUTCStringFromLocal(range[1]);
    } else {
        form.starts_at = '';
        form.ends_at = '';
    }
};

const submit = () => {
    if (props.event && props.organization) {
        form.put(
            route('peoplecount.events.update', {
                event: props.event.id,
                organization: props.organization.slug,
            }),
        );
    } else if (props.organization) {
        form.post(route('peoplecount.events.store', { organization: props.organization.slug }));
    }
};

// Date picker configuration
const datePickerConfig = {
    range: true,
    enableTimePicker: true,
    format: 'dd/MM/yyyy HH:mm',
    previewFormat: 'dd/MM/yyyy HH:mm',
    placeholder: 'Select event date and time range',
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
                <Label for="name">Event Name</Label>
                <Input id="name" v-model="form.name" :tabindex="1" autocomplete="on" autofocus placeholder="Event Name" required type="text" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="dateRange">Event Date & Time Range</Label>
                <VueDatePicker
                    id="dateRange"
                    v-model="dateRange"
                    :tabindex="2"
                    class="dp-custom-input"
                    v-bind="datePickerConfig"
                    @update:model-value="handleDateRangeChange"
                />
                <InputError :message="form.errors.starts_at" />
                <InputError :message="form.errors.ends_at" />
                <p class="text-sm text-muted-foreground">Select the start and end date/time for your event. Times are in your local timezone.</p>
            </div>

            <Button :disabled="form.processing || !dateRange" class="mt-2 w-full" tabindex="3" type="submit">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>{{ props.event ? 'Update Event' : 'Create Event' }}</span>
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
