import { PeoplecountArea, PeoplecountAssignment, PeoplecountEvent } from '@/types';
import { beforeEach, describe, expect, it } from 'vitest';
import {
    areaHasAssignments,
    AreaHelper,
    getAreaAssignmentCount,
    getAreaAssignmentCountText,
    getAreaDisplayInfo,
    getAreaEventName,
} from '../areaHelpers';

// Mock event data for testing
const createMockEvent = (id: number, name: string): PeoplecountEvent => ({
    id,
    name,
    organization_id: 1,
    starts_at: '2024-07-25T10:00:00.000Z',
    ends_at: '2024-07-25T18:00:00.000Z',
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-01T00:00:00.000Z',
});

// Mock assignment data for testing
const createMockAssignment = (id: number): PeoplecountAssignment => ({
    id,
    event_id: 1,
    area_id: 1,
    sensor_id: 1,
    direction_flipped: false,
    active_from: '2024-07-25T10:00:00.000Z',
    active_to: '2024-07-25T18:00:00.000Z',
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-01T00:00:00.000Z',
});

// Mock area data for testing
const createMockArea = (id: number, name: string, event?: PeoplecountEvent, assignments?: PeoplecountAssignment[]): PeoplecountArea => ({
    id,
    name,
    event_id: event?.id || 1,
    event,
    assignments,
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-01T00:00:00.000Z',
});

describe('AreaHelper Class', () => {
    let mockEvent: PeoplecountEvent;
    let areaWithoutAssignments: PeoplecountArea;
    let areaWithOneAssignment: PeoplecountArea;
    let areaWithMultipleAssignments: PeoplecountArea;
    let areaWithoutEvent: PeoplecountArea;

    beforeEach(() => {
        mockEvent = createMockEvent(1, 'Test Event');

        // Area without assignments
        areaWithoutAssignments = createMockArea(1, 'Area 1', mockEvent, []);

        // Area with one assignment
        areaWithOneAssignment = createMockArea(2, 'Area 2', mockEvent, [createMockAssignment(1)]);

        // Area with multiple assignments
        areaWithMultipleAssignments = createMockArea(3, 'Area 3', mockEvent, [
            createMockAssignment(1),
            createMockAssignment(2),
            createMockAssignment(3),
        ]);

        // Area without event
        areaWithoutEvent = createMockArea(4, 'Area 4');
    });

    describe('constructor', () => {
        it('should create AreaHelper with area', () => {
            const helper = new AreaHelper(areaWithoutAssignments);
            expect(helper).toBeInstanceOf(AreaHelper);
        });
    });

    describe('getAssignmentCount', () => {
        it('should return 0 for area without assignments', () => {
            const helper = new AreaHelper(areaWithoutAssignments);
            expect(helper.getAssignmentCount()).toBe(0);
        });

        it('should return 1 for area with one assignment', () => {
            const helper = new AreaHelper(areaWithOneAssignment);
            expect(helper.getAssignmentCount()).toBe(1);
        });

        it('should return correct count for area with multiple assignments', () => {
            const helper = new AreaHelper(areaWithMultipleAssignments);
            expect(helper.getAssignmentCount()).toBe(3);
        });

        it('should return 0 for area with undefined assignments', () => {
            const areaWithUndefinedAssignments = createMockArea(5, 'Area 5', mockEvent);
            const helper = new AreaHelper(areaWithUndefinedAssignments);
            expect(helper.getAssignmentCount()).toBe(0);
        });
    });

    describe('getAssignmentCountText', () => {
        it('should return singular text for 0 assignments', () => {
            const helper = new AreaHelper(areaWithoutAssignments);
            expect(helper.getAssignmentCountText()).toBe('0 assignments');
        });

        it('should return singular text for 1 assignment', () => {
            const helper = new AreaHelper(areaWithOneAssignment);
            expect(helper.getAssignmentCountText()).toBe('1 assignment');
        });

        it('should return plural text for multiple assignments', () => {
            const helper = new AreaHelper(areaWithMultipleAssignments);
            expect(helper.getAssignmentCountText()).toBe('3 assignments');
        });
    });

    describe('hasAssignments', () => {
        it('should return false for area without assignments', () => {
            const helper = new AreaHelper(areaWithoutAssignments);
            expect(helper.hasAssignments()).toBe(false);
        });

        it('should return true for area with assignments', () => {
            const helper = new AreaHelper(areaWithOneAssignment);
            expect(helper.hasAssignments()).toBe(true);
        });

        it('should return false for area with undefined assignments', () => {
            const areaWithUndefinedAssignments = createMockArea(5, 'Area 5', mockEvent);
            const helper = new AreaHelper(areaWithUndefinedAssignments);
            expect(helper.hasAssignments()).toBe(false);
        });
    });

    describe('getEventName', () => {
        it('should return event name when event exists', () => {
            const helper = new AreaHelper(areaWithoutAssignments);
            expect(helper.getEventName()).toBe('Test Event');
        });

        it('should return "Unknown Event" when event is undefined', () => {
            const helper = new AreaHelper(areaWithoutEvent);
            expect(helper.getEventName()).toBe('Unknown Event');
        });
    });

    describe('getDisplayInfo', () => {
        it('should return complete display info for area with assignments', () => {
            const helper = new AreaHelper(areaWithMultipleAssignments);
            const displayInfo = helper.getDisplayInfo();

            expect(displayInfo).toEqual({
                name: 'Area 3',
                eventName: 'Test Event',
                assignmentCount: 3,
                assignmentText: '3 assignments',
                hasAssignments: true,
            });
        });

        it('should return complete display info for area without assignments', () => {
            const helper = new AreaHelper(areaWithoutAssignments);
            const displayInfo = helper.getDisplayInfo();

            expect(displayInfo).toEqual({
                name: 'Area 1',
                eventName: 'Test Event',
                assignmentCount: 0,
                assignmentText: '0 assignments',
                hasAssignments: false,
            });
        });

        it('should handle area without event', () => {
            const helper = new AreaHelper(areaWithoutEvent);
            const displayInfo = helper.getDisplayInfo();

            expect(displayInfo).toEqual({
                name: 'Area 4',
                eventName: 'Unknown Event',
                assignmentCount: 0,
                assignmentText: '0 assignments',
                hasAssignments: false,
            });
        });
    });
});

describe('Standalone Utility Functions', () => {
    let mockEvent: PeoplecountEvent;
    let areaWithoutAssignments: PeoplecountArea;
    let areaWithOneAssignment: PeoplecountArea;
    let areaWithMultipleAssignments: PeoplecountArea;
    let areaWithoutEvent: PeoplecountArea;

    beforeEach(() => {
        mockEvent = createMockEvent(1, 'Test Event');
        areaWithoutAssignments = createMockArea(1, 'Area 1', mockEvent, []);
        areaWithOneAssignment = createMockArea(2, 'Area 2', mockEvent, [createMockAssignment(1)]);
        areaWithMultipleAssignments = createMockArea(3, 'Area 3', mockEvent, [
            createMockAssignment(1),
            createMockAssignment(2),
            createMockAssignment(3),
        ]);
        areaWithoutEvent = createMockArea(4, 'Area 4');
    });

    describe('getAreaAssignmentCount', () => {
        it('should return correct assignment count', () => {
            expect(getAreaAssignmentCount(areaWithoutAssignments)).toBe(0);
            expect(getAreaAssignmentCount(areaWithOneAssignment)).toBe(1);
            expect(getAreaAssignmentCount(areaWithMultipleAssignments)).toBe(3);
        });

        it('should handle undefined assignments', () => {
            const areaWithUndefinedAssignments = createMockArea(5, 'Area 5', mockEvent);
            expect(getAreaAssignmentCount(areaWithUndefinedAssignments)).toBe(0);
        });
    });

    describe('getAreaAssignmentCountText', () => {
        it('should return correct assignment count text', () => {
            expect(getAreaAssignmentCountText(areaWithoutAssignments)).toBe('0 assignments');
            expect(getAreaAssignmentCountText(areaWithOneAssignment)).toBe('1 assignment');
            expect(getAreaAssignmentCountText(areaWithMultipleAssignments)).toBe('3 assignments');
        });
    });

    describe('areaHasAssignments', () => {
        it('should correctly identify areas with and without assignments', () => {
            expect(areaHasAssignments(areaWithoutAssignments)).toBe(false);
            expect(areaHasAssignments(areaWithOneAssignment)).toBe(true);
            expect(areaHasAssignments(areaWithMultipleAssignments)).toBe(true);
        });

        it('should handle undefined assignments', () => {
            const areaWithUndefinedAssignments = createMockArea(5, 'Area 5', mockEvent);
            expect(areaHasAssignments(areaWithUndefinedAssignments)).toBe(false);
        });
    });

    describe('getAreaEventName', () => {
        it('should return event name when event exists', () => {
            expect(getAreaEventName(areaWithoutAssignments)).toBe('Test Event');
        });

        it('should return "Unknown Event" when event is undefined', () => {
            expect(getAreaEventName(areaWithoutEvent)).toBe('Unknown Event');
        });
    });

    describe('getAreaDisplayInfo', () => {
        it('should return complete display info', () => {
            const displayInfo = getAreaDisplayInfo(areaWithMultipleAssignments);

            expect(displayInfo).toEqual({
                name: 'Area 3',
                eventName: 'Test Event',
                assignmentCount: 3,
                assignmentText: '3 assignments',
                hasAssignments: true,
            });
        });

        it('should handle edge cases', () => {
            const displayInfo = getAreaDisplayInfo(areaWithoutEvent);

            expect(displayInfo).toEqual({
                name: 'Area 4',
                eventName: 'Unknown Event',
                assignmentCount: 0,
                assignmentText: '0 assignments',
                hasAssignments: false,
            });
        });
    });
});

describe('Edge Cases and Error Handling', () => {
    it('should handle null assignments gracefully', () => {
        const areaWithNullAssignments = {
            ...createMockArea(1, 'Test Area'),
            assignments: null as any,
        };

        expect(getAreaAssignmentCount(areaWithNullAssignments)).toBe(0);
        expect(getAreaAssignmentCountText(areaWithNullAssignments)).toBe('0 assignments');
        expect(areaHasAssignments(areaWithNullAssignments)).toBe(false);
    });

    it('should handle null event gracefully', () => {
        const areaWithNullEvent = {
            ...createMockArea(1, 'Test Area'),
            event: null as any,
        };

        expect(getAreaEventName(areaWithNullEvent)).toBe('Unknown Event');
    });

    it('should handle empty area name', () => {
        const areaWithEmptyName = createMockArea(1, '');
        const helper = new AreaHelper(areaWithEmptyName);
        const displayInfo = helper.getDisplayInfo();

        expect(displayInfo.name).toBe('');
    });
});
