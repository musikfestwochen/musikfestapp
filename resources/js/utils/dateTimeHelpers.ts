/**
 * Date and Time Helper Functions
 * Provides utility functions for date and time formatting and conversion
 * Includes RRULE integration and advanced timezone handling
 */

import { RRule } from 'rrule';

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
 * Parse an RRULE string and return the next N occurrences
 */
export function getNextRRuleOccurrences(rruleString: string, startDate: Date, count: number = 5): Date[] {
    try {
        const rule = RRule.fromString(rruleString);
        return rule.between(startDate, new Date(startDate.getTime() + 365 * 24 * 60 * 60 * 1000), true).slice(0, count);
    } catch (error) {
        console.error('Error parsing RRULE:', error);
        return [];
    }
}

/**
 * Validate an RRULE string
 */
export function validateRRule(rruleString: string): { isValid: boolean; error?: string } {
    if (!rruleString || rruleString.trim() === '') {
        return {
            isValid: false,
            error: 'RRULE string cannot be empty',
        };
    }

    try {
        RRule.fromString(rruleString);
        return { isValid: true };
    } catch (error) {
        return {
            isValid: false,
            error: error instanceof Error ? error.message : 'Invalid RRULE format',
        };
    }
}

/**
 * Convert RRULE to human-readable text
 */
export function rruleToText(rruleString: string): string {
    try {
        const rule = RRule.fromString(rruleString);
        return rule.toText();
    } catch {
        return 'Invalid RRULE';
    }
}

/**
 * Create an RRULE for common patterns
 */
export function createRRule(
    frequency: 'DAILY' | 'WEEKLY' | 'MONTHLY',
    options?: {
        interval?: number;
        byweekday?: number[];
        byhour?: number;
        byminute?: number;
        until?: Date;
        count?: number;
    },
): string {
    const ruleOptions: any = {
        freq: RRule[frequency],
        interval: options?.interval || 1,
    };

    if (options?.byweekday) {
        ruleOptions.byweekday = options.byweekday;
    }
    if (options?.byhour !== undefined) {
        ruleOptions.byhour = options.byhour;
    }
    if (options?.byminute !== undefined) {
        ruleOptions.byminute = options.byminute;
    }
    if (options?.until) {
        ruleOptions.until = options.until;
    }
    if (options?.count) {
        ruleOptions.count = options.count;
    }

    const rule = new RRule(ruleOptions);
    return rule.toString();
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
    const now = new Date();
    const diffMs = date.getTime() - now.getTime();
    const diffMinutes = Math.floor(diffMs / (1000 * 60));
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (Math.abs(diffMinutes) < 1) {
        return 'now';
    } else if (Math.abs(diffMinutes) < 60) {
        return diffMinutes > 0 ? `in ${diffMinutes} minutes` : `${Math.abs(diffMinutes)} minutes ago`;
    } else if (Math.abs(diffHours) < 24) {
        return diffHours > 0 ? `in ${diffHours} hours` : `${Math.abs(diffHours)} hours ago`;
    } else {
        return diffDays > 0 ? `in ${diffDays} days` : `${Math.abs(diffDays)} days ago`;
    }
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
     * Parse an RRULE string and return the next N occurrences
     */
    static getNextRRuleOccurrences(rruleString: string, startDate: Date, count: number = 5): Date[] {
        return getNextRRuleOccurrences(rruleString, startDate, count);
    }

    /**
     * Validate an RRULE string
     */
    static validateRRule(rruleString: string): { isValid: boolean; error?: string } {
        return validateRRule(rruleString);
    }

    /**
     * Convert RRULE to human-readable text
     */
    static rruleToText(rruleString: string): string {
        return rruleToText(rruleString);
    }

    /**
     * Create an RRULE for common patterns
     */
    static createRRule(
        frequency: 'DAILY' | 'WEEKLY' | 'MONTHLY',
        options?: {
            interval?: number;
            byweekday?: number[];
            byhour?: number;
            byminute?: number;
            until?: Date;
            count?: number;
        },
    ): string {
        return createRRule(frequency, options);
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
