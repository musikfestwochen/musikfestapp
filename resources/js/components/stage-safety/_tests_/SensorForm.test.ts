import type { StageSafetySensorFormData } from '@/types';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import SensorForm from '../sensors/SensorForm.vue';

const InputStub = defineComponent({
    props: {
        modelValue: {
            type: [String, Number],
            default: '',
        },
    },
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

const form: StageSafetySensorFormData = {
    manufacturer: 'broadweigh',
    model: 'BW-WSS',
    identifier: 'FF1234',
    name: null,
    location: null,
    stale_after_seconds: 300,
};

describe('Stage Safety SensorForm', () => {
    it('renders controlled sensor types and emits field changes', async () => {
        const wrapper = mount(SensorForm, {
            props: {
                form: { ...form },
                sensorTypes: [
                    { manufacturer: 'broadweigh', model: 'BW-WSS', label: 'BroadWeigh BW-WSS' },
                    { manufacturer: 'future', model: 'FUTURE-1', label: 'Future Sensor' },
                ],
                submitLabel: 'Create Sensor',
            },
            global: {
                stubs: {
                    Button: { template: '<button><slot /></button>' },
                    HoverCard: { template: '<div><slot /></div>' },
                    HoverCardContent: { template: '<div><slot /></div>' },
                    HoverCardTrigger: { template: '<div><slot /></div>' },
                    Input: InputStub,
                    InputError: true,
                    Label: { template: '<label><slot /></label>' },
                    LoaderCircle: true,
                },
            },
        });

        expect(wrapper.findAll('option')).toHaveLength(2);
        expect(wrapper.find('img').attributes('alt')).toContain('Device ID highlighted');
        expect(wrapper.find('[aria-label="Where to find the BroadWeigh Device ID"]').exists()).toBe(true);
        await wrapper.find('select').setValue('1');
        await wrapper.find('#identifier').setValue('ab1234');

        expect(wrapper.emitted('change')).toEqual([[{ manufacturer: 'future', model: 'FUTURE-1' }], [{ identifier: 'AB1234' }]]);
    });

    it('emits submit without mutating its form prop', async () => {
        const input = { ...form };
        const wrapper = mount(SensorForm, {
            props: {
                form: input,
                sensorTypes: [{ manufacturer: 'broadweigh', model: 'BW-WSS', label: 'BroadWeigh BW-WSS' }],
                submitLabel: 'Create Sensor',
            },
            global: {
                stubs: {
                    Button: { template: '<button type="submit"><slot /></button>' },
                    HoverCard: { template: '<div><slot /></div>' },
                    HoverCardContent: { template: '<div><slot /></div>' },
                    HoverCardTrigger: { template: '<div><slot /></div>' },
                    Input: InputStub,
                    InputError: true,
                    Label: { template: '<label><slot /></label>' },
                    LoaderCircle: true,
                },
            },
        });

        await wrapper.find('form').trigger('submit');

        expect(wrapper.emitted('submit')).toHaveLength(1);
        expect(input).toEqual(form);
    });
});
