import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import WidgetShell from '../WidgetShell.vue';

describe('WidgetShell', () => {
    it('renders standard header and content slots', () => {
        const wrapper = mount(WidgetShell, {
            props: { title: 'Current Wind', subtitle: 'Average and gust speed' },
            slots: {
                icon: '<svg data-testid="icon" />',
                actions: '<button type="button">Change range</button>',
                default: '<p>Widget content</p>',
            },
        });

        expect(wrapper.get('h3').text()).toBe('Current Wind');
        expect(wrapper.text()).toContain('Average and gust speed');
        expect(wrapper.find('[data-testid="icon"]').exists()).toBe(true);
        expect(wrapper.get('button').text()).toBe('Change range');
        expect(wrapper.text()).toContain('Widget content');
    });

    it('renders a standard error and successful update footer', () => {
        const lastUpdated = new Date(2026, 6, 29, 13, 57, 30);
        const wrapper = mount(WidgetShell, {
            props: { title: 'Current Wind', error: 'Failed to load current wind.', lastUpdated },
        });

        expect(wrapper.get('[role="alert"]').text()).toBe('Failed to load current wind.');
        expect(wrapper.text()).toContain('Latest data:');
        expect(wrapper.get('time').attributes('datetime')).toBe(lastUpdated.toISOString());
        expect(wrapper.get('time').text()).toBe('29.07.2026 13:57:30');
        expect(wrapper.get('time').element.closest('.pt-4')).not.toBeNull();
    });

    it('omits optional elements and uses one grid cell by default', () => {
        const wrapper = mount(WidgetShell, { props: { title: 'Sensor Health' } });

        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper.find('time').exists()).toBe(false);
        expect(wrapper.classes()).not.toContain('col-span-full');
    });

    it('supports full-width widgets', () => {
        const wrapper = mount(WidgetShell, { props: { title: 'Wind History', span: 'full' } });

        expect(wrapper.classes()).toContain('col-span-full');
    });
});
