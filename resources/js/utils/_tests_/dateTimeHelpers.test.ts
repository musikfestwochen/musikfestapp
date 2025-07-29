import { describe, expect, it } from 'vitest';
import {
    combineDateAndTime,
    convertTimezone,
    createRRule,
    DateTimeHelper,
    formatDateForInput,
    formatDateInTimezone,
    formatLocalDateTime,
    formatLocalDateTimeLong,
    formatTimeForInput,
    formatTimestamp,
    getLocalDateFromUTC,
    getNextRRuleOccurrences,
    getRelativeTime,
    getUserTimezone,
    getUTCStringFromLocal,
    isFuture,
    isPast,
    isToday,
    rruleToText,
    validateRRule,
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
});

describe('RRULE Functions', () => {
    describe('validateRRule', () => {
        it('should validate correct RRULE strings', () => {
            const result = validateRRule('FREQ=DAILY;INTERVAL=1');
            expect(result.isValid).toBe(true);
            expect(result.error).toBeUndefined();
        });

        it('should invalidate incorrect RRULE strings', () => {
            const result = validateRRule('INVALID_RRULE');
            expect(result.isValid).toBe(false);
            expect(result.error).toBeDefined();
        });

        it('should handle empty strings', () => {
            const result = validateRRule('');
            expect(result.isValid).toBe(false);
            expect(result.error).toBeDefined();
        });
    });

    describe('createRRule', () => {
        it('should create daily RRULE', () => {
            const rrule = createRRule('DAILY', { interval: 2, byhour: 9, byminute: 30 });
            expect(rrule).toContain('FREQ=DAILY');
            expect(rrule).toContain('INTERVAL=2');
            expect(rrule).toContain('BYHOUR=9');
            expect(rrule).toContain('BYMINUTE=30');
        });

        it('should create weekly RRULE with weekdays', () => {
            const rrule = createRRule('WEEKLY', { byweekday: [1, 3, 5] });
            expect(rrule).toContain('FREQ=WEEKLY');
            expect(rrule).toContain('BYDAY=');
        });

        it('should create monthly RRULE', () => {
            const rrule = createRRule('MONTHLY');
            expect(rrule).toContain('FREQ=MONTHLY');
            expect(rrule).toContain('INTERVAL=1');
        });

        it('should handle until date', () => {
            const until = new Date('2024-12-31');
            const rrule = createRRule('DAILY', { until });
            expect(rrule).toContain('UNTIL=');
        });

        it('should handle count', () => {
            const rrule = createRRule('DAILY', { count: 10 });
            expect(rrule).toContain('COUNT=10');
        });
    });

    describe('rruleToText', () => {
        it('should convert valid RRULE to text', () => {
            const text = rruleToText('FREQ=DAILY;INTERVAL=1');
            expect(text).toBe('every day');
        });

        it('should handle invalid RRULE', () => {
            const text = rruleToText('INVALID');
            expect(text).toBe('Invalid RRULE');
        });
    });

    describe('getNextRRuleOccurrences', () => {
        it('should return next occurrences for daily RRULE', () => {
            const startDate = new Date('2024-01-01T09:00:00Z');
            const occurrences = getNextRRuleOccurrences('DTSTART:20240101T090000Z\nFREQ=DAILY;INTERVAL=1', startDate, 3);
            expect(occurrences).toHaveLength(3);
            expect(occurrences[0]).toBeInstanceOf(Date);
        });

        it('should handle invalid RRULE', () => {
            const startDate = new Date('2024-01-01T09:00:00Z');
            const occurrences = getNextRRuleOccurrences('INVALID', startDate, 3);
            expect(occurrences).toHaveLength(0);
        });

        it('should limit occurrences to requested count', () => {
            const startDate = new Date('2024-01-01T09:00:00Z');
            const occurrences = getNextRRuleOccurrences('DTSTART:20240101T090000Z\nFREQ=DAILY;INTERVAL=1', startDate, 2);
            expect(occurrences).toHaveLength(2);
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
            const today = new Date();
            expect(isToday(today)).toBe(true);
        });

        it('should return false for yesterday', () => {
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
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
        it('should return "now" for current time', () => {
            const now = new Date();
            const relative = getRelativeTime(now);
            expect(relative).toBe('now');
        });

        it('should return minutes ago for recent past', () => {
            const fiveMinutesAgo = new Date();
            fiveMinutesAgo.setMinutes(fiveMinutesAgo.getMinutes() - 5);
            const relative = getRelativeTime(fiveMinutesAgo);
            expect(relative).toContain('minutes ago');
        });

        it('should return "in X minutes" for near future', () => {
            const fiveMinutesLater = new Date();
            fiveMinutesLater.setMinutes(fiveMinutesLater.getMinutes() + 5);
            const relative = getRelativeTime(fiveMinutesLater);
            expect(relative).toContain('in');
            expect(relative).toContain('minutes');
        });

        it('should return hours for longer periods', () => {
            const twoHoursAgo = new Date();
            twoHoursAgo.setHours(twoHoursAgo.getHours() - 2);
            const relative = getRelativeTime(twoHoursAgo);
            expect(relative).toContain('hours ago');
        });

        it('should return days for very long periods', () => {
            const threeDaysAgo = new Date();
            threeDaysAgo.setDate(threeDaysAgo.getDate() - 3);
            const relative = getRelativeTime(threeDaysAgo);
            expect(relative).toContain('days ago');
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
