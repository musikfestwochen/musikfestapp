import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import WidgetHistoryControls from '../WidgetHistoryControls.vue';

const stubs = {
    WidgetTimeRangeSelect: {
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: '<button data-testid="range" @click="$emit(\'update:modelValue\', \'6h\')">{{ modelValue }}</button>',
    },
    Switch: {
        props: ['modelValue', 'id'],
        emits: ['update:modelValue'],
        template: '<button :id="id" role="switch" :aria-checked="modelValue" @click="$emit(\'update:modelValue\', !modelValue)">Statistics</button>',
    },
    Label: { template: '<label v-bind="$attrs"><slot /></label>' },
};

describe('WidgetHistoryControls', () => {
    it('emits range and statistics changes independently', async () => {
        const wrapper = mount(WidgetHistoryControls, {
            props: { timeRange: '1h', statisticsEnabled: false },
            global: { stubs },
        });

        await wrapper.get('[data-testid="range"]').trigger('click');
        expect(wrapper.emitted('update:timeRange')).toEqual([['6h']]);
        expect(wrapper.emitted('update:statisticsEnabled')).toBeUndefined();

        await wrapper.get('[role="switch"]').trigger('click');
        expect(wrapper.emitted('update:statisticsEnabled')).toEqual([[true]]);
        expect(wrapper.get('label').attributes('for')).toBe(wrapper.get('[role="switch"]').attributes('id'));
        expect(wrapper.get('[role="switch"]').element.parentElement?.classList).not.toContain('border');
    });
});
