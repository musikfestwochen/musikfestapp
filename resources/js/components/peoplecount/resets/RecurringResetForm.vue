<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Event, Organization, PeoplecountArea, PeoplecountAreaRecurringReset } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import RRuleInput from './RRuleInput.vue';

const props = defineProps<{
    recurringReset?: PeoplecountAreaRecurringReset;
    organization: Organization;
    area: PeoplecountArea;
    events: Event[];
}>();

const form = useForm({
    event_id: props.recurringReset?.event_id || null,
    reset_value: props.recurringReset?.reset_value || '',
    rrule: props.recurringReset?.rrule || '',
    timezone: props.recurringReset?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone,
    notes: props.recurringReset?.notes || '',
});

// Get the selected event for start/end dates
const selectedEvent = computed(() => {
    if (!form.event_id) return null;
    return props.events.find((event) => event.id === form.event_id) || null;
});

// Default start date (current date if no event selected)
const startDate = computed(() => {
    if (selectedEvent.value?.start_date) {
        return new Date(selectedEvent.value.start_date);
    }
    return new Date();
});

// End date from event (optional)
const endDate = computed(() => {
    if (selectedEvent.value?.end_date) {
        return new Date(selectedEvent.value.end_date);
    }
    return undefined;
});

const submit = () => {
    if (props.recurringReset && props.organization && props.area) {
        form.put(
            route('peoplecount.areas.recurring-resets.update', {
                organization: props.organization.slug,
                area: props.area.id,
                recurring_reset: props.recurringReset.id,
            }),
        );
    } else if (props.organization && props.area) {
        form.post(
            route('peoplecount.areas.recurring-resets.store', {
                organization: props.organization.slug,
                area: props.area.id,
            }),
        );
    }
};
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid max-w-2xl gap-6">
            <!-- Event Selection -->
            <div class="grid gap-2">
                <Label for="event">Event</Label>
                <Select v-model="form.event_id">
                    <SelectTrigger>
                        <SelectValue placeholder="Select an event (optional)" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="null">No event (area-wide reset)</SelectItem>
                        <SelectItem v-for="event in events" :key="event.id" :value="event.id">
                            {{ event.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.event_id" />
            </div>

            <!-- Reset Value -->
            <div class="grid gap-2">
                <Label for="reset_value">Reset Value</Label>
                <Input
                    id="reset_value"
                    v-model.number="form.reset_value"
                    :tabindex="1"
                    autocomplete="off"
                    min="0"
                    placeholder="0"
                    required
                    step="1"
                    type="number"
                />
                <InputError :message="form.errors.reset_value" />
            </div>

            <!-- RRULE Input -->
            <div class="grid gap-2">
                <Label>Recurrence Schedule</Label>
                <RRuleInput v-model="form.rrule" :end-date="endDate" :start-date="startDate" />
                <InputError :message="form.errors.rrule" />
            </div>

            <!-- Notes -->
            <div class="grid gap-2">
                <Label for="notes">Notes</Label>
                <Textarea
                    id="notes"
                    v-model="form.notes"
                    :tabindex="3"
                    placeholder="Optional notes about this recurring reset schedule..."
                    rows="3"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <!-- Submit Button -->
            <Button :disabled="form.processing" class="mt-2 w-full" tabindex="4" type="submit">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>{{ props.recurringReset ? 'Update Recurring Reset' : 'Create Recurring Reset' }}</span>
            </Button>
        </div>
    </form>
</template>
