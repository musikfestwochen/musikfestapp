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
                    <p class="mb-1 text-xs text-gray-600">Next occurrences:</p>
                    <ul class="space-y-1 text-xs text-gray-700">
                        <li v-for="occurrence in nextOccurrences" :key="occurrence.toISOString()">
                            {{ formatLocalDateTime(occurrence.toISOString()) }}
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
import { createRRule, formatDateForInput, formatLocalDateTime, getNextRRuleOccurrences, rruleToText, validateRRule } from '@/utils/dateTimeHelpers';
import { computed, ref, watch } from 'vue';

interface Props {
    modelValue?: string;
    startDate: Date;
    endDate?: Date;
}

interface Emits {
    (e: 'update:modelValue', value: string): void;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    endDate: undefined,
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

// Week days for display
const weekDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Computed properties
const currentRRule = computed(() => {
    if (frequency.value === 'CUSTOM') {
        return customRRule.value;
    }

    const [hours, minutes] = resetTime.value.split(':').map(Number);
    const options: any = {
        interval: interval.value,
        byhour: hours,
        byminute: minutes,
    };

    if (frequency.value === 'WEEKLY' && selectedWeekDays.value.length > 0) {
        options.byweekday = selectedWeekDays.value;
    }

    if (endDateInput.value) {
        options.until = new Date(endDateInput.value + 'T23:59:59');
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

    // Try to parse common patterns
    if (rrule.includes('FREQ=DAILY')) {
        frequency.value = 'DAILY';
    } else if (rrule.includes('FREQ=WEEKLY')) {
        frequency.value = 'WEEKLY';
    } else if (rrule.includes('FREQ=MONTHLY')) {
        frequency.value = 'MONTHLY';
    } else {
        frequency.value = 'CUSTOM';
        customRRule.value = rrule;
        return;
    }

    // Parse interval
    const intervalMatch = rrule.match(/INTERVAL=(\d+)/);
    if (intervalMatch) {
        interval.value = parseInt(intervalMatch[1]);
    }

    // Parse time
    const hourMatch = rrule.match(/BYHOUR=(\d+)/);
    const minuteMatch = rrule.match(/BYMINUTE=(\d+)/);
    if (hourMatch && minuteMatch) {
        const hour = parseInt(hourMatch[1]).toString().padStart(2, '0');
        const minute = parseInt(minuteMatch[1]).toString().padStart(2, '0');
        resetTime.value = `${hour}:${minute}`;
    }

    // Parse weekdays for weekly frequency
    if (frequency.value === 'WEEKLY') {
        const weekdayMatch = rrule.match(/BYDAY=([^;]+)/);
        if (weekdayMatch) {
            // This is a simplified parser - in a real implementation you'd want more robust parsing
            selectedWeekDays.value = [1]; // Default to Monday for now
        }
    }

    // Parse end date
    const untilMatch = rrule.match(/UNTIL=([^;]+)/);
    if (untilMatch) {
        const until = new Date(untilMatch[1]);
        endDateInput.value = formatDateForInput(until);
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
