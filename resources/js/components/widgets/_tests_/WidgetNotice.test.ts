import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import WidgetNotice from '../WidgetNotice.vue';

describe('WidgetNotice', () => {
    it('renders accessible errors in light and dark themes', () => {
        const wrapper = mount(WidgetNotice, {
            props: { variant: 'error' },
            slots: { default: 'Failed to load.' },
        });

        expect(wrapper.attributes('role')).toBe('alert');
        expect(wrapper.text()).toBe('Failed to load.');
        expect(wrapper.classes()).toContain('bg-rose-50/70');
        expect(wrapper.classes()).toContain('dark:bg-rose-950/20');
    });

    it('renders accessible stale-data warnings', () => {
        const wrapper = mount(WidgetNotice, {
            props: { variant: 'warning' },
            slots: { default: 'Data may be stale.' },
        });

        expect(wrapper.attributes('role')).toBe('status');
        expect(wrapper.text()).toBe('Data may be stale.');
        expect(wrapper.classes()).toContain('bg-amber-50');
        expect(wrapper.classes()).toContain('dark:bg-amber-950/30');
    });
});
