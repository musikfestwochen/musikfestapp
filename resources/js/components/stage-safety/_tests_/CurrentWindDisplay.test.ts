import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CurrentWindDisplay from '../CurrentWindDisplay.vue';

describe('CurrentWindDisplay', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-07-25T12:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders multiple sensors in km/h and labels stale readings as last known', () => {
        const wrapper = mount(CurrentWindDisplay, {
            props: {
                sensors: [
                    {
                        sensor: { id: 1, identifier: 'ABC123', name: 'Main Stage', location: 'Roof', stale_after_seconds: 300 },
                        status: 'archived',
                        latest_observed_at: '2026-07-25T11:59:00Z',
                        radio_diagnostics: null,
                        wind_average: {
                            kind: 'wind_average',
                            value: 5,
                            unit: 'm/s',
                            status: 'fresh',
                            observed_at: '2026-07-25T11:59:00Z',
                            received_at: '2026-07-25T11:59:01Z',
                            receipt_delay_seconds: 1,
                            window_seconds: 10,
                        },
                        wind_gust: null,
                    },
                    {
                        sensor: { id: 2, identifier: 'DEF456', name: 'Town Hall', location: null, stale_after_seconds: 300 },
                        status: 'fresh',
                        latest_observed_at: '2026-07-25T11:59:30Z',
                        radio_diagnostics: null,
                        wind_average: null,
                        wind_gust: {
                            kind: 'wind_gust',
                            value: 8.0556,
                            unit: 'm/s',
                            status: 'stale',
                            observed_at: '2026-07-25T11:50:00Z',
                            received_at: '2026-07-25T11:50:01Z',
                            receipt_delay_seconds: 1,
                            window_seconds: 10,
                        },
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('Roof');
        expect(wrapper.text()).not.toContain('Main Stage');
        expect(wrapper.text()).toContain('Town Hall');
        expect(wrapper.text()).toContain('18');
        expect(wrapper.text()).toContain('29');
        expect(wrapper.text()).toContain('Last known average');
        expect(wrapper.text()).toContain('Last known gust');
        expect(wrapper.text()).toContain('Observed 30 seconds ago');
        expect(wrapper.text()).not.toMatch(/\bfresh\b/i);
        expect(wrapper.text()).not.toContain('second period');
        expect(wrapper.findAll('article').every((article) => !article.classes().includes('border'))).toBe(true);
    });
});
