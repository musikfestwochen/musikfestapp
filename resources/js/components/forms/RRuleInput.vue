<template>
    <div class="space-y-4">
        <!-- Frequency Selection -->
        <div>
            <Label for="frequency">Frequency</Label>
            <Select v-model="frequency" @update:model-value="updateRRule">
                <SelectTrigger>
                    <SelectValue placeholder="Select frequency" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="DAILY">Daily</SelectItem>
                    <SelectItem value="WEEKLY">Weekly</SelectItem>
                    <SelectItem value="MONTHLY">Monthly</SelectItem>
                    <SelectItem value="CUSTOM">Custom</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Time Selection -->
        <div>
            <Label for="time">Reset Time</Label>
            <Input id="time" v-model="resetTime" type="time" @input="updateRRule" />
        </div>

        <!-- Timezone Selection -->
        <div>
            <Label for="timezone">Timezone</Label>
            <Select v-model="selectedTimezone" @update:model-value="updateRRule">
                <SelectTrigger>
                    <SelectValue placeholder="Select timezone" />
                </SelectTrigger>
                <SelectContent class="max-h-60">
                    <SelectItem v-for="tz in availableTimezones" :key="tz" :value="tz">
                        {{ formatTimezoneLabel(tz) }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Interval Selection (for non-custom frequencies) -->
        <div v-if="frequency !== 'CUSTOM'">
            <Label for="interval">Repeat every</Label>
            <div class="flex items-center space-x-2">
                <Input id="interval" v-model.number="interval" class="w-20" max="365" min="1" type="number" @input="updateRRule" />
                <span class="text-sm text-gray-600">
                    {{ getIntervalLabel() }}
                </span>
            </div>
        </div>

        <!-- Day of Week Selection (for weekly frequency) -->
        <div v-if="frequency === 'WEEKLY'">
            <Label>Days of the week</Label>
            <div class="mt-2 flex flex-wrap gap-2">
                <div v-for="(day, index) in weekDays" :key="day" class="flex items-center space-x-2">
                    <Checkbox :id="`day-${index}`" :checked="selectedWeekDays.includes(index)" @update:checked="toggleWeekDay(index)" />
                    <Label :for="`day-${index}`" class="text-sm">{{ day }}</Label>
                </div>
            </div>
        </div>

        <!-- End Date Selection -->
        <div>
            <Label for="endDate">End Date (Optional)</Label>
            <Input id="endDate" v-model="endDateInput" :min="formatDateForInput(startDate)" type="date" @input="updateRRule" />
        </div>

        <!-- Custom RRULE Input -->
        <div v-if="frequency === 'CUSTOM'">
            <Label for="customRRule">Custom RRULE</Label>
            <Input id="customRRule" v-model="customRRule" placeholder="FREQ=DAILY;INTERVAL=1" @input="validateCustomRRule" />
            <p v-if="customRRuleError" class="mt-1 text-sm text-red-600">
                {{ customRRuleError }}
            </p>
        </div>

        <!-- RRULE Preview -->
        <div v-if="isValidRRule">
            <Label>Preview</Label>
            <div class="rounded-md bg-gray-50 p-3">
                <p class="mb-2 text-sm font-medium">{{ rruleText }}</p>
                <div v-if="nextOccurrences.length > 0">
                    <p class="mb-1 text-xs text-gray-600">Next occurrences ({{ selectedTimezone }}):</p>
                    <ul class="space-y-1 text-xs text-gray-700">
                        <li v-for="occurrence in nextOccurrences" :key="occurrence.toISOString()">
                            {{ formatOccurrenceInTimezone(occurrence) }}
                        </li>
                    </ul>
                </div>
                <p v-else class="text-xs text-gray-500">No upcoming occurrences</p>
            </div>
        </div>

        <!-- Validation Error -->
        <div v-if="!isValidRRule && modelValue">
            <p class="text-sm text-red-600">Invalid RRULE format</p>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { createRRule, formatDateForInput, getNextRRuleOccurrences, rruleToText, validateRRule } from '@/utils/dateTimeHelpers';
import { RRule } from 'rrule';
import { computed, ref, watch } from 'vue';

interface Props {
    modelValue?: string;
    startDate: Date;
    endDate?: Date;
    timezone?: string;
}

interface Emits {
    (e: 'update:modelValue', value: string): void;
    (e: 'update:timezone', value: string): void;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    endDate: undefined,
    timezone: () => Intl.DateTimeFormat().resolvedOptions().timeZone,
});

const emit = defineEmits<Emits>();

// Reactive data
const frequency = ref<'DAILY' | 'WEEKLY' | 'MONTHLY' | 'CUSTOM'>('DAILY');
const resetTime = ref('09:00');
const interval = ref(1);
const selectedWeekDays = ref<number[]>([1]); // Default to Monday
const endDateInput = ref('');
const customRRule = ref('');
const customRRuleError = ref('');
const selectedTimezone = ref(props.timezone);

// Available timezones
const availableTimezones = ref<string[]>([]);

// Initialize timezones
try {
    availableTimezones.value = Intl.supportedValuesOf('timeZone');
} catch {
    // Fallback for older browsers
    availableTimezones.value = [
        'UTC',
        'America/New_York',
        'America/Chicago',
        'America/Denver',
        'America/Los_Angeles',
        'Europe/London',
        'Europe/Paris',
        'Europe/Berlin',
        'Europe/Zurich',
        'Asia/Tokyo',
        'Asia/Shanghai',
        'Australia/Sydney',
    ];
}

// Week days for display
const weekDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Computed properties
const currentRRule = computed(() => {
    if (frequency.value === 'CUSTOM') {
        return customRRule.value;
    }

    const [hours, minutes] = resetTime.value.split(':').map(Number);

    // Create proper dtstart with timezone for RRule
    // Use the start date from props as base, but with the specified time
    const startDate = new Date(props.startDate);
    const dtstart = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate(), hours, minutes, 0, 0);

    const options: any = {
        interval: interval.value,
        dtstart: dtstart,
        tzid: selectedTimezone.value, // Let RRule handle timezone properly
        byhour: hours, // Use local time - RRule will handle timezone conversion
        byminute: minutes,
    };

    if (frequency.value === 'WEEKLY' && selectedWeekDays.value.length > 0) {
        options.byweekday = selectedWeekDays.value;
    }

    if (endDateInput.value) {
        // Create proper until date in the selected timezone
        const untilDate = new Date(endDateInput.value + 'T23:59:59');
        options.until = untilDate;
    }

    return createRRule(frequency.value, options);
});

const isValidRRule = computed(() => {
    if (!currentRRule.value) return false;
    return validateRRule(currentRRule.value).isValid;
});

const rruleText = computed(() => {
    if (!isValidRRule.value) return '';
    return rruleToText(currentRRule.value);
});

const nextOccurrences = computed(() => {
    if (!isValidRRule.value) return [];
    return getNextRRuleOccurrences(currentRRule.value, props.startDate, 5);
});

// Methods
function getIntervalLabel(): string {
    const labels = {
        DAILY: interval.value === 1 ? 'day' : 'days',
        WEEKLY: interval.value === 1 ? 'week' : 'weeks',
        MONTHLY: interval.value === 1 ? 'month' : 'months',
    };
    return labels[frequency.value] || '';
}

function formatTimezoneLabel(timezone: string): string {
    try {
        const now = new Date();
        const formatter = new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            timeZoneName: 'short',
        });
        const parts = formatter.formatToParts(now);
        const timeZoneName = parts.find((part) => part.type === 'timeZoneName')?.value || '';

        // Format: "Europe/Zurich (CET)"
        return `${timezone} (${timeZoneName})`;
    } catch {
        return timezone;
    }
}

function formatOccurrenceInTimezone(occurrence: Date): string {
    try {
        // Format the occurrence date in the selected timezone using proper locale formatting
        const formatter = new Intl.DateTimeFormat('en-GB', {
            timeZone: selectedTimezone.value,
            day: '2-digit',
            month: '2-digit',
            year: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        });

        const parts = formatter.formatToParts(occurrence);
        const day = parts.find((p) => p.type === 'day')?.value || '00';
        const month = parts.find((p) => p.type === 'month')?.value || '00';
        const year = parts.find((p) => p.type === 'year')?.value || '00';
        const hour = parts.find((p) => p.type === 'hour')?.value || '00';
        const minute = parts.find((p) => p.type === 'minute')?.value || '00';

        return `${day}.${month}.${year}, ${hour}:${minute}`;
    } catch (error) {
        console.warn('Failed to format occurrence in timezone:', error);
        // Fallback to ISO string format
        return occurrence.toISOString().slice(0, 16).replace('T', ' ');
    }
}

function toggleWeekDay(dayIndex: number): void {
    const index = selectedWeekDays.value.indexOf(dayIndex);
    if (index > -1) {
        selectedWeekDays.value.splice(index, 1);
    } else {
        selectedWeekDays.value.push(dayIndex);
    }
    selectedWeekDays.value.sort();
    updateRRule();
}

function updateRRule(): void {
    if (frequency.value !== 'CUSTOM') {
        customRRuleError.value = '';
    }
    emit('update:modelValue', currentRRule.value);
    emit('update:timezone', selectedTimezone.value);
}

function validateCustomRRule(): void {
    const validation = validateRRule(customRRule.value);
    customRRuleError.value = validation.isValid ? '' : validation.error || 'Invalid RRULE format';
    emit('update:modelValue', customRRule.value);
}

// Initialize from props
function initializeFromRRule(rrule: string): void {
    if (!rrule) return;

    const validation = validateRRule(rrule);
    if (!validation.isValid) {
        frequency.value = 'CUSTOM';
        customRRule.value = rrule;
        return;
    }

    try {
        // Use RRule's built-in parsing instead of manual regex parsing
        const rule = RRule.fromString(rrule);
        const options = rule.options;

        // Parse frequency
        if (options.freq === RRule.DAILY) {
            frequency.value = 'DAILY';
        } else if (options.freq === RRule.WEEKLY) {
            frequency.value = 'WEEKLY';
        } else if (options.freq === RRule.MONTHLY) {
            frequency.value = 'MONTHLY';
        } else {
            frequency.value = 'CUSTOM';
            customRRule.value = rrule;
            return;
        }

        // Parse interval
        interval.value = options.interval || 1;

        // Parse time from dtstart if available, otherwise from byhour/byminute
        if (options.dtstart) {
            const dtstart = options.dtstart;
            const hour = dtstart.getHours().toString().padStart(2, '0');
            const minute = dtstart.getMinutes().toString().padStart(2, '0');
            resetTime.value = `${hour}:${minute}`;
        } else if (options.byhour !== undefined && options.byminute !== undefined) {
            const hour = options.byhour.toString().padStart(2, '0');
            const minute = options.byminute.toString().padStart(2, '0');
            resetTime.value = `${hour}:${minute}`;
        }

        // Parse weekdays for weekly frequency
        if (frequency.value === 'WEEKLY' && options.byweekday) {
            selectedWeekDays.value = Array.isArray(options.byweekday) ? options.byweekday : [options.byweekday];
        }

        // Parse end date
        if (options.until) {
            endDateInput.value = formatDateForInput(options.until);
        }
    } catch (error) {
        console.warn('Failed to parse RRule with library, falling back to custom:', error);
        frequency.value = 'CUSTOM';
        customRRule.value = rrule;
    }
}

// Watch for prop changes
watch(
    () => props.modelValue,
    (newValue) => {
        if (newValue && newValue !== currentRRule.value) {
            initializeFromRRule(newValue);
        }
    },
    { immediate: true },
);

// Watch for timezone prop changes
watch(
    () => props.timezone,
    (newTimezone) => {
        if (newTimezone && newTimezone !== selectedTimezone.value) {
            selectedTimezone.value = newTimezone;
        }
    },
    { immediate: true },
);

// Watch for end date from props
watch(
    () => props.endDate,
    (newEndDate) => {
        if (newEndDate && !endDateInput.value) {
            endDateInput.value = formatDateForInput(newEndDate);
        }
    },
    { immediate: true },
);

// Emit initial value when component is mounted
watch(
    currentRRule,
    (newValue) => {
        if (newValue) {
            emit('update:modelValue', newValue);
        }
    },
    { immediate: true },
);
</script>
