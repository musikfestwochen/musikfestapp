import { utcStringToDatetimeLocal } from '@/utils/dateTimeHelpers';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import AssignmentForm from '../assignments/AssignmentForm.vue';
import EventForm from '../events/EventForm.vue';
import SingleResetForm from '../resets/SingleResetForm.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initialData: Record<string, unknown>) => ({
        ...initialData,
        errors: {},
        processing: false,
        transform: vi.fn().mockReturnThis(),
        post: vi.fn(),
        put: vi.fn(),
    }),
}));

const InputStub = defineComponent({
    props: ['modelValue'],
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
        return () =>
            h('input', {
                ...attrs,
                value: props.modelValue,
                onInput: (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).value),
            });
    },
});

describe('datetime-local inputs', () => {
    const commonMountOptions = {
        global: {
            stubs: {
                InputError: true,
                Button: true,
                Input: InputStub,
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

    it('prefills event edit date range as datetime-local values', () => {
        const startsAt = '2024-07-25T12:30:00.000Z';
        const endsAt = '2024-07-25T14:30:00.000Z';

        const wrapper = mount(EventForm, {
            props: {
                organization: {
                    slug: 'test-org',
                    id: 0,
                    name: '',
                    created_at: '',
                    updated_at: '',
                },
                event: {
                    id: 1,
                    name: 'Test Event',
                    starts_at: startsAt,
                    ends_at: endsAt,
                    organization_id: 0,
                    created_at: '',
                    updated_at: '',
                },
            },
            ...commonMountOptions,
        });

        const inputs = wrapper.findAll('input[type="datetime-local"]');

        expect(inputs).toHaveLength(2);
        expect(inputs[0].attributes('value')).toBe(utcStringToDatetimeLocal(startsAt));
        expect(inputs[1].attributes('value')).toBe(utcStringToDatetimeLocal(endsAt));
    });

    it('prefills assignment edit active range as datetime-local values', () => {
        const activeFrom = '2024-07-25T12:30:00.000Z';
        const activeTo = '2024-07-25T14:30:00.000Z';

        const wrapper = mount(AssignmentForm, {
            props: {
                organization: {
                    id: 1,
                    slug: 'test-org',
                    name: '',
                    created_at: '',
                    updated_at: '',
                },
                events: [],
                sensors: [],
                assignment: {
                    id: 1,
                    event_id: 1,
                    area_id: 1,
                    sensor_id: 1,
                    label: null,
                    direction_flipped: false,
                    active_from: activeFrom,
                    active_to: activeTo,
                    created_at: '',
                    updated_at: '',
                },
            },
            ...commonMountOptions,
        });

        const inputs = wrapper.findAll('input[type="datetime-local"]');

        expect(inputs).toHaveLength(2);
        expect(inputs[0].attributes('value')).toBe(utcStringToDatetimeLocal(activeFrom));
        expect(inputs[1].attributes('value')).toBe(utcStringToDatetimeLocal(activeTo));
    });

    it('renders single reset effective date as datetime-local input', () => {
        const wrapper = mount(SingleResetForm, {
            props: {
                organization: {
                    slug: 'test-org',
                    id: 0,
                    name: '',
                    created_at: '',
                    updated_at: '',
                },
                area: {
                    id: 1,
                    name: '',
                    event_id: 0,
                    created_at: '',
                    updated_at: '',
                },
            },
            ...commonMountOptions,
        });

        expect(wrapper.findAll('input[type="datetime-local"]')).toHaveLength(1);
    });
});
