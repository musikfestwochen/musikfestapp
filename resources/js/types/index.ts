import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    route?: string; // Route name (internal Inertia route)
    params?: Record<string, any>; // Route parameters for the internal route
    url?: string; // External URL
    icon?: LucideIcon;
    permission?: string; // Permission required to view this item
}

export interface Token {
    abilities: string[];
    created_at: string;
    expires_at: string | null;
    id: number;
    last_used_at: string | null;
    name: string;
    tokenable_id: number;
    tokenable_type: string;
    updated_at: string;
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
}

export interface User {
    id: number;
    name: string;
    email: string;
    phone?: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface Organization {
    id: number;
    name: string;
    slug: string;
    description?: string;
    email?: string;
    phone?: string;
    website?: string;
    logo?: string;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
    peoplecount_sensors?: PeoplecountSensor[]; // Optional, for related sensors
    peoplecount_events?: PeoplecountEvent[]; // Optional, for related events
    peoplecount_areas?: PeoplecountArea[]; // Optional, for related areas
    peoplecount_assignments?: PeoplecountAssignment[]; // Optional, for related assignments
}

export interface PeoplecountSensor {
    id: number;
    vendor: string;
    model: string;
    serial: string;
    organization_id: number;
    api_token?: string | null; // Plaintext API token, nullable
    created_at: string;
    updated_at: string;
    deleted_at?: string;
    interval_counts?: PeopleCountIntervalCount[]; // Optional, for related interval counts
    organization?: Organization; // Optional, for related organization
    assignments?: PeoplecountAssignment[]; // Optional, for related assignments
}

export interface PeopleCountIntervalCount {
    id: number;
    sensor_id: number;
    ts_from: string; // ISO 8601 date-time string
    ts_to: string; // ISO 8601 date-time string
    count_in: number;
    count_out: number;
    sensor?: PeoplecountSensor; // Optional, for related sensor
}

export interface PeoplecountEvent {
    id: number;
    name: string;
    organization_id: number;
    starts_at: string; // ISO 8601 date-time string (UTC)
    ends_at: string; // ISO 8601 date-time string (UTC)
    created_at: string;
    updated_at: string;
    deleted_at?: string;
    organization?: Organization; // Optional, for related organization
    areas?: PeoplecountArea[]; // Optional, for related areas
    assignments?: PeoplecountAssignment[]; // Optional, for related assignments
}

export interface PeoplecountArea {
    id: number;
    name: string;
    event_id: number;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
    event?: PeoplecountEvent; // Optional, for related event
    assignments?: PeoplecountAssignment[]; // Optional, for related assignments
}

export interface PeoplecountAssignment {
    id: number;
    event_id: number;
    area_id: number;
    sensor_id: number;
    direction: 'in' | 'out';
    active_from: string; // ISO 8601 date-time string (UTC)
    active_to: string; // ISO 8601 date-time string (UTC)
    created_at: string;
    updated_at: string;
    deleted_at?: string;
    event?: PeoplecountEvent; // Optional, for related event
    area?: PeoplecountArea; // Optional, for related area
    sensor?: PeoplecountSensor; // Optional, for related sensor
}
