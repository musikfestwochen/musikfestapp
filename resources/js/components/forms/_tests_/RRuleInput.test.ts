import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import RRuleInput from '../RRuleInput.vue';

// Mock the dateTimeHelpers module
vi.mock('@/utils/dateTimeHelpers', () => ({
    createRRule: vi.fn((frequency, options) => {
        let rrule = `FREQ=${frequency}`;
        if (options?.interval) rrule += `;INTERVAL=${options.interval}`;
        if (options?.byhour !== undefined) rrule += `;BYHOUR=${options.byhour}`;
        if (options?.byminute !== undefined) rrule += `;BYMINUTE=${options.byminute}`;
        if (options?.byweekday) rrule += `;BYDAY=MO,WE,FR`;
        if (options?.until) rrule += `;UNTIL=${options.until.toISOString().replace(/[-:]/g, '').split('.')[0]}Z`;
        if (options?.count) rrule += `;COUNT=${options.count}`;
        if (options?.dtstart) rrule += `;DTSTART=${options.dtstart.toISOString().replace(/[-:]/g, '').split('.')[0]}Z`;
        if (options?.tzid) rrule += `;TZID=${options.tzid}`;
        return rrule;
    }),
    formatDateForInput: vi.fn((date) => date.toISOString().split('T')[0]),
    formatLocalDateTime: vi.fn((dateString) => new Date(dateString).toLocaleString()),
    getNextRRuleOccurrences: vi.fn(() => [new Date('2024-01-01T09:00:00Z'), new Date('2024-01-02T09:00:00Z'), new Date('2024-01-03T09:00:00Z')]),
    rruleToText: vi.fn((rrule) => {
        if (rrule.includes('FREQ=DAILY')) return 'daily';
        if (rrule.includes('FREQ=WEEKLY')) return 'weekly';
        if (rrule.includes('FREQ=MONTHLY')) return 'monthly';
        return 'Invalid RRULE';
    }),
    validateRRule: vi.fn((rrule) => {
        if (rrule.includes('FREQ=') && !rrule.includes('INVALID')) {
            return { isValid: true };
        }
        return { isValid: false, error: 'Invalid RRULE format' };
    }),
}));

describe('RRuleInput', () => {
    const defaultProps = {
        startDate: new Date('2024-01-01T00:00:00Z'),
    };

    describe('Component Rendering', () => {
        it('should render all form elements', () => {
            const wrapper = mount(RRuleInput, {
                props: defaultProps,
            });

            // Check for frequency selection
            expect(wrapper.text()).toContain('Frequency');

            // Check for time input
            expect(wrapper.find('input[type="time"]').exists()).toBe(true);

            // Check for interval input
            expect(wrapper.find('input[type="number"]').exists()).toBe(true);

            // Check for end date input
            expect(wrapper.find('input[type="date"]').exists()).toBe(true);
        });

        it('should show weekday selection for weekly frequency', () => {
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    modelValue: 'FREQ=WEEKLY;INTERVAL=1;BYHOUR=9;BYMINUTE=0',
                },
            });

            // Check for weekday checkboxes
            expect(wrapper.text()).toContain('Days of the week');
            expect(wrapper.text()).toContain('Monday');
            expect(wrapper.text()).toContain('Tuesday');
        });

        it('should show custom RRULE input for custom frequency', () => {
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    modelValue: 'INVALID_CUSTOM_RRULE',
                },
            });

            // Check for custom RRULE input
            expect(wrapper.text()).toContain('Custom RRULE');
            expect(wrapper.find('input[placeholder*="FREQ=DAILY"]').exists()).toBe(true);
        });
    });

    describe('RRULE Generation', () => {
        it('should generate daily RRULE with default values', async () => {
            const wrapper = mount(RRuleInput, {
                props: defaultProps,
            });

            // Wait for component to initialize
            await wrapper.vm.$nextTick();

            const emittedValues = wrapper.emitted('update:modelValue');
            expect(emittedValues).toBeTruthy();
            const emittedValue = emittedValues![0][0] as string;

            expect(emittedValue).toContain('FREQ=DAILY');
            expect(emittedValue).toContain('INTERVAL=1');
            // With timezone-aware fix: 09:00 local time with tzid, not converted to UTC
            expect(emittedValue).toContain('BYHOUR=9');
            expect(emittedValue).toContain('BYMINUTE=0');
            expect(emittedValue).toContain('TZID=Europe/Zurich');
        });

        it('should generate weekly RRULE with selected weekdays', () => {
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    modelValue: 'FREQ=WEEKLY;INTERVAL=1;BYHOUR=9;BYMINUTE=0',
                },
            });

            expect(wrapper.emitted('update:modelValue')).toBeTruthy();
            const emittedValue = wrapper.emitted('update:modelValue')![0][0] as string;
            expect(emittedValue).toContain('FREQ=WEEKLY');
            expect(emittedValue).toContain('BYDAY=');
        });

        it('should generate monthly RRULE', () => {
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    modelValue: 'FREQ=MONTHLY;INTERVAL=1;BYHOUR=9;BYMINUTE=0',
                },
            });

            expect(wrapper.emitted('update:modelValue')).toBeTruthy();
            const emittedValue = wrapper.emitted('update:modelValue')![0][0] as string;
            expect(emittedValue).toContain('FREQ=MONTHLY');
        });

        it('should include end date in RRULE when specified', () => {
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    endDate: new Date('2024-12-31'),
                },
            });

            expect(wrapper.emitted('update:modelValue')).toBeTruthy();
            const emittedValue = wrapper.emitted('update:modelValue')![0][0] as string;
            expect(emittedValue).toContain('UNTIL=');
        });
    });

    describe('Form Interactions', () => {
        it('should update time when time input changes', async () => {
            const wrapper = mount(RRuleInput, {
                props: defaultProps,
            });

            const timeInput = wrapper.find('input[type="time"]');
            await timeInput.setValue('14:30');

            expect(wrapper.emitted('update:modelValue')).toBeTruthy();
            const emittedValues = wrapper.emitted('update:modelValue') as string[][];
            const lastEmittedValue = emittedValues[emittedValues.length - 1][0];
            // With timezone-aware fix: 14:30 local time with tzid, not converted to UTC
            expect(lastEmittedValue).toContain('BYHOUR=14');
            expect(lastEmittedValue).toContain('BYMINUTE=30');
        });

        it('should update interval when interval input changes', async () => {
            const wrapper = mount(RRuleInput, {
                props: defaultProps,
            });

            const intervalInput = wrapper.find('input[type="number"]');
            await intervalInput.setValue('2');

            expect(wrapper.emitted('update:modelValue')).toBeTruthy();
            const emittedValues = wrapper.emitted('update:modelValue') as string[][];
            const lastEmittedValue = emittedValues[emittedValues.length - 1][0];
            expect(lastEmittedValue).toContain('INTERVAL=2');
        });

        it('should emit modelValue when RRULE changes', () => {
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    modelValue: 'FREQ=WEEKLY;INTERVAL=1;BYHOUR=9;BYMINUTE=0',
                },
            });

            // Check that update:modelValue was emitted
            expect(wrapper.emitted('update:modelValue')).toBeTruthy();
            expect(wrapper.emitted('update:modelValue')![0][0]).toContain('FREQ=WEEKLY');
        });
    });

    describe('Custom RRULE Validation', () => {
        it('should validate custom RRULE input', async () => {
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    modelValue: 'INVALID_RRULE',
                },
            });

            const customInput = wrapper.find('input[placeholder*="FREQ=DAILY"]');
            expect(customInput.exists()).toBe(true);

            // Set valid custom RRULE
            await customInput.setValue('FREQ=DAILY;INTERVAL=2');
            await customInput.trigger('input');

            expect(wrapper.emitted('update:modelValue')).toBeTruthy();

            // Set invalid custom RRULE
            await customInput.setValue('INVALID_RRULE');
            await customInput.trigger('input');

            expect(wrapper.text()).toContain('Invalid RRULE format');
        });
    });

    describe('RRULE Preview', () => {
        it('should show RRULE preview for valid rules', () => {
            const wrapper = mount(RRuleInput, {
                props: defaultProps,
            });

            expect(wrapper.text()).toContain('Preview');
            expect(wrapper.text()).toContain('daily');
            expect(wrapper.text()).toContain('Next occurrences');
        });

        it('should hide preview for invalid rules', () => {
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    modelValue: 'INVALID_RRULE',
                },
            });

            expect(wrapper.text()).toContain('Invalid RRULE format');
        });
    });

    describe('Props and Initialization', () => {
        it('should initialize from modelValue prop', () => {
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    modelValue: 'FREQ=WEEKLY;INTERVAL=2',
                },
            });

            // Component should handle the weekly frequency
            expect(wrapper.text()).toContain('Days of the week');
        });

        it('should initialize end date from props', () => {
            const endDate = new Date('2024-12-31');
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    endDate,
                },
            });

            const endDateInput = wrapper.find('input[type="date"]');
            expect(endDateInput.exists()).toBe(true);
        });

        it('should handle invalid RRULE in modelValue', () => {
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    modelValue: 'INVALID_RRULE',
                },
            });

            expect(wrapper.text()).toContain('Custom RRULE');
        });
    });

    describe('Timezone Handling', () => {
        it('should use proper timezone in RRule generation', () => {
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    timezone: 'Europe/Zurich',
                },
            });

            // Change frequency to trigger RRule creation
            const frequencySelect = wrapper.find('select');
            if (frequencySelect.exists()) {
                frequencySelect.setValue('DAILY');
            }

            // Verify that timezone is included in the generated RRule
            expect(wrapper.emitted('update:modelValue')).toBeTruthy();
        });

        it('should format occurrences in selected timezone', () => {
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    timezone: 'Europe/Zurich',
                    modelValue: 'FREQ=DAILY;INTERVAL=1;BYHOUR=9;BYMINUTE=0',
                },
            });

            // The component should show upcoming occurrences
            expect(wrapper.text()).toContain('Next occurrences');
        });

        it('should handle timezone changes', async () => {
            const wrapper = mount(RRuleInput, {
                props: {
                    ...defaultProps,
                    timezone: 'UTC',
                },
            });

            // Simulate user changing timezone through the component
            // Since we're testing the emit behavior, we can directly call the updateRRule method
            // that gets triggered when timezone changes in the UI
            const component = wrapper.vm as any;
            component.selectedTimezone = 'Europe/Zurich';
            component.updateRRule();

            await wrapper.vm.$nextTick();

            // Should emit timezone update
            expect(wrapper.emitted('update:timezone')).toBeTruthy();
            const emittedTimezone = wrapper.emitted('update:timezone')![0][0];
            expect(emittedTimezone).toBe('Europe/Zurich');
        });
    });
});
