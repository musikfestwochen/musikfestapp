import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import SensorHealthWidget from '../SensorHealthWidget.vue';

const mocks = vi.hoisted(() => ({
    peoplecountGet: vi.fn(),
    stageSafetyGet: vi.fn(),
    peoplecountRequest: { processing: false, get: vi.fn() },
    stageSafetyRequest: { processing: false, get: vi.fn() },
    useHttp: vi.fn(),
    useIntervalFn: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: mocks.useHttp,
}));

vi.mock('@vueuse/core', () => ({
    useIntervalFn: mocks.useIntervalFn,
}));

const organization = { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' };
const peoplecountPayload = {
    last_updated: '2026-07-25T12:00:00Z',
    total: 1,
    all_healthy: false,
    healthy: [],
    suspicious: [{ id: 1, serial: 'PC-1', vendor: 'Axis', model: 'P8815-2', name: null, latest_ts: '2026-07-25T12:00:00Z' }],
    unhealthy: [],
};
const stageSafetyPayload = {
    generated_at: '2026-07-25T12:00:00Z',
    total: 1,
    all_fresh: false,
    fresh: [],
    stale: [
        {
            id: 2,
            identifier: 'WIND-1',
            name: 'Main Stage',
            location: 'Roof',
            stale_after_seconds: 300,
            status: 'stale' as const,
            latest_observed_at: '2026-07-25T11:59:30Z',
        },
    ],
    never_seen: [],
};

describe('combined sensor health widget', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-07-25T12:00:00Z'));
        mocks.peoplecountRequest.processing = false;
        mocks.stageSafetyRequest.processing = false;
        mocks.peoplecountRequest.get = mocks.peoplecountGet;
        mocks.stageSafetyRequest.get = mocks.stageSafetyGet;
        mocks.useHttp.mockReturnValueOnce(mocks.peoplecountRequest).mockReturnValueOnce(mocks.stageSafetyRequest);
        mocks.useIntervalFn.mockImplementation((callback: () => Promise<void>) => {
            void callback();
            return { pause: vi.fn(), resume: vi.fn(), isActive: true };
        });
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => name),
        );
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('fetches and renders both authorized health sources independently', async () => {
        mocks.peoplecountGet.mockResolvedValue(peoplecountPayload);
        mocks.stageSafetyGet.mockResolvedValue(stageSafetyPayload);

        const wrapper = mount(SensorHealthWidget, {
            props: { organization, showPeoplecount: true, showStageSafety: true },
        });
        await flushPromises();

        expect(mocks.peoplecountGet).toHaveBeenCalledWith('peoplecount.sensor-health.index');
        expect(mocks.stageSafetyGet).toHaveBeenCalledWith('stage-safety.sensor-health.index');
        expect(wrapper.text()).toContain('Axis P8815-2 · PC-1');
        expect(wrapper.text()).toContain('Roof');
        expect(wrapper.text()).not.toContain('Main Stage');
        expect(wrapper.text()).toContain('Observed 30 seconds ago');
    });

    it('only fetches endpoints granted by its permission props', async () => {
        mocks.peoplecountGet.mockResolvedValue(peoplecountPayload);

        const wrapper = mount(SensorHealthWidget, {
            props: { organization, showPeoplecount: true, showStageSafety: false },
        });
        await flushPromises();

        expect(mocks.peoplecountGet).toHaveBeenCalledOnce();
        expect(mocks.stageSafetyGet).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Peoplecount');
        expect(wrapper.text()).not.toContain('Stage Safety');
    });

    it('keeps one source visible when the other source fails', async () => {
        mocks.peoplecountGet.mockRejectedValue(new Error('network'));
        mocks.stageSafetyGet.mockResolvedValue(stageSafetyPayload);

        const wrapper = mount(SensorHealthWidget, {
            props: { organization, showPeoplecount: true, showStageSafety: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Failed to load Peoplecount sensor health');
        expect(wrapper.text()).not.toContain('No sensors currently assigned');
        expect(wrapper.text()).toContain('Roof');
    });
});
