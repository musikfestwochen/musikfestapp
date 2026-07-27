import SensorHealthWidget from '@/components/SensorHealthWidget.vue';
import { shallowMount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Dashboard from '../Dashboard.vue';

vi.mock('@inertiajs/vue3', () => ({ Head: { template: '<div />' } }));

describe('Stage Safety dashboard', () => {
    it('renders Stage Safety widgets', () => {
        const wrapper = shallowMount(Dashboard, {
            props: { organization: { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' } },
            global: { stubs: { Layout: { template: '<main><slot /></main>' } } },
        });

        expect(Array.from(wrapper.find('.grid').element.children).map((element) => element.tagName.toLowerCase())).toEqual([
            'current-wind-widget-stub',
            'sensor-health-widget-stub',
            'wind-history-widget-stub',
            'lqi-history-widget-stub',
        ]);
        expect(wrapper.findComponent(SensorHealthWidget).props()).toMatchObject({ showPeoplecount: false, showStageSafety: true });
    });
});
