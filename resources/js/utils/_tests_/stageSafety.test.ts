import { describe, expect, it } from 'vitest';
import { formatWindSpeed, metersPerSecondToKilometersPerHour, stageSafetySensorName } from '../stageSafety';

describe('Stage Safety wind formatting', () => {
    it('converts canonical meters per second to kilometers per hour', () => {
        expect(metersPerSecondToKilometersPerHour(5)).toBe(18);
        expect(formatWindSpeed(8.0556)).toBe('29');
    });

    it('uses location, name, then identifier as the sensor label', () => {
        expect(stageSafetySensorName({ location: 'Roof', name: 'Main Stage', identifier: 'ABC123' })).toBe('Roof');
        expect(stageSafetySensorName({ location: null, name: 'Main Stage', identifier: 'ABC123' })).toBe('Main Stage');
        expect(stageSafetySensorName({ location: null, name: null, identifier: 'ABC123' })).toBe('ABC123');
    });
});
