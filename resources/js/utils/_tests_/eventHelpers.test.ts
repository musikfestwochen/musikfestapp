import { PeoplecountEvent } from '@/types';
import { beforeEach, describe, expect, it } from 'vitest';
import { EventHelper, getEventDuration, getEventStatus } from '../eventHelpers';

// Mock event data for testing
const createMockEvent = (startsAt: string, endsAt: string): PeoplecountEvent => ({
    id: 1,
    name: 'Test Event',
    organization_id: 1,
    starts_at: startsAt,
    ends_at: endsAt,
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-01T00:00:00.000Z',
});

describe('EventHelper Class', () => {
    let mockCurrentTime: Date;
    let pastEvent: PeoplecountEvent;
    let activeEvent: PeoplecountEvent;
    let futureEvent: PeoplecountEvent;

    beforeEach(() => {
        // Mock current time as 2024-07-25 12:00:00 UTC
        mockCurrentTime = new Date('2024-07-25T12:00:00.000Z');

        // Past event (ended yesterday)
        pastEvent = createMockEvent('2024-07-24T10:00:00.000Z', '2024-07-24T18:00:00.000Z');

        // Active event (started 2 hours ago, ends in 6 hours)
        activeEvent = createMockEvent('2024-07-25T10:00:00.000Z', '2024-07-25T18:00:00.000Z');

        // Future event (starts tomorrow)
        futureEvent = createMockEvent('2024-07-26T10:00:00.000Z', '2024-07-26T18:00:00.000Z');
    });

    describe('constructor', () => {
        it('should create EventHelper with event and default current time', () => {
            const helper = new EventHelper(activeEvent);
            expect(helper).toBeInstanceOf(EventHelper);
        });

        it('should create EventHelper with event and custom current time', () => {
            const helper = new EventHelper(activeEvent, mockCurrentTime);
            expect(helper).toBeInstanceOf(EventHelper);
        });
    });

    describe('getStatus', () => {
        it('should return completed status for past events', () => {
            const helper = new EventHelper(pastEvent, mockCurrentTime);
            const status = helper.getStatus();

            expect(status.status).toBe('completed');
            expect(status.text).toBe('Completed');
            expect(status.class).toBe('bg-gray-100 text-gray-800');
        });

        it('should return active status for current events', () => {
            const helper = new EventHelper(activeEvent, mockCurrentTime);
            const status = helper.getStatus();

            expect(status.status).toBe('active');
            expect(status.text).toBe('Active');
            expect(status.class).toBe('bg-green-100 text-green-800');
        });

        it('should return upcoming status for future events', () => {
            const helper = new EventHelper(futureEvent, mockCurrentTime);
            const status = helper.getStatus();

            expect(status.status).toBe('upcoming');
            expect(status.text).toBe('Upcoming');
            expect(status.class).toBe('bg-blue-100 text-blue-800');
        });

        it('should return unknown status for edge case', () => {
            // Create an event with invalid dates to test edge case
            const invalidEvent = createMockEvent('invalid-date', 'invalid-date');
            const helper = new EventHelper(invalidEvent, mockCurrentTime);
            const status = helper.getStatus();

            expect(status.status).toBe('unknown');
            expect(status.text).toBe('Unknown');
            expect(status.class).toBe('bg-gray-100 text-gray-800');
        });
    });

    describe('boolean status methods', () => {
        it('should correctly identify active events', () => {
            const helper = new EventHelper(activeEvent, mockCurrentTime);
            expect(helper.isActive()).toBe(true);
            expect(helper.isUpcoming()).toBe(false);
            expect(helper.isCompleted()).toBe(false);
        });

        it('should correctly identify upcoming events', () => {
            const helper = new EventHelper(futureEvent, mockCurrentTime);
            expect(helper.isActive()).toBe(false);
            expect(helper.isUpcoming()).toBe(true);
            expect(helper.isCompleted()).toBe(false);
        });

        it('should correctly identify completed events', () => {
            const helper = new EventHelper(pastEvent, mockCurrentTime);
            expect(helper.isActive()).toBe(false);
            expect(helper.isUpcoming()).toBe(false);
            expect(helper.isCompleted()).toBe(true);
        });
    });

    describe('getDuration', () => {
        it('should calculate duration correctly for 8-hour event', () => {
            const helper = new EventHelper(activeEvent, mockCurrentTime);
            const duration = helper.getDuration();

            expect(duration.days).toBe(0);
            expect(duration.hours).toBe(8);
            expect(duration.minutes).toBe(0);
            expect(duration.totalMs).toBe(8 * 60 * 60 * 1000); // 8 hours in ms
            expect(duration.formatted).toBe('8h');
            expect(duration.formattedLong).toBe('8 hours');
        });

        it('should calculate duration for multi-day event', () => {
            const multiDayEvent = createMockEvent('2024-07-25T10:00:00.000Z', '2024-07-27T14:30:00.000Z');
            const helper = new EventHelper(multiDayEvent, mockCurrentTime);
            const duration = helper.getDuration();

            expect(duration.days).toBe(2);
            expect(duration.hours).toBe(4);
            expect(duration.minutes).toBe(30);
            expect(duration.formatted).toBe('2d 4h 30m');
            expect(duration.formattedLong).toBe('2 days 4 hours 30 minutes');
        });

        it('should handle zero duration', () => {
            const zeroDurationEvent = createMockEvent('2024-07-25T12:00:00.000Z', '2024-07-25T12:00:00.000Z');
            const helper = new EventHelper(zeroDurationEvent, mockCurrentTime);
            const duration = helper.getDuration();

            expect(duration.days).toBe(0);
            expect(duration.hours).toBe(0);
            expect(duration.minutes).toBe(0);
            expect(duration.formatted).toBe('0m');
            expect(duration.formattedLong).toBe('0 minutes');
        });

        it('should handle singular vs plural correctly', () => {
            const oneHourEvent = createMockEvent('2024-07-25T12:00:00.000Z', '2024-07-25T13:01:00.000Z');
            const helper = new EventHelper(oneHourEvent, mockCurrentTime);
            const duration = helper.getDuration();

            expect(duration.formattedLong).toBe('1 hour 1 minute');
        });
    });

    describe('getTimeRelativeToStart', () => {
        it('should calculate time until start for future events', () => {
            const helper = new EventHelper(futureEvent, mockCurrentTime);
            const relative = helper.getTimeRelativeToStart();

            expect(relative).not.toBeNull();
            // The exact values may vary due to timezone calculations, but should be reasonable
            expect(relative!.totalMs).toBeGreaterThan(0);
            expect(relative!.formatted).toMatch(/\d+[dhm]/); // Should contain digits followed by d, h, or m
        });

        it('should calculate time since start for active events', () => {
            const helper = new EventHelper(activeEvent, mockCurrentTime);
            const relative = helper.getTimeRelativeToStart();

            expect(relative).not.toBeNull();
            expect(relative!.days).toBe(0);
            expect(relative!.hours).toBe(2);
            expect(relative!.minutes).toBe(0);
            expect(relative!.formatted).toBe('2h');
        });
    });
});

describe('Static Helper Functions', () => {
    describe('static getEventStatus', () => {
        it('should return correct status for active event', () => {
            const activeEvent = createMockEvent('2024-07-25T10:00:00.000Z', '2024-07-25T18:00:00.000Z');
            const mockTime = new Date('2024-07-25T12:00:00.000Z');
            const status = EventHelper.getEventStatus(activeEvent, mockTime);

            expect(status.status).toBe('active');
            expect(status.text).toBe('Active');
        });
    });

    describe('static getEventDuration', () => {
        it('should return correct duration for event', () => {
            const event = createMockEvent('2024-07-25T10:00:00.000Z', '2024-07-25T18:00:00.000Z');
            const duration = EventHelper.getEventDuration(event);

            expect(duration.hours).toBe(8);
            expect(duration.formatted).toBe('8h');
        });
    });
});

describe('Standalone Utility Functions', () => {
    describe('getEventStatus', () => {
        it('should return correct status for active event', () => {
            const activeEvent = createMockEvent('2024-07-25T10:00:00.000Z', '2024-07-25T18:00:00.000Z');
            const mockTime = new Date('2024-07-25T12:00:00.000Z');
            const status = getEventStatus(activeEvent, mockTime);

            expect(status.status).toBe('active');
            expect(status.text).toBe('Active');
            expect(status.class).toBe('bg-green-100 text-green-800');
        });

        it('should use current time when no time provided', () => {
            const activeEvent = createMockEvent(
                new Date(Date.now() - 60000).toISOString(), // 1 minute ago
                new Date(Date.now() + 60000).toISOString(), // 1 minute from now
            );
            const status = getEventStatus(activeEvent);

            expect(status.status).toBe('active');
        });
    });

    describe('getEventDuration', () => {
        it('should return correct duration', () => {
            const event = createMockEvent('2024-07-25T10:00:00.000Z', '2024-07-25T18:30:00.000Z');
            const duration = getEventDuration(event);

            expect(duration.hours).toBe(8);
            expect(duration.minutes).toBe(30);
            expect(duration.formatted).toBe('8h 30m');
            expect(duration.formattedLong).toBe('8 hours 30 minutes');
        });
    });
});

describe('Edge Cases and Error Handling', () => {
    describe('invalid date handling', () => {
        it('should handle invalid date strings gracefully', () => {
            const invalidEvent = createMockEvent('invalid-date', 'also-invalid');
            const helper = new EventHelper(invalidEvent);

            // Should not throw, but return unknown status
            const status = helper.getStatus();
            expect(status.status).toBe('unknown');
        });
    });

    describe('boundary conditions', () => {
        it('should handle event that starts and ends at exact current time', () => {
            const mockTime = new Date('2024-07-25T12:00:00.000Z');
            const boundaryEvent = createMockEvent('2024-07-25T12:00:00.000Z', '2024-07-25T12:00:00.000Z');
            const helper = new EventHelper(boundaryEvent, mockTime);

            expect(helper.isActive()).toBe(true); // Should be active when current time equals start/end
        });

        it('should handle very long duration events', () => {
            const longEvent = createMockEvent('2024-01-01T00:00:00.000Z', '2024-12-31T23:59:59.000Z');
            const helper = new EventHelper(longEvent);
            const duration = helper.getDuration();

            expect(duration.days).toBeGreaterThan(300);
            expect(duration.formatted).toMatch(/\d+d/);
        });
    });
});
