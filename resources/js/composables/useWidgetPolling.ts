import { useIntervalFn } from '@vueuse/core';
import { ref, type Ref } from 'vue';

interface WidgetPollingOptions<T> {
    interval: number;
    load: () => Promise<T>;
    errorMessage: string;
    enabled?: boolean;
}

interface WidgetPolling<T> {
    data: Ref<T | null>;
    loading: Ref<boolean>;
    error: Ref<string | null>;
    lastUpdated: Ref<Date | null>;
    refresh: () => Promise<void>;
}

export function useWidgetPolling<T>({ interval, load, errorMessage, enabled = true }: WidgetPollingOptions<T>): WidgetPolling<T> {
    const data = ref<T | null>(null) as Ref<T | null>;
    const loading = ref(enabled);
    const error = ref<string | null>(null);
    const lastUpdated = ref<Date | null>(null);
    let requestedVersion = 0;
    let refreshQueued = false;
    let activeRefresh: Promise<void> | null = null;

    async function runRefreshes(): Promise<void> {
        do {
            refreshQueued = false;
            const version = requestedVersion;

            try {
                const response = await load();

                if (version === requestedVersion) {
                    data.value = response;
                    error.value = null;
                    lastUpdated.value = new Date();
                }
            } catch {
                if (version === requestedVersion) {
                    error.value = errorMessage;
                }
            }
        } while (refreshQueued);

        loading.value = false;
    }

    function refresh(): Promise<void> {
        if (!enabled) {
            return Promise.resolve();
        }

        requestedVersion++;

        if (activeRefresh) {
            refreshQueued = true;
            return activeRefresh;
        }

        activeRefresh = runRefreshes().finally(() => {
            activeRefresh = null;
        });

        return activeRefresh;
    }

    function poll(): Promise<void> {
        return activeRefresh ?? refresh();
    }

    if (enabled) {
        useIntervalFn(poll, interval, { immediateCallback: true });
    }

    return { data, loading, error, lastUpdated, refresh };
}
