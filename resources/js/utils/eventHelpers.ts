import { PeoplecountEvent } from '@/types';

/**
 * Event status types
 */
export type EventStatus = 'active' | 'upcoming' | 'completed' | 'unknown';

export interface EventStatusInfo {
    status: EventStatus;
    text: string;
    class: string;
}

/**
 * Duration information
 */
export interface DurationInfo {
    days: number;
    hours: number;
    minutes: number;
    totalMs: number;
    formatted: string;
    formattedLong: string;
}

/**
 * Event helper class for handling event-related business logic
 */
export class EventHelper {
    private event: PeoplecountEvent;
    private now: Date;

    constructor(event: PeoplecountEvent, currentTime?: Date) {
        this.event = event;
        this.now = currentTime || new Date();
    }

    /**
     * Get event status for a given event (static helper)
     */
    static getEventStatus(event: PeoplecountEvent, currentTime?: Date): EventStatusInfo {
        const helper = new EventHelper(event, currentTime);
        return helper.getStatus();
    }

    /**
     * Get event duration for a given event (static helper)
     */
    static getEventDuration(event: PeoplecountEvent): DurationInfo {
        const helper = new EventHelper(event);
        return helper.getDuration();
    }

    /**
     * Get the current status of the event
     */
    getStatus(): EventStatusInfo {
        const start = new Date(this.event.starts_at);
        const end = new Date(this.event.ends_at);

        if (this.now >= start && this.now <= end) {
            return {
                status: 'active',
                text: 'Active',
                class: 'bg-green-100 text-green-800',
            };
        }

        if (this.now < start) {
            return {
                status: 'upcoming',
                text: 'Upcoming',
                class: 'bg-blue-100 text-blue-800',
            };
        }

        if (this.now > end) {
            return {
                status: 'completed',
                text: 'Completed',
                class: 'bg-gray-100 text-gray-800',
            };
        }

        return {
            status: 'unknown',
            text: 'Unknown',
            class: 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Check if the event is currently active
     */
    isActive(): boolean {
        return this.getStatus().status === 'active';
    }

    /**
     * Check if the event is upcoming
     */
    isUpcoming(): boolean {
        return this.getStatus().status === 'upcoming';
    }

    /**
     * Check if the event is completed
     */
    isCompleted(): boolean {
        return this.getStatus().status === 'completed';
    }

    /**
     * Get the duration of the event
     */
    getDuration(): DurationInfo {
        const start = new Date(this.event.starts_at);
        const end = new Date(this.event.ends_at);
        const totalMs = end.getTime() - start.getTime();

        const days = Math.floor(totalMs / (1000 * 60 * 60 * 24));
        const hours = Math.floor((totalMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((totalMs % (1000 * 60 * 60)) / (1000 * 60));

        // Short format (e.g., "2d 3h 45m")
        let formatted = '';
        if (days > 0) formatted += `${days}d `;
        if (hours > 0) formatted += `${hours}h `;
        if (minutes > 0) formatted += `${minutes}m`;
        formatted = formatted.trim() || '0m';

        // Long format (e.g., "2 days 3 hours 45 minutes")
        let formattedLong = '';
        if (days > 0) formattedLong += `${days} day${days > 1 ? 's' : ''} `;
        if (hours > 0) formattedLong += `${hours} hour${hours > 1 ? 's' : ''} `;
        if (minutes > 0) formattedLong += `${minutes} minute${minutes > 1 ? 's' : ''}`;
        formattedLong = formattedLong.trim() || '0 minutes';

        return {
            days,
            hours,
            minutes,
            totalMs,
            formatted,
            formattedLong,
        };
    }

    /**
     * Get time until event starts (if upcoming) or time since event started (if active)
     */
    getTimeRelativeToStart(): DurationInfo | null {
        const start = new Date(this.event.starts_at);
        const diffMs = Math.abs(this.now.getTime() - start.getTime());

        const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

        let formatted = '';
        if (days > 0) formatted += `${days}d `;
        if (hours > 0) formatted += `${hours}h `;
        if (minutes > 0) formatted += `${minutes}m`;
        formatted = formatted.trim() || '0m';

        let formattedLong = '';
        if (days > 0) formattedLong += `${days} day${days > 1 ? 's' : ''} `;
        if (hours > 0) formattedLong += `${hours} hour${hours > 1 ? 's' : ''} `;
        if (minutes > 0) formattedLong += `${minutes} minute${minutes > 1 ? 's' : ''}`;
        formattedLong = formattedLong.trim() || '0 minutes';

        return {
            days,
            hours,
            minutes,
            totalMs: diffMs,
            formatted,
            formattedLong,
        };
    }
}

/**
 * Standalone utility functions for backward compatibility
 */

/**
 * Get the status of an event
 */
export function getEventStatus(event: PeoplecountEvent, currentTime?: Date): EventStatusInfo {
    return EventHelper.getEventStatus(event, currentTime);
}

/**
 * Get the duration of an event
 */
export function getEventDuration(event: PeoplecountEvent): DurationInfo {
    return EventHelper.getEventDuration(event);
}
