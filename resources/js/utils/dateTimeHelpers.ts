/**
 * Date and Time Helper Functions
 * Provides utility functions for date and time formatting and conversion
 * Includes daily reset scheduling and advanced timezone handling
 *
 * IMPORTANT: Daily Reset Timezone Handling
 * ========================================
 * This implementation handles daily resets at specific times in specified timezones:
 *
 * 1. Reset times are stored in HH:MM format (24-hour)
 * 2. Timezone handling ensures resets occur at the correct local time
 * 3. DST transitions are handled by applying the reset at the defined time in the current day
 * 4. Next occurrence calculation accounts for whether today's reset time has already passed
 *
 * Example of daily reset usage:
 * ```typescript
 * const nextOccurrences = getNextDailyOccurrence('09:00', 'Europe/Zurich', 5);
 * const isValid = validateResetTime('09:00');
 * const description = dailyResetToText('09:00', 'Europe/Zurich');
 * ```
 *
 * This approach ensures consistent daily reset behavior across different timezones and DST transitions.
 */

/**
 * Format a date/time in the user's local timezone
 * Format: dd.mm.yy, hh:mm (24h format, no timezone info)
 */
export function formatLocalDateTime(utcString: string, options?: Intl.DateTimeFormatOptions): string {
    const date = new Date(utcString);

    // If custom options are provided, use them with the old behavior for backward compatibility
    if (options) {
        const defaultOptions: Intl.DateTimeFormatOptions = {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            timeZoneName: 'short',
        };
        return date.toLocaleString('en-US', { ...defaultOptions, ...options });
    }

    // Default format: dd.mm.yy, hh:mm (24h format, no timezone)
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear().toString().slice(-2);
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');

    return `${day}.${month}.${year}, ${hours}:${minutes}`;
}

/**
 * Format a date/time in a long format (e.g., "Monday, January 1, 2024 at 6:00 PM PST")
 */
export function formatLocalDateTimeLong(utcString: string): string {
    return formatLocalDateTime(utcString, {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZoneName: 'short',
    });
}

/**
 * Format a date/time with short date and time styles
 */
export function formatTimestamp(utcString: string): string {
    const date = new Date(utcString);
    return date.toLocaleString('en-US', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

/**
 * Convert local Date objects to UTC ISO strings for backend
 */
export function getUTCStringFromLocal(localDate: Date | null): string {
    if (!localDate) return '';
    return localDate.toISOString();
}

/**
 * Convert UTC ISO strings to local Date objects
 */
export function getLocalDateFromUTC(utcString?: string): Date | null {
    if (!utcString) return null;
    return new Date(utcString);
}

/**
 * Convert UTC date/time strings to datetime-local input values.
 */
export function utcStringToDatetimeLocal(utcString?: string): string {
    if (!utcString) return '';

    const date = new Date(utcString);

    if (Number.isNaN(date.getTime())) return '';

    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

/**
 * Convert datetime-local input values to UTC ISO strings for backend storage.
 */
export function datetimeLocalToUTCString(datetimeLocal: string): string {
    if (!datetimeLocal) return '';

    const date = new Date(datetimeLocal);

    if (Number.isNaN(date.getTime())) return '';

    return date.toISOString();
}

/**
 * Get the user's current timezone
 */
export function getUserTimezone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

/**
 * Convert a date from one timezone to another
 */
export function convertTimezone(date: Date, fromTimezone: string, toTimezone: string): Date {
    // Create a date string in the source timezone
    const utcDate = new Date(date.toLocaleString('en-US', { timeZone: 'UTC' }));
    const sourceDate = new Date(date.toLocaleString('en-US', { timeZone: fromTimezone }));
    const diff = utcDate.getTime() - sourceDate.getTime();

    // Apply the difference and convert to target timezone
    const targetDate = new Date(date.getTime() + diff);
    return new Date(targetDate.toLocaleString('en-US', { timeZone: toTimezone }));
}

/**
 * Format a date in a specific timezone
 */
export function formatDateInTimezone(date: Date, timezone: string, options?: Intl.DateTimeFormatOptions): string {
    const defaultOptions: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: timezone,
        timeZoneName: 'short',
    };

    return date.toLocaleString('en-US', { ...defaultOptions, ...options });
}

/**
 * Calculate next daily occurrences at resetTime in specified timezone
 * Handles DST by applying reset at defined time in current day
 */
export function getNextDailyOccurrence(resetTime: string, timezone: string, count: number = 5): Date[] {
    const occurrences: Date[] = [];
    const [hours, minutes] = resetTime.split(':').map(Number);

    // Validate time format
    if (isNaN(hours) || isNaN(minutes) || hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
        return [];
    }

    const now = new Date();

    for (let i = 0; i < count; i++) {
        // Create a date for today + i days
        const targetDate = new Date(now);
        targetDate.setDate(now.getDate() + i);
        targetDate.setHours(hours, minutes, 0, 0);

        // If it's today and the time has already passed, skip to tomorrow
        if (i === 0 && targetDate <= now) {
            targetDate.setDate(now.getDate() + 1);
        }

        occurrences.push(targetDate);
    }

    return occurrences;
}

/**
 * Validate HH:MM format using regex
 */
export function validateResetTime(timeString: string): { isValid: boolean; error?: string } {
    if (!timeString || timeString.trim() === '') {
        return {
            isValid: false,
            error: 'Time string cannot be empty',
        };
    }

    // Validate HH:MM format: /^([01][0-9]|2[0-3]):[0-5][0-9]$/
    const timeRegex = /^([01][0-9]|2[0-3]):[0-5][0-9]$/;

    if (!timeRegex.test(timeString)) {
        return {
            isValid: false,
            error: 'Time must be in HH:MM format (24-hour)',
        };
    }

    return { isValid: true };
}

/**
 * Convert daily reset time and timezone to human-readable text
 */
export function dailyResetToText(resetTime: string, timezone: string): string {
    return `Daily at ${resetTime} (${timezone})`;
}

/**
 * Format a date for display in forms (YYYY-MM-DD format)
 */
export function formatDateForInput(date: Date): string {
    return date.toISOString().split('T')[0];
}

/**
 * Format a time for display in forms (HH:MM format)
 */
export function formatTimeForInput(date: Date): string {
    return date.toTimeString().slice(0, 5);
}

/**
 * Combine date and time strings into a Date object
 */
export function combineDateAndTime(dateString: string, timeString: string): Date {
    return new Date(`${dateString}T${timeString}:00`);
}

/**
 * Check if a date is today
 */
export function isToday(date: Date): boolean {
    const today = new Date();
    return date.toDateString() === today.toDateString();
}

/**
 * Check if a date is in the past
 */
export function isPast(date: Date): boolean {
    return date < new Date();
}

/**
 * Check if a date is in the future
 */
export function isFuture(date: Date): boolean {
    return date > new Date();
}

/**
 * Get relative time string (e.g., "2 hours ago", "in 3 days")
 */
export function getRelativeTime(date: Date): string {
    const diffSeconds = Math.round((date.getTime() - Date.now()) / 1000);
    const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

    if (Math.abs(diffSeconds) < 60) return formatter.format(diffSeconds, 'second');
    if (Math.abs(diffSeconds) < 3600) return formatter.format(Math.round(diffSeconds / 60), 'minute');
    if (Math.abs(diffSeconds) < 86400) return formatter.format(Math.round(diffSeconds / 3600), 'hour');

    return formatter.format(Math.round(diffSeconds / 86400), 'day');
}

/**
 * Date and Time Helper Class
 * Provides utility methods for date and time formatting and conversion
 */
export class DateTimeHelper {
    /**
     * Format a date/time in the user's local timezone
     */
    static formatLocalDateTime(utcString: string, options?: Intl.DateTimeFormatOptions): string {
        return formatLocalDateTime(utcString, options);
    }

    /**
     * Format a date/time in a long format
     */
    static formatLocalDateTimeLong(utcString: string): string {
        return formatLocalDateTimeLong(utcString);
    }

    /**
     * Format a date/time with short date and time styles
     */
    static formatTimestamp(utcString: string): string {
        return formatTimestamp(utcString);
    }

    /**
     * Convert local Date objects to UTC ISO strings for backend
     */
    static getUTCStringFromLocal(localDate: Date | null): string {
        return getUTCStringFromLocal(localDate);
    }

    /**
     * Convert UTC ISO strings to local Date objects
     */
    static getLocalDateFromUTC(utcString?: string): Date | null {
        return getLocalDateFromUTC(utcString);
    }

    /**
     * Convert UTC date/time strings to datetime-local input values.
     */
    static utcStringToDatetimeLocal(utcString?: string): string {
        return utcStringToDatetimeLocal(utcString);
    }

    /**
     * Convert datetime-local input values to UTC ISO strings for backend storage.
     */
    static datetimeLocalToUTCString(datetimeLocal: string): string {
        return datetimeLocalToUTCString(datetimeLocal);
    }

    /**
     * Get the user's current timezone
     */
    static getUserTimezone(): string {
        return getUserTimezone();
    }

    /**
     * Convert a date from one timezone to another
     */
    static convertTimezone(date: Date, fromTimezone: string, toTimezone: string): Date {
        return convertTimezone(date, fromTimezone, toTimezone);
    }

    /**
     * Format a date in a specific timezone
     */
    static formatDateInTimezone(date: Date, timezone: string, options?: Intl.DateTimeFormatOptions): string {
        return formatDateInTimezone(date, timezone, options);
    }

    /**
     * Calculate next daily occurrences at resetTime in specified timezone
     */
    static getNextDailyOccurrence(resetTime: string, timezone: string, count: number = 5): Date[] {
        return getNextDailyOccurrence(resetTime, timezone, count);
    }

    /**
     * Validate HH:MM format using regex
     */
    static validateResetTime(timeString: string): { isValid: boolean; error?: string } {
        return validateResetTime(timeString);
    }

    /**
     * Convert daily reset time and timezone to human-readable text
     */
    static dailyResetToText(resetTime: string, timezone: string): string {
        return dailyResetToText(resetTime, timezone);
    }

    /**
     * Format a date for display in forms (YYYY-MM-DD format)
     */
    static formatDateForInput(date: Date): string {
        return formatDateForInput(date);
    }

    /**
     * Format a time for display in forms (HH:MM format)
     */
    static formatTimeForInput(date: Date): string {
        return formatTimeForInput(date);
    }

    /**
     * Combine date and time strings into a Date object
     */
    static combineDateAndTime(dateString: string, timeString: string): Date {
        return combineDateAndTime(dateString, timeString);
    }

    /**
     * Check if a date is today
     */
    static isToday(date: Date): boolean {
        return isToday(date);
    }

    /**
     * Check if a date is in the past
     */
    static isPast(date: Date): boolean {
        return isPast(date);
    }

    /**
     * Check if a date is in the future
     */
    static isFuture(date: Date): boolean {
        return isFuture(date);
    }

    /**
     * Get relative time string (e.g., "2 hours ago", "in 3 days")
     */
    static getRelativeTime(date: Date): string {
        return getRelativeTime(date);
    }
}
