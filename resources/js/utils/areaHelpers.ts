import { PeoplecountArea } from '@/types';

/**
 * Area Helper Class
 * Provides utility methods for area-related operations
 */
export class AreaHelper {
    private area: PeoplecountArea;

    constructor(area: PeoplecountArea) {
        this.area = area;
    }

    /**
     * Get the number of assignments for this area
     */
    getAssignmentCount(): number {
        return this.area.assignments?.length || 0;
    }

    /**
     * Get formatted assignment count text
     */
    getAssignmentCountText(): string {
        const count = this.getAssignmentCount();
        return `${count} assignment${count !== 1 ? 's' : ''}`;
    }

    /**
     * Check if the area has any assignments
     */
    hasAssignments(): boolean {
        return this.getAssignmentCount() > 0;
    }

    /**
     * Get the event name this area belongs to
     */
    getEventName(): string {
        return this.area.event?.name || 'Unknown Event';
    }

    /**
     * Get area display information
     */
    getDisplayInfo(): {
        name: string;
        eventName: string;
        assignmentCount: number;
        assignmentText: string;
        hasAssignments: boolean;
    } {
        return {
            name: this.area.name,
            eventName: this.getEventName(),
            assignmentCount: this.getAssignmentCount(),
            assignmentText: this.getAssignmentCountText(),
            hasAssignments: this.hasAssignments(),
        };
    }
}

/**
 * Standalone utility functions for backward compatibility
 */

/**
 * Get the number of assignments for an area
 */
export function getAreaAssignmentCount(area: PeoplecountArea): number {
    return area.assignments?.length || 0;
}

/**
 * Get formatted assignment count text for an area
 */
export function getAreaAssignmentCountText(area: PeoplecountArea): string {
    const count = getAreaAssignmentCount(area);
    return `${count} assignment${count !== 1 ? 's' : ''}`;
}

/**
 * Check if an area has any assignments
 */
export function areaHasAssignments(area: PeoplecountArea): boolean {
    return getAreaAssignmentCount(area) > 0;
}

/**
 * Get the event name for an area
 */
export function getAreaEventName(area: PeoplecountArea): string {
    return area.event?.name || 'Unknown Event';
}

/**
 * Get area display information
 */
export function getAreaDisplayInfo(area: PeoplecountArea): {
    name: string;
    eventName: string;
    assignmentCount: number;
    assignmentText: string;
    hasAssignments: boolean;
} {
    return {
        name: area.name,
        eventName: getAreaEventName(area),
        assignmentCount: getAreaAssignmentCount(area),
        assignmentText: getAreaAssignmentCountText(area),
        hasAssignments: areaHasAssignments(area),
    };
}
