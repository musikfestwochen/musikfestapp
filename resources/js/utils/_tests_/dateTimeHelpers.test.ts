import {
    DATE_TIME_LOCALE,
    RELATIVE_TIME_LOCALE,
    datetimeLocalToUTCString,
    formatChartTick,
    formatChartTooltip,
    formatDate,
    formatDateTime,
    formatDateTimeWithSeconds,
    formatDuration,
    formatRelativeTime,
    formatTime,
    getUserTimezone,
    utcStringToDatetimeLocal,
} from '@/utils/dateTimeHelpers';
import { describe, expect, it } from 'vitest';

describe('date and time display policy', () => {
    it('uses Swiss date conventions and English relative text', () => {
        expect(DATE_TIME_LOCALE).toBe('de-CH');
        expect(RELATIVE_TIME_LOCALE).toBe('en');
    });

    it('formats UTC instants with Swiss formats in an explicit timezone', () => {
        const value = '2026-08-06T14:40:12.000Z';

        expect(formatDate(value, 'Europe/Zurich')).toBe('06.08.2026');
        expect(formatDateTime(value, 'Europe/Zurich')).toBe('06.08.2026, 16:40');
        expect(formatDateTimeWithSeconds(value, 'Europe/Zurich')).toBe('06.08.2026 16:40:12');
        expect(formatTime(value, 'Europe/Zurich')).toBe('16:40');
    });

    it('formats chart labels in browser timezone using 24-hour time', () => {
        const localValue = new Date(2026, 7, 6, 16, 40);

        expect(formatChartTick(localValue, false)).toBe('16:40');
        expect(formatChartTick(localValue, true)).toBe('6. Aug., 16:40');
        expect(formatChartTooltip(localValue)).toBe('6. Aug., 16:40');
    });

    it('formats relative time in English', () => {
        const now = new Date('2026-08-06T16:00:00.000Z').getTime();

        expect(formatRelativeTime('2026-08-06T12:00:00.000Z', now)).toBe('4 hours ago');
        expect(formatRelativeTime('2026-08-06T16:05:00.000Z', now)).toBe('in 5 minutes');
    });

    it('formats compact and long durations', () => {
        expect(formatDuration(3_600_000, { style: 'short' })).toBe('1h');
        expect(formatDuration(93_600_000, { style: 'short' })).toBe('1d 2h');
        expect(formatDuration(7_200_000)).toBe('2 hours');
        expect(formatDuration(0, { style: 'short' })).toBe('0s');
    });

    it('returns N/A for invalid display values', () => {
        expect(formatDateTime('invalid')).toBe('N/A');
        expect(formatRelativeTime('invalid')).toBe('N/A');
    });
});

describe('datetime-local transport conversions', () => {
    it('round trips UTC instants through a local input value', () => {
        const utcString = '2026-08-06T14:40:00.000Z';
        const localValue = utcStringToDatetimeLocal(utcString);

        expect(datetimeLocalToUTCString(localValue)).toBe(utcString);
    });

    it('returns empty strings for missing or invalid values', () => {
        expect(utcStringToDatetimeLocal()).toBe('');
        expect(utcStringToDatetimeLocal('invalid')).toBe('');
        expect(datetimeLocalToUTCString('')).toBe('');
        expect(datetimeLocalToUTCString('invalid')).toBe('');
    });

    it('reports browser timezone', () => {
        expect(getUserTimezone()).not.toBe('');
    });
});
