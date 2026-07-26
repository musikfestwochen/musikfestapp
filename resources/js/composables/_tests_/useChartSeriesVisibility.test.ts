import { describe, expect, it } from 'vitest';
import { nextTick, ref } from 'vue';
import { useChartSeriesVisibility } from '../useChartSeriesVisibility';

describe('useChartSeriesVisibility', () => {
    it('isolates a series and restores all when selected again', () => {
        const visibility = useChartSeriesVisibility(() => ['one', 'two', 'three']);

        visibility.selectSeries('two');
        expect(visibility.isSeriesVisible('one')).toBe(false);
        expect(visibility.isSeriesVisible('two')).toBe(true);
        expect(visibility.isSeriesVisible('three')).toBe(false);

        visibility.selectSeries('two');
        expect(visibility.hiddenSeriesKeys.value.size).toBe(0);
    });

    it('uses additive selection without allowing every series to be hidden', () => {
        const visibility = useChartSeriesVisibility(() => ['one', 'two', 'three']);

        visibility.selectSeries('one');
        visibility.selectSeries('two', true);
        expect(visibility.isSeriesVisible('one')).toBe(true);
        expect(visibility.isSeriesVisible('two')).toBe(true);
        expect(visibility.isSeriesVisible('three')).toBe(false);

        visibility.selectSeries('one', true);
        expect(visibility.isSeriesVisible('one')).toBe(false);
        expect(visibility.isSeriesVisible('two')).toBe(true);

        visibility.selectSeries('two', true);
        expect(visibility.isSeriesVisible('two')).toBe(true);
    });

    it('drops removed keys and restores visibility when data changes', async () => {
        const keys = ref(['one', 'two']);
        const visibility = useChartSeriesVisibility(() => keys.value);
        visibility.selectSeries('one');

        keys.value = ['two', 'three'];
        await nextTick();

        expect(visibility.hiddenSeriesKeys.value.size).toBe(0);
        expect(visibility.isSeriesVisible('two')).toBe(true);
        expect(visibility.isSeriesVisible('three')).toBe(true);
    });
});
