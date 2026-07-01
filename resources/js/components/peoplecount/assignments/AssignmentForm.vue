<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Organization, PeoplecountAssignment, PeoplecountEvent, PeoplecountSensor } from '@/types';
import { datetimeLocalToUTCString, utcStringToDatetimeLocal } from '@/utils/dateTimeHelpers';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    assignment?: PeoplecountAssignment;
    organization: Organization;
    events: PeoplecountEvent[];
    sensors: PeoplecountSensor[];
}>();

const form = useForm({
    event_id: props.assignment?.event_id.toString() || '',
    area_id: props.assignment?.area_id.toString() || '',
    sensor_id: props.assignment?.sensor_id.toString() || '',
    label: props.assignment?.label || '',
    direction_flipped: props.assignment?.direction_flipped || false,
    active_from: utcStringToDatetimeLocal(props.assignment?.active_from),
    active_to: utcStringToDatetimeLocal(props.assignment?.active_to),
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

const selectedEvent = computed(() => props.events.find((event) => event.id === parseInt(form.event_id.toString())));

const eventStartsAt = computed(() => utcStringToDatetimeLocal(selectedEvent.value?.starts_at));
const eventEndsAt = computed(() => utcStringToDatetimeLocal(selectedEvent.value?.ends_at));

const hasValidDateRange = () => Boolean(form.active_from && form.active_to && form.active_from < form.active_to);
const isWithinEventRange = () => !selectedEvent.value || (form.active_from >= eventStartsAt.value && form.active_to <= eventEndsAt.value);

const submitForm = () =>
    form.transform((data) => ({
        ...data,
        active_from: datetimeLocalToUTCString(data.active_from),
        active_to: datetimeLocalToUTCString(data.active_to),
    }));

const submit = () => {
    if (props.assignment && props.organization) {
        submitForm().put(
            route('peoplecount.assignments.update', {
                assignment: props.assignment.id,
                organization: props.organization.slug,
            }),
        );
    } else if (props.organization) {
        submitForm().post(route('peoplecount.assignments.store', { organization: props.organization.slug }));
    }
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
                <p v-if="!form.event_id" class="text-muted-foreground text-sm">Please select an event first</p>
            </div>

            <div class="grid gap-2">
                <Label for="sensor_id">Sensor</Label>
                <Select v-model="form.sensor_id" required>
                    <SelectTrigger id="sensor_id" :tabindex="3">
                        <SelectValue placeholder="Select a sensor" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="sensor in sensors" :key="sensor.id" :value="sensor.id.toString()">
                            {{
                                sensor.name
                                    ? `${sensor.name} (${sensor.vendor} ${sensor.model})`
                                    : `${sensor.vendor} ${sensor.model} (${sensor.serial})`
                            }}
                            <span v-if="sensor.organization && sensor.organization_id !== props.organization.id">
                                · shared by {{ sensor.organization.name }}</span
                            >
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.sensor_id" />
            </div>

            <div class="grid gap-2">
                <Label for="label">Label</Label>
                <Input id="label" v-model="form.label" :tabindex="4" placeholder="e.g. Entrance Tent A" type="text" />
                <InputError :message="form.errors.label" />
                <p class="text-muted-foreground text-sm">Optional. A description for this sensor deployment at this location.</p>
            </div>

            <div class="grid gap-2">
                <div class="flex items-center space-x-2">
                    <Checkbox id="direction_flipped" v-model:checked="form.direction_flipped" :tabindex="5" />
                    <Label for="direction_flipped">Direction Flipped</Label>
                </div>
                <p class="text-muted-foreground text-sm">Toggle this if the sensor's counting direction should be reversed (in/out becomes out/in)</p>
                <InputError :message="form.errors.direction_flipped" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="active_from">Active from</Label>
                    <Input id="active_from" v-model="form.active_from" :tabindex="6" required type="datetime-local" />
                    <InputError :message="form.errors.active_from" />
                </div>

                <div class="grid gap-2">
                    <Label for="active_to">Active to</Label>
                    <Input id="active_to" v-model="form.active_to" :tabindex="7" required type="datetime-local" />
                    <InputError :message="form.errors.active_to" />
                </div>
                <p class="text-muted-foreground text-sm sm:col-span-2">
                    Select when this assignment should be active. Times are in your local timezone.
                </p>
                <p v-if="form.active_from && form.active_to && form.active_from >= form.active_to" class="text-destructive text-sm sm:col-span-2">
                    Active to must be after active from.
                </p>
                <p v-if="selectedEvent && hasValidDateRange() && !isWithinEventRange()" class="text-destructive text-sm sm:col-span-2">
                    Assignment must be within the selected event: {{ eventStartsAt }} to {{ eventEndsAt }}.
                </p>
            </div>

            <Button
                :disabled="form.processing || !hasValidDateRange() || !isWithinEventRange() || !form.event_id || !form.area_id || !form.sensor_id"
                class="mt-2 w-full"
                type="submit"
            >
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>{{ props.assignment ? 'Update Assignment' : 'Create Assignment' }}</span>
            </Button>
        </div>
    </form>
</template>
