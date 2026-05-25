import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AssignmentForm from '../assignments/AssignmentForm.vue';
import EventForm from '../events/EventForm.vue';
import SingleResetForm from '../resets/SingleResetForm.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initialData: Record<string, unknown>) => ({
        ...initialData,
        errors: {},
        processing: false,
        post: vi.fn(),
        put: vi.fn(),
    }),
}));

vi.mock('@vuepic/vue-datepicker', () => ({
    WeekStart: {
        Monday: 1,
    },
    VueDatePicker: {
        name: 'VueDatePicker',
        props: {
            weekStart: {
                type: Number,
                default: null,
            },
        },
        template: '<div data-testid="datepicker" :data-week-start="weekStart" />',
    },
}));

describe('Datepicker week start compatibility', () => {
    const commonMountOptions = {
        global: {
            stubs: {
                InputError: true,
                Button: true,
                Input: true,
                Label: true,
                Checkbox: true,
                Textarea: true,
                Select: true,
                SelectContent: true,
                SelectItem: true,
                SelectTrigger: true,
                SelectValue: true,
                LoaderCircle: true,
            },
        },
    };

    it('keeps Monday as week start in EventForm', () => {
        const wrapper = mount(EventForm, {
            props: {
                organization: {
                    slug: 'test-org',
                },
            },
            ...commonMountOptions,
        });

        expect(wrapper.find('[data-testid="datepicker"]').attributes('data-week-start')).toBe('1');
    });

    it('keeps Monday as week start in AssignmentForm', () => {
        const wrapper = mount(AssignmentForm, {
            props: {
                organization: {
                    slug: 'test-org',
                },
                events: [],
                sensors: [],
            },
            ...commonMountOptions,
        });

        expect(wrapper.find('[data-testid="datepicker"]').attributes('data-week-start')).toBe('1');
    });

    it('keeps Monday as week start in SingleResetForm', () => {
        const wrapper = mount(SingleResetForm, {
            props: {
                organization: {
                    slug: 'test-org',
                },
                area: {
                    id: 1,
                },
            },
            ...commonMountOptions,
        });

        expect(wrapper.find('[data-testid="datepicker"]').attributes('data-week-start')).toBe('1');
    });
});
