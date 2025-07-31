<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { Organization, PeoplecountArea, PeoplecountAreaRecurringReset } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    recurringReset?: PeoplecountAreaRecurringReset;
    organization: Organization;
    area: PeoplecountArea;
    timezones: Array<{ value: string; label: string }>;
}>();

const form = useForm({
    reset_value: props.recurringReset?.reset_value || '',
    reset_time: props.recurringReset?.reset_time || '',
    timezone: props.recurringReset?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone,
    notes: props.recurringReset?.notes || '',
});

// Find the selected timezone object for the Combobox
const selectedTimezone = computed({
    get: () => {
        return props.timezones.find((tz) => tz.value === form.timezone) || null;
    },
    set: (timezone: { value: string; label: string } | null) => {
        if (timezone) {
            form.timezone = timezone.value;
        }
    },
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

            <!-- Reset Time -->
            <div class="grid gap-2">
                <Label for="reset_time">Reset Time</Label>
                <Input id="reset_time" v-model="form.reset_time" :tabindex="2" required type="time" />
                <InputError :message="form.errors.reset_time" />
            </div>

            <!-- Timezone -->
            <div class="grid gap-2">
                <Label for="timezone">Timezone</Label>
                <Combobox v-model="selectedTimezone" by="label">
                    <ComboboxAnchor>
                        <div class="relative w-full items-center">
                            <ComboboxInput :display-value="(val) => val?.label ?? ''" :tabindex="3" placeholder="Select timezone..." />
                            <ComboboxTrigger class="absolute inset-y-0 end-0 flex items-center justify-center px-3">
                                <ChevronsUpDown class="size-4 text-muted-foreground" />
                            </ComboboxTrigger>
                        </div>
                    </ComboboxAnchor>

                    <ComboboxList>
                        <ComboboxEmpty> No timezone found. </ComboboxEmpty>

                        <ComboboxGroup>
                            <ComboboxItem v-for="timezone in props.timezones" :key="timezone.value" :value="timezone">
                                {{ timezone.label }}

                                <ComboboxItemIndicator>
                                    <Check :class="cn('ml-auto h-4 w-4')" />
                                </ComboboxItemIndicator>
                            </ComboboxItem>
                        </ComboboxGroup>
                    </ComboboxList>
                </Combobox>
                <InputError :message="form.errors.timezone" />
            </div>

            <!-- Notes -->
            <div class="grid gap-2">
                <Label for="notes">Notes</Label>
                <Textarea
                    id="notes"
                    v-model="form.notes"
                    :tabindex="4"
                    placeholder="Optional notes about this recurring reset schedule..."
                    rows="3"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <!-- Submit Button -->
            <Button :disabled="form.processing" class="mt-2 w-full" tabindex="5" type="submit">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>{{ props.recurringReset ? 'Update Recurring Reset' : 'Create Recurring Reset' }}</span>
            </Button>
        </div>
    </form>
</template>
