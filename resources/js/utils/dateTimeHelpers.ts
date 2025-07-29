/**
 * Date and Time Helper Functions
 * Provides utility functions for date and time formatting and conversion
 */

/**
 * Format a date/time in the user's local timezone
 */
export function formatLocalDateTime(utcString: string, options?: Intl.DateTimeFormatOptions): string {
    const date = new Date(utcString);
    const defaultOptions: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        timeZoneName: 'short',
    };

    // Use 'en-US' locale to ensure consistent formatting regardless of system locale
    return date.toLocaleString('en-US', { ...defaultOptions, ...options });
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
}
