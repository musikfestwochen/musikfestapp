import { describe, expect, it } from 'vitest';
import { formatWindSpeed, metersPerSecondToKilometersPerHour } from '../stageSafety';

describe('Stage Safety wind formatting', () => {
    it('converts canonical meters per second to kilometers per hour', () => {
        expect(metersPerSecondToKilometersPerHour(5)).toBe(18);
        expect(formatWindSpeed(8.0556)).toBe('29');
    });
});
