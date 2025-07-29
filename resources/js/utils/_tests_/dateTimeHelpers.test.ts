import { describe, expect, it } from 'vitest';
import {
    DateTimeHelper,
    formatLocalDateTime,
    formatLocalDateTimeLong,
    formatTimestamp,
    getLocalDateFromUTC,
    getUTCStringFromLocal,
} from '../dateTimeHelpers';

describe('DateTimeHelper Class', () => {
    describe('Static Helper Functions', () => {
        describe('formatLocalDateTime', () => {
            it('should format UTC string to local datetime with default options', () => {
                const utcString = '2024-07-25T12:00:00.000Z';
                const formatted = DateTimeHelper.formatLocalDateTime(utcString);

                // The exact format depends on the system locale, but should contain date and time
                expect(formatted).toMatch(/2024/);
                expect(formatted).toMatch(/25/);
                expect(formatted).toMatch(/07/);
            });

            it('should format UTC string with custom options', () => {
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
        it('should format datetime correctly', () => {
            const utcString = '2024-07-25T12:00:00.000Z';
            const formatted = formatLocalDateTime(utcString);

            expect(formatted).toMatch(/2024/);
            expect(formatted).toMatch(/25/);
        });

        it('should accept custom options', () => {
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

describe('Edge Cases and Error Handling', () => {
    describe('invalid date handling', () => {
        it('should handle malformed UTC strings in formatting', () => {
            expect(() => formatLocalDateTime('invalid-date')).not.toThrow();
        });
    });
});
