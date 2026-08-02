import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    APP_LOCALE,
    combineDateAndTime,
    convertTimezone,
    dailyResetToText,
    DateTimeHelper,
    datetimeLocalToUTCString,
    formatDateForInput,
    formatDateInTimezone,
    formatLocalDateTime,
    formatLocalDateTimeLong,
    formatTimeForInput,
    formatTimestamp,
    getLocalDateFromUTC,
    getNextDailyOccurrence,
    getRelativeTime,
    getUserTimezone,
    getUTCStringFromLocal,
    isFuture,
    isPast,
    isToday,
    utcStringToDatetimeLocal,
    validateResetTime,
} from '../dateTimeHelpers';

describe('DateTimeHelper Class', () => {
    describe('Static Helper Functions', () => {
        describe('formatLocalDateTime', () => {
            it('should format UTC string to local datetime with new default format', () => {
                const utcString = '2024-07-25T12:00:00.000Z';
                const formatted = DateTimeHelper.formatLocalDateTime(utcString);

                // Should match dd.mm.yy, hh:mm format
                expect(formatted).toMatch(/\d{2}\.\d{2}\.\d{2}, \d{2}:\d{2}/);
                expect(formatted).toContain('25.07.24');
                // Time will be converted to local timezone, so we just check the format
                expect(formatted).toMatch(/\d{2}:\d{2}/);
            });

            it('should format UTC string with custom options using old behavior', () => {
                const utcString = '2024-07-25T12:00:00.000Z';
                const formatted = DateTimeHelper.formatLocalDateTime(utcString, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                });

                expect(formatted).toMatch(/2024/);
                expect(formatted).toMatch(/Jul/);
                expect(formatted).toMatch(/25/);
            });
        });

        describe('formatLocalDateTimeLong', () => {
            it('should format UTC string in long format', () => {
                const utcString = '2024-07-25T12:00:00.000Z';
                const formatted = DateTimeHelper.formatLocalDateTimeLong(utcString);

                expect(formatted).toMatch(/Thursday/); // Assuming July 25, 2024 is a Thursday
                expect(formatted).toMatch(/July/);
                expect(formatted).toMatch(/25/);
                expect(formatted).toMatch(/2024/);
            });
        });

        describe('formatTimestamp', () => {
            it('should format UTC string with short date and time styles', () => {
                const utcString = '2024-07-25T12:00:00.000Z';
                const formatted = DateTimeHelper.formatTimestamp(utcString);

                // The exact format depends on the system locale, but should be short
                expect(formatted).toMatch(/\d{1,2}\/\d{1,2}\/\d{2,4}/); // Short date format
                expect(formatted).toMatch(/\d{1,2}:\d{2}/); // Short time format
            });
        });

        describe('getUTCStringFromLocal', () => {
            it('should convert local date to UTC ISO string', () => {
                const localDate = new Date('2024-07-25T12:00:00');
                const utcString = DateTimeHelper.getUTCStringFromLocal(localDate);

                expect(utcString).toMatch(/2024-07-25T\d{2}:00:00\.\d{3}Z/);
            });

            it('should return empty string for null input', () => {
                const utcString = DateTimeHelper.getUTCStringFromLocal(null);
                expect(utcString).toBe('');
            });
        });

        describe('getLocalDateFromUTC', () => {
            it('should convert UTC string to local date', () => {
                const utcString = '2024-07-25T12:00:00.000Z';
                const localDate = DateTimeHelper.getLocalDateFromUTC(utcString);

                expect(localDate).toBeInstanceOf(Date);
                expect(localDate!.getUTCFullYear()).toBe(2024);
                expect(localDate!.getUTCMonth()).toBe(6); // July is month 6 (0-indexed)
                expect(localDate!.getUTCDate()).toBe(25);
            });

            it('should return null for undefined input', () => {
                const localDate = DateTimeHelper.getLocalDateFromUTC(undefined);
                expect(localDate).toBeNull();
            });

            it('should return null for empty string', () => {
                const localDate = DateTimeHelper.getLocalDateFromUTC('');
                expect(localDate).toBeNull();
            });
        });

        describe('datetime-local conversions', () => {
            it('round trips UTC strings through datetime-local values', () => {
                const utcString = '2024-07-25T12:30:00.000Z';
                const localValue = DateTimeHelper.utcStringToDatetimeLocal(utcString);

                expect(localValue).toMatch(/^2024-07-25T\d{2}:30$/);
                expect(DateTimeHelper.datetimeLocalToUTCString(localValue)).toBe(utcString);
            });
        });

        describe('validateResetTime', () => {
            it('should validate correct time formats', () => {
                const result = DateTimeHelper.validateResetTime('08:00');
                expect(result.isValid).toBe(true);
                expect(result.error).toBeUndefined();
            });

            it('should invalidate incorrect time formats', () => {
                const result = DateTimeHelper.validateResetTime('25:00');
                expect(result.isValid).toBe(false);
                expect(result.error).toBeDefined();
            });
        });

        describe('dailyResetToText', () => {
            it('should convert time and timezone to readable text', () => {
                const text = DateTimeHelper.dailyResetToText('08:00', 'Europe/Zurich');
                expect(text).toBe('Daily at 08:00 (Europe/Zurich)');
            });
        });

        describe('getNextDailyOccurrence', () => {
            it('should return next daily occurrences', () => {
                const occurrences = DateTimeHelper.getNextDailyOccurrence('09:00', 'UTC', 3);
                expect(occurrences).toHaveLength(3);
                expect(occurrences[0]).toBeInstanceOf(Date);
            });
        });
    });
});

describe('Standalone Utility Functions', () => {
    describe('formatLocalDateTime', () => {
        it('should format datetime with new default format', () => {
            const utcString = '2024-07-25T12:00:00.000Z';
            const formatted = formatLocalDateTime(utcString);

            // Should match dd.mm.yy, hh:mm format
            expect(formatted).toMatch(/\d{2}\.\d{2}\.\d{2}, \d{2}:\d{2}/);
            expect(formatted).toContain('25.07.24');
            // Time will be converted to local timezone, so we just check the format
            expect(formatted).toMatch(/\d{2}:\d{2}/);
        });

        it('should accept custom options using old behavior', () => {
            const utcString = '2024-07-25T12:00:00.000Z';
            const formatted = formatLocalDateTime(utcString, { year: 'numeric' });

            // The formatting function merges custom options with defaults, so it may include more than just the year
            expect(formatted).toMatch(/2024/);
        });
    });

    describe('formatLocalDateTimeLong', () => {
        it('should format datetime in long format', () => {
            const utcString = '2024-07-25T12:00:00.000Z';
            const formatted = formatLocalDateTimeLong(utcString);

            expect(formatted).toMatch(/Thursday/);
            expect(formatted).toMatch(/July/);
            expect(formatted).toMatch(/2024/);
        });
    });

    describe('formatTimestamp', () => {
        it('should format datetime with short date and time styles', () => {
            const utcString = '2024-07-25T12:00:00.000Z';
            const formatted = formatTimestamp(utcString);

            // The exact format depends on the system locale, but should be short
            expect(formatted).toMatch(/\d{1,2}\/\d{1,2}\/\d{2,4}/); // Short date format
            expect(formatted).toMatch(/\d{1,2}:\d{2}/); // Short time format
        });
    });

    describe('getUTCStringFromLocal', () => {
        it('should convert local date to UTC string', () => {
            const localDate = new Date('2024-07-25T12:00:00');
            const utcString = getUTCStringFromLocal(localDate);

            expect(utcString).toMatch(/2024-07-25T\d{2}:00:00\.\d{3}Z/);
        });

        it('should handle null input', () => {
            const utcString = getUTCStringFromLocal(null);
            expect(utcString).toBe('');
        });
    });

    describe('getLocalDateFromUTC', () => {
        it('should convert UTC string to local date', () => {
            const utcString = '2024-07-25T12:00:00.000Z';
            const localDate = getLocalDateFromUTC(utcString);

            expect(localDate).toBeInstanceOf(Date);
            expect(localDate!.getUTCFullYear()).toBe(2024);
        });

        it('should handle undefined input', () => {
            const localDate = getLocalDateFromUTC(undefined);
            expect(localDate).toBeNull();
        });
    });

    describe('datetime-local conversions', () => {
        it('round trips UTC strings through datetime-local values', () => {
            const utcString = '2024-07-25T12:30:00.000Z';
            const localValue = utcStringToDatetimeLocal(utcString);

            expect(localValue).toMatch(/^2024-07-25T\d{2}:30$/);
            expect(datetimeLocalToUTCString(localValue)).toBe(utcString);
        });

        it('returns empty strings for missing or invalid values', () => {
            expect(utcStringToDatetimeLocal()).toBe('');
            expect(utcStringToDatetimeLocal('not-a-date')).toBe('');
            expect(datetimeLocalToUTCString('')).toBe('');
            expect(datetimeLocalToUTCString('not-a-date')).toBe('');
        });
    });
});

describe('Time Format Functions', () => {
    describe('validateResetTime', () => {
        it('should validate correct time formats', () => {
            const result = validateResetTime('08:00');
            expect(result.isValid).toBe(true);
            expect(result.error).toBeUndefined();
        });

        it('should validate edge case times', () => {
            expect(validateResetTime('00:00').isValid).toBe(true);
            expect(validateResetTime('23:59').isValid).toBe(true);
            expect(validateResetTime('12:30').isValid).toBe(true);
        });

        it('should invalidate incorrect time formats', () => {
            const result = validateResetTime('25:00');
            expect(result.isValid).toBe(false);
            expect(result.error).toBeDefined();
        });

        it('should invalidate malformed times', () => {
            expect(validateResetTime('8:00').isValid).toBe(false); // Missing leading zero
            expect(validateResetTime('12:5').isValid).toBe(false); // Missing leading zero
            expect(validateResetTime('abc:def').isValid).toBe(false);
            expect(validateResetTime('12:60').isValid).toBe(false); // Invalid minutes
        });

        it('should handle empty strings', () => {
            const result = validateResetTime('');
            expect(result.isValid).toBe(false);
            expect(result.error).toBeDefined();
        });
    });

    describe('dailyResetToText', () => {
        it('should convert time and timezone to readable text', () => {
            const text = dailyResetToText('08:00', 'Europe/Zurich');
            expect(text).toBe('Daily at 08:00 (Europe/Zurich)');
        });

        it('should handle different timezones', () => {
            const text = dailyResetToText('14:30', 'America/New_York');
            expect(text).toBe('Daily at 14:30 (America/New_York)');
        });

        it('should handle UTC timezone', () => {
            const text = dailyResetToText('12:00', 'UTC');
            expect(text).toBe('Daily at 12:00 (UTC)');
        });
    });

    describe('getNextDailyOccurrence', () => {
        it('should return next daily occurrences', () => {
            const occurrences = getNextDailyOccurrence('09:00', 'UTC', 3);
            expect(occurrences).toHaveLength(3);
            expect(occurrences[0]).toBeInstanceOf(Date);
            expect(occurrences[1]).toBeInstanceOf(Date);
            expect(occurrences[2]).toBeInstanceOf(Date);
        });

        it('should handle different timezones', () => {
            const occurrences = getNextDailyOccurrence('14:30', 'Europe/Zurich', 2);
            expect(occurrences).toHaveLength(2);
            expect(occurrences[0]).toBeInstanceOf(Date);
        });

        it('should handle invalid time format', () => {
            const occurrences = getNextDailyOccurrence('invalid', 'UTC', 3);
            expect(occurrences).toHaveLength(0);
        });

        it('should limit occurrences to requested count', () => {
            const occurrences = getNextDailyOccurrence('08:00', 'UTC', 2);
            expect(occurrences).toHaveLength(2);
        });

        it('should handle edge case times', () => {
            const occurrences = getNextDailyOccurrence('00:00', 'UTC', 1);
            expect(occurrences).toHaveLength(1);
            expect(occurrences[0]).toBeInstanceOf(Date);
        });

        describe('timezone-aware behavior', () => {
            afterEach(() => {
                vi.useRealTimers();
            });

            const wallTimeIn = (date: Date, timezone: string): string =>
                date.toLocaleString(APP_LOCALE, {
                    timeZone: timezone,
                    hour: '2-digit',
                    minute: '2-digit',
                    hourCycle: 'h23',
                });

            it('should apply the reset time in the target timezone, not the viewer timezone', () => {
                // 2024-07-25 12:00 UTC — independent of the host/viewer timezone,
                // every occurrence must read 09:00 in Europe/Zurich
                vi.useFakeTimers();
                vi.setSystemTime(new Date('2024-07-25T12:00:00Z'));

                const occurrences = getNextDailyOccurrence('09:00', 'Europe/Zurich', 3);

                expect(occurrences).toHaveLength(3);
                for (const occurrence of occurrences) {
                    expect(wallTimeIn(occurrence, 'Europe/Zurich')).toBe('09:00');
                }
            });

            it('should shift UTC instants across DST transitions while keeping local wall time', () => {
                // Europe/Zurich springs forward CET -> CEST on 2024-03-31
                vi.useFakeTimers();
                vi.setSystemTime(new Date('2024-03-30T12:00:00Z'));

                const occurrences = getNextDailyOccurrence('09:00', 'Europe/Zurich', 3);

                expect(occurrences.map((date) => date.toISOString())).toEqual([
                    '2024-03-31T07:00:00.000Z', // CET (UTC+1)
                    '2024-04-01T07:00:00.000Z', // CEST (UTC+2)
                    '2024-04-02T07:00:00.000Z', // CEST (UTC+2)
                ]);
            });

            it('should keep UTC instants aligned across DST fall-back', () => {
                // Europe/Zurich falls back CEST -> CET on 2024-10-27
                vi.useFakeTimers();
                vi.setSystemTime(new Date('2024-10-25T12:00:00Z'));

                const occurrences = getNextDailyOccurrence('09:00', 'Europe/Zurich', 3);

                expect(occurrences.map((date) => date.toISOString())).toEqual([
                    '2024-10-26T07:00:00.000Z', // CEST (UTC+2)
                    '2024-10-27T08:00:00.000Z', // CET (UTC+1)
                    '2024-10-28T08:00:00.000Z', // CET (UTC+1)
                ]);
            });

            it('should skip today when the reset time already passed in the target timezone', () => {
                // 2024-07-25 10:00 UTC = 12:00 in Europe/Zurich — 09:00 already passed there
                vi.useFakeTimers();
                vi.setSystemTime(new Date('2024-07-25T10:00:00Z'));

                const occurrences = getNextDailyOccurrence('09:00', 'Europe/Zurich', 2);

                expect(occurrences.map((date) => date.toISOString())).toEqual(['2024-07-26T07:00:00.000Z', '2024-07-27T07:00:00.000Z']);
            });

            it('should include today when the reset time is still ahead near midnight', () => {
                // 2024-07-25 21:30 UTC = 23:30 in Europe/Zurich — 00:00 tomorrow is 30 min away
                vi.useFakeTimers();
                vi.setSystemTime(new Date('2024-07-25T21:30:00Z'));

                const occurrences = getNextDailyOccurrence('00:00', 'Europe/Zurich', 2);

                expect(occurrences.map((date) => date.toISOString())).toEqual(['2024-07-25T22:00:00.000Z', '2024-07-26T22:00:00.000Z']);
            });

            it('should return occurrences in the future and in ascending order', () => {
                vi.useFakeTimers();
                const now = new Date('2024-07-25T12:00:00Z');
                vi.setSystemTime(now);

                const occurrences = getNextDailyOccurrence('14:30', 'America/New_York', 5);

                expect(occurrences).toHaveLength(5);
                for (const occurrence of occurrences) {
                    expect(occurrence.getTime()).toBeGreaterThan(now.getTime());
                    expect(wallTimeIn(occurrence, 'America/New_York')).toBe('14:30');
                }
                for (let i = 1; i < occurrences.length; i++) {
                    expect(occurrences[i].getTime()).toBeGreaterThan(occurrences[i - 1].getTime());
                }
            });
        });
    });
});

describe('Timezone Functions', () => {
    describe('getUserTimezone', () => {
        it('should return a valid timezone string', () => {
            const timezone = getUserTimezone();
            expect(typeof timezone).toBe('string');
            expect(timezone.length).toBeGreaterThan(0);
        });
    });

    describe('formatDateInTimezone', () => {
        it('should format date in specified timezone', () => {
            const date = new Date('2024-07-25T12:00:00Z');
            const formatted = formatDateInTimezone(date, 'UTC');
            expect(formatted).toContain('2024');
            expect(formatted).toContain('25');
        });

        it('should accept custom options', () => {
            const date = new Date('2024-07-25T12:00:00Z');
            const formatted = formatDateInTimezone(date, 'UTC', { year: 'numeric' });
            expect(formatted).toContain('2024');
        });
    });

    describe('convertTimezone', () => {
        it('should convert date between timezones', () => {
            const date = new Date('2024-07-25T12:00:00Z');
            const converted = convertTimezone(date, 'UTC', 'America/New_York');
            expect(converted).toBeInstanceOf(Date);
        });
    });
});

describe('Date Utility Functions', () => {
    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    describe('formatDateForInput', () => {
        it('should format date for HTML input', () => {
            const date = new Date('2024-07-25T12:00:00Z');
            const formatted = formatDateForInput(date);
            expect(formatted).toBe('2024-07-25');
        });
    });

    describe('formatTimeForInput', () => {
        it('should format time for HTML input', () => {
            const date = new Date('2024-07-25T12:30:00Z');
            const formatted = formatTimeForInput(date);
            expect(formatted).toMatch(/\d{2}:\d{2}/);
        });
    });

    describe('combineDateAndTime', () => {
        it('should combine date and time strings', () => {
            const combined = combineDateAndTime('2024-07-25', '12:30');
            expect(combined).toBeInstanceOf(Date);
            expect(combined.getFullYear()).toBe(2024);
            expect(combined.getMonth()).toBe(6); // July is month 6
            expect(combined.getDate()).toBe(25);
        });
    });

    describe('isToday', () => {
        it('should return true for today', () => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-07-25T12:00:00Z'));

            const today = new Date('2024-07-25T12:00:00Z');

            expect(isToday(today)).toBe(true);
        });

        it('should return false for yesterday', () => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-07-25T12:00:00Z'));

            const yesterday = new Date('2024-07-24T12:00:00Z');

            expect(isToday(yesterday)).toBe(false);
        });
    });

    describe('isPast', () => {
        it('should return true for past dates', () => {
            const pastDate = new Date('2020-01-01');
            expect(isPast(pastDate)).toBe(true);
        });

        it('should return false for future dates', () => {
            const futureDate = new Date('2030-01-01');
            expect(isPast(futureDate)).toBe(false);
        });
    });

    describe('isFuture', () => {
        it('should return true for future dates', () => {
            const futureDate = new Date('2030-01-01');
            expect(isFuture(futureDate)).toBe(true);
        });

        it('should return false for past dates', () => {
            const pastDate = new Date('2020-01-01');
            expect(isFuture(pastDate)).toBe(false);
        });
    });

    describe('getRelativeTime', () => {
        it('should use the application locale instead of the browser locale', () => {
            const NativeRelativeTimeFormat = Intl.RelativeTimeFormat;
            const formatter = vi.spyOn(Intl, 'RelativeTimeFormat').mockImplementation(function (locales, options) {
                return new NativeRelativeTimeFormat(locales, options);
            });

            getRelativeTime(new Date());

            expect(formatter).toHaveBeenCalledWith(APP_LOCALE, { numeric: 'auto' });
        });

        it('should return "now" for current time', () => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-07-25T12:00:00Z'));

            const now = new Date('2024-07-25T12:00:00Z');

            const relative = getRelativeTime(now);
            expect(relative).toBe('now');
        });

        it('should return minutes ago for recent past', () => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-07-25T12:00:00Z'));

            const fiveMinutesAgo = new Date('2024-07-25T11:55:00Z');

            const relative = getRelativeTime(fiveMinutesAgo);
            expect(relative).toBe('5 minutes ago');
        });

        it('should return seconds for observations less than one minute old', () => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-07-25T12:00:00Z'));

            expect(getRelativeTime(new Date('2024-07-25T11:59:32Z'))).toBe('28 seconds ago');
            expect(getRelativeTime(new Date('2024-07-25T12:00:28Z'))).toBe('in 28 seconds');
        });

        it('should round relative units equally for past and future dates', () => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-07-25T12:00:00Z'));

            expect(getRelativeTime(new Date('2024-07-25T11:58:01Z'))).toBe('2 minutes ago');
            expect(getRelativeTime(new Date('2024-07-25T12:01:59Z'))).toBe('in 2 minutes');
        });

        it('should return "in X minutes" for near future', () => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-07-25T12:00:00Z'));

            const fiveMinutesLater = new Date('2024-07-25T12:05:00Z');

            const relative = getRelativeTime(fiveMinutesLater);
            expect(relative).toBe('in 5 minutes');
        });

        it('should return hours for longer periods', () => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-07-25T12:00:00Z'));

            const twoHoursAgo = new Date('2024-07-25T10:00:00Z');

            const relative = getRelativeTime(twoHoursAgo);
            expect(relative).toBe('2 hours ago');
        });

        it('should return days for very long periods', () => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-07-25T12:00:00Z'));

            const threeDaysAgo = new Date('2024-07-22T12:00:00Z');

            const relative = getRelativeTime(threeDaysAgo);
            expect(relative).toBe('3 days ago');
        });
    });
});

describe('Edge Cases and Error Handling', () => {
    describe('invalid date handling', () => {
        it('should handle malformed UTC strings in formatting', () => {
            expect(() => formatLocalDateTime('invalid-date')).not.toThrow();
        });
    });
});
