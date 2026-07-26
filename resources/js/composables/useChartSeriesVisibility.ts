import { ref, watch, type Ref } from 'vue';

interface ChartSeriesVisibility {
    hiddenSeriesKeys: Ref<Set<string>>;
    isSeriesVisible: (key: string) => boolean;
    selectSeries: (key: string, additive?: boolean) => void;
}

export function useChartSeriesVisibility(seriesKeys: () => string[]): ChartSeriesVisibility {
    const hiddenSeriesKeys = ref(new Set<string>());

    function isSeriesVisible(key: string): boolean {
        return !hiddenSeriesKeys.value.has(key);
    }

    function selectSeries(key: string, additive = false): void {
        const keys = seriesKeys();
        const visibleKeys = keys.filter(isSeriesVisible);

        if (!additive) {
            hiddenSeriesKeys.value =
                visibleKeys.length === 1 && visibleKeys[0] === key ? new Set() : new Set(keys.filter((seriesKey) => seriesKey !== key));
            return;
        }

        const next = new Set(hiddenSeriesKeys.value);

        if (next.has(key)) {
            next.delete(key);
        } else if (visibleKeys.length > 1) {
            next.add(key);
        }

        hiddenSeriesKeys.value = next;
    }

    watch(
        seriesKeys,
        (keys, previousKeys = []) => {
            const availableKeys = new Set(keys);
            const previouslyVisibleKeys = previousKeys.filter((key) => !hiddenSeriesKeys.value.has(key));

            if (previouslyVisibleKeys.length === 1 && !availableKeys.has(previouslyVisibleKeys[0])) {
                hiddenSeriesKeys.value = new Set();
                return;
            }

            const next = new Set([...hiddenSeriesKeys.value].filter((key) => availableKeys.has(key)));

            if (keys.length > 0 && keys.every((key) => next.has(key))) {
                next.clear();
            }

            hiddenSeriesKeys.value = next;
        },
        { immediate: true },
    );

    return { hiddenSeriesKeys, isSeriesVisible, selectSeries };
}
