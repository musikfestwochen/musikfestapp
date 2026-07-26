import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import WidgetTimeRangeSelect from '../WidgetTimeRangeSelect.vue';

const stubs = {
    Select: {
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: '<select data-testid="range" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><slot /></select>',
    },
    SelectTrigger: { template: '<div><slot /></div>' },
    SelectValue: { template: '<div />' },
    SelectContent: { template: '<div><slot /></div>' },
    SelectItem: { props: ['value'], template: '<option :value="value"><slot /></option>' },
};

describe('WidgetTimeRangeSelect', () => {
    it('offers the shared history ranges', () => {
        const wrapper = mount(WidgetTimeRangeSelect, {
            props: { modelValue: '1h' },
            global: { stubs },
        });

        expect(wrapper.findAll('option').map((option) => [option.attributes('value'), option.text()])).toEqual([
            ['1h', 'Last hour'],
            ['3h', 'Last 3 hours'],
            ['6h', 'Last 6 hours'],
            ['12h', 'Last 12 hours'],
            ['24h', 'Last 24 hours'],
        ]);
    });

    it('emits the selected range', async () => {
        const wrapper = mount(WidgetTimeRangeSelect, {
            props: { modelValue: '1h' },
            global: { stubs },
        });

        await wrapper.get('[data-testid="range"]').setValue('6h');

        expect(wrapper.emitted('update:modelValue')).toEqual([['6h']]);
    });
});
