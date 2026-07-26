import { flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
    useIntervalFn: vi.fn(),
}));

vi.mock('@vueuse/core', () => ({
    useIntervalFn: mocks.useIntervalFn,
}));

import { useWidgetPolling } from '../useWidgetPolling';

function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason: unknown) => void;
    const promise = new Promise<T>((promiseResolve, promiseReject) => {
        resolve = promiseResolve;
        reject = promiseReject;
    });

    return { promise, resolve, reject };
}

describe('useWidgetPolling', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-07-26T12:00:00Z'));
        mocks.useIntervalFn.mockImplementation((callback: () => Promise<void>, _interval: number, options: { immediateCallback: boolean }) => {
            if (options.immediateCallback) {
                void callback();
            }

            return { pause: vi.fn(), resume: vi.fn(), isActive: true };
        });
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('loads immediately and records the accepted response time', async () => {
        const load = vi.fn().mockResolvedValue({ count: 42 });
        const polling = useWidgetPolling({ interval: 10_000, load, errorMessage: 'Failed to load.' });

        expect(polling.loading.value).toBe(true);
        expect(mocks.useIntervalFn).toHaveBeenCalledWith(expect.any(Function), 10_000, { immediateCallback: true });

        await flushPromises();

        expect(load).toHaveBeenCalledOnce();
        expect(polling.data.value).toEqual({ count: 42 });
        expect(polling.loading.value).toBe(false);
        expect(polling.error.value).toBeNull();
        expect(polling.lastUpdated.value).toEqual(new Date('2026-07-26T12:00:00Z'));
    });

    it('does not load when disabled', async () => {
        const load = vi.fn();
        const polling = useWidgetPolling({ interval: 10_000, load, errorMessage: 'Failed to load.', enabled: false });

        await polling.refresh();

        expect(load).not.toHaveBeenCalled();
        expect(polling.loading.value).toBe(false);
        expect(mocks.useIntervalFn).not.toHaveBeenCalled();
    });

    it('retains previous data and timestamp when a refresh fails', async () => {
        const load = vi.fn().mockResolvedValueOnce('current').mockRejectedValueOnce(new Error('network'));
        const polling = useWidgetPolling({ interval: 10_000, load, errorMessage: 'Failed to load.' });
        await flushPromises();
        const successfulUpdate = polling.lastUpdated.value;

        vi.setSystemTime(new Date('2026-07-26T12:01:00Z'));
        await polling.refresh();

        expect(polling.data.value).toBe('current');
        expect(polling.error.value).toBe('Failed to load.');
        expect(polling.lastUpdated.value).toEqual(successfulUpdate);
    });

    it('coalesces queued refreshes and discards an obsolete response', async () => {
        const first = deferred<string>();
        const latest = deferred<string>();
        const load = vi.fn().mockReturnValueOnce(first.promise).mockReturnValueOnce(latest.promise);
        const polling = useWidgetPolling({ interval: 30_000, load, errorMessage: 'Failed to load.' });

        const queuedRefresh = polling.refresh();
        void polling.refresh();
        expect(load).toHaveBeenCalledOnce();

        first.resolve('obsolete');
        await Promise.resolve();
        await Promise.resolve();

        expect(load).toHaveBeenCalledTimes(2);
        expect(polling.data.value).toBeNull();

        latest.resolve('latest');
        await queuedRefresh;

        expect(load).toHaveBeenCalledTimes(2);
        expect(polling.data.value).toBe('latest');
    });

    it('lets an active request finish when the scheduled poll fires again', async () => {
        const first = deferred<string>();
        const load = vi.fn().mockReturnValue(first.promise);
        const polling = useWidgetPolling({ interval: 10_000, load, errorMessage: 'Failed to load.' });
        const poll = mocks.useIntervalFn.mock.calls[0][0] as () => Promise<void>;

        const scheduledPoll = poll();
        first.resolve('current');
        await scheduledPoll;

        expect(load).toHaveBeenCalledOnce();
        expect(polling.data.value).toBe('current');
    });

    it('does not expose an obsolete request error', async () => {
        const first = deferred<string>();
        const load = vi.fn().mockReturnValueOnce(first.promise).mockResolvedValueOnce('latest');
        const polling = useWidgetPolling({ interval: 30_000, load, errorMessage: 'Failed to load.' });

        const queuedRefresh = polling.refresh();
        first.reject(new Error('obsolete failure'));
        await queuedRefresh;

        expect(polling.data.value).toBe('latest');
        expect(polling.error.value).toBeNull();
    });
});
