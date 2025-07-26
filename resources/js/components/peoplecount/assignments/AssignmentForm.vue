<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Organization, PeoplecountAssignment, PeoplecountEvent, PeoplecountSensor } from '@/types';
import { getLocalDateFromUTC, getUTCStringFromLocal } from '@/utils/eventHelpers';
import { useForm } from '@inertiajs/vue3';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    assignment?: PeoplecountAssignment;
    organization: Organization;
    events: PeoplecountEvent[];
    sensors: PeoplecountSensor[];
}>();

// Initialize date range from existing assignment or null for new assignments
const initialDateRange = props.assignment
    ? [getLocalDateFromUTC(props.assignment.active_from), getLocalDateFromUTC(props.assignment.active_to)]
    : null;

const dateRange = ref(initialDateRange);

const form = useForm({
    event_id: props.assignment?.event_id || '',
    area_id: props.assignment?.area_id || '',
    sensor_id: props.assignment?.sensor_id || '',
    direction_flipped: props.assignment?.direction_flipped || false,
    active_from: props.assignment?.active_from || '',
    active_to: props.assignment?.active_to || '',
});

// Get areas from selected event
const filteredAreas = computed(() => {
    if (!form.event_id) return [];
    const selectedEvent = props.events.find((event) => event.id === parseInt(form.event_id.toString()));
    return selectedEvent?.areas || [];
});

// Reset area selection when event changes
watch(
    () => form.event_id,
    () => {
        form.area_id = '';
    },
);

// Handle date range changes
const handleDateRangeChange = (range: [Date, Date] | null) => {
    dateRange.value = range;
    if (range && range.length === 2) {
        form.active_from = getUTCStringFromLocal(range[0]);
        form.active_to = getUTCStringFromLocal(range[1]);
    } else {
        form.active_from = '';
        form.active_to = '';
    }
};

const submit = () => {
    if (props.assignment && props.organization) {
        form.put(
            route('peoplecount.assignments.update', {
                assignment: props.assignment.id,
                organization: props.organization.slug,
            }),
        );
    } else if (props.organization) {
        form.post(route('peoplecount.assignments.store', { organization: props.organization.slug }));
    }
};

// Date picker configuration
const datePickerConfig = {
    range: true,
    enableTimePicker: true,
    format: 'dd/MM/yyyy HH:mm',
    previewFormat: 'dd/MM/yyyy HH:mm',
    placeholder: 'Select active date and time range',
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
                <Label for="event_id">Event</Label>
                <Select v-model="form.event_id" required>
                    <SelectTrigger id="event_id" :tabindex="1">
                        <SelectValue placeholder="Select an event" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="event in events" :key="event.id" :value="event.id.toString()">
                            {{ event.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.event_id" />
            </div>

            <div class="grid gap-2">
                <Label for="area_id">Area</Label>
                <Select v-model="form.area_id" :disabled="!form.event_id" required>
                    <SelectTrigger id="area_id" :tabindex="2">
                        <SelectValue placeholder="Select an area" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="area in filteredAreas" :key="area.id" :value="area.id.toString()">
                            {{ area.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.area_id" />
                <p v-if="!form.event_id" class="text-sm text-muted-foreground">Please select an event first</p>
            </div>

            <div class="grid gap-2">
                <Label for="sensor_id">Sensor</Label>
                <Select v-model="form.sensor_id" required>
                    <SelectTrigger id="sensor_id" :tabindex="3">
                        <SelectValue placeholder="Select a sensor" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="sensor in sensors" :key="sensor.id" :value="sensor.id.toString()">
                            {{ sensor.vendor }} {{ sensor.model }} ({{ sensor.serial }})
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.sensor_id" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center space-x-2">
                    <Checkbox id="direction_flipped" v-model:checked="form.direction_flipped" :tabindex="4" />
                    <Label for="direction_flipped">Direction Flipped</Label>
                </div>
                <p class="text-sm text-muted-foreground">Toggle this if the sensor's counting direction should be reversed (in/out becomes out/in)</p>
                <InputError :message="form.errors.direction_flipped" />
            </div>

            <div class="grid gap-2">
                <Label for="dateRange">Active Date & Time Range</Label>
                <VueDatePicker
                    id="dateRange"
                    v-model="dateRange"
                    :tabindex="5"
                    v-bind="datePickerConfig"
                    @update:model-value="handleDateRangeChange"
                />
                <InputError :message="form.errors.active_from" />
                <InputError :message="form.errors.active_to" />
                <p class="text-sm text-muted-foreground">Select when this assignment should be active. Times are in your local timezone.</p>
            </div>

            <Button :disabled="form.processing || !dateRange || !form.event_id || !form.area_id || !form.sensor_id" class="mt-2 w-full" type="submit">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>{{ props.assignment ? 'Update Assignment' : 'Create Assignment' }}</span>
            </Button>
        </div>
    </form>
</template>
