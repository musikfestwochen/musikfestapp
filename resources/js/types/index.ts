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
    children?: NavItem[];
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
    eastereggs_activated: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    organizations?: Organization[];
    organizations_count?: number;
    organization_roles?: RoleOption[];
    area_single_resets?: PeoplecountAreaSingleReset[]; // Optional, for related area single resets created by this user
}

export interface RoleOption {
    name: string;
    display_name: string | null;
    description: string | null;
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
    stage_safety_sensors?: StageSafetySensor[];
}

export interface StageSafetySensor {
    id: number;
    organization_id: number;
    manufacturer: string;
    model: string;
    identifier: string;
    name: string | null;
    location: string | null;
    stale_after_seconds: number;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
    has_active_token?: boolean;
}

export interface StageSafetySensorType {
    manufacturer: string;
    model: string;
    label: string;
}

export interface StageSafetySensorFormData {
    manufacturer: string;
    model: string;
    identifier: string;
    name: string | null;
    location: string | null;
    stale_after_seconds: number;
}

export interface StageSafetySensorCreatedResponse {
    sensor: StageSafetySensor;
    token: string;
}

export type StageSafetySensorHealthStatus = 'fresh' | 'stale' | 'never_seen' | 'archived';
export type StageSafetyReadingKind = 'wind_average' | 'wind_gust';

export interface StageSafetySensorSummary {
    id: number;
    identifier: string;
    name: string | null;
    location: string | null;
    stale_after_seconds: number;
}

export interface StageSafetyHistoryReading {
    kind: StageSafetyReadingKind;
    value: number;
    unit: 'm/s';
    observed_at: string;
    window_seconds: number | null;
}

export interface StageSafetyCurrentReading extends StageSafetyHistoryReading {
    status: 'fresh' | 'stale';
    received_at: string;
    receipt_delay_seconds: number;
}

export interface StageSafetyCurrentSensor {
    sensor: StageSafetySensorSummary;
    status: StageSafetySensorHealthStatus;
    latest_observed_at: string | null;
    radio_diagnostics: {
        battery_low: boolean | null;
        rssi_dbm: number | null;
        cv: number | null;
    } | null;
    wind_average: StageSafetyCurrentReading | null;
    wind_gust: StageSafetyCurrentReading | null;
}

export interface StageSafetyCurrentWindPayload {
    generated_at: string;
    sensors: StageSafetyCurrentSensor[];
}

export interface StageSafetyHealthSensor extends StageSafetySensorSummary {
    status: 'fresh' | 'stale' | 'never_seen';
    latest_observed_at: string | null;
}

export interface StageSafetySensorHealthPayload {
    generated_at: string;
    total: number;
    all_fresh: boolean;
    fresh: StageSafetyHealthSensor[];
    stale: StageSafetyHealthSensor[];
    never_seen: StageSafetyHealthSensor[];
}

export interface StageSafetyHistorySensor {
    sensor: StageSafetySensorSummary;
    readings: StageSafetyHistoryReading[];
}

export interface StageSafetyWindHistoryPayload {
    generated_at: string;
    from: string;
    to: string;
    sensors: StageSafetyHistorySensor[];
}

export interface StageSafetyLqiHistorySample {
    observed_at: string;
    lqi_percent: number;
}

export interface StageSafetyLqiHistorySensor {
    sensor: StageSafetySensorSummary;
    samples: StageSafetyLqiHistorySample[];
}

export interface StageSafetyLqiHistoryPayload {
    generated_at: string;
    from: string;
    to: string;
    sensors: StageSafetyLqiHistorySensor[];
}

export interface PeoplecountSensor {
    id: number;
    vendor: string;
    model: string;
    serial: string;
    name?: string | null;
    organization_id: number;
    archived_at?: string | null;
    has_active_token?: boolean;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
    interval_counts?: PeopleCountIntervalCount[]; // Optional, for related interval counts
    organization?: Organization; // Optional, for related organization
    assignments?: PeoplecountAssignment[]; // Optional, for related assignments
    shares?: PeoplecountSensorShare[]; // Optional, for related shares
}

export interface PeoplecountSensorFormData {
    vendor: string;
    model: string;
    serial: string;
    name: string | null;
}

export interface PeoplecountSensorCreatedResponse {
    sensor: PeoplecountSensor;
    token: string;
}

export interface PeoplecountSensorShare {
    id: number;
    sensor_id: number;
    owner_organization_id: number;
    borrower_organization_id: number;
    starts_at: string;
    ends_at: string;
    created_at: string;
    updated_at: string;
    assignments_count?: number;
    sensor?: PeoplecountSensor;
    borrower_organization?: Organization;
    owner_organization?: Organization;
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
    area_single_resets?: PeoplecountAreaSingleReset[]; // Optional, for related single resets
    area_recurring_resets?: PeoplecountAreaRecurringReset[]; // Optional, for related recurring resets
}

export interface PeoplecountAssignment {
    id: number;
    event_id: number;
    area_id: number;
    sensor_id: number;
    sensor_share_id?: number | null;
    label?: string | null;
    direction_flipped: boolean;
    active_from: string; // ISO 8601 date-time string (UTC)
    active_to: string; // ISO 8601 date-time string (UTC)
    created_at: string;
    updated_at: string;
    deleted_at?: string;
    event?: PeoplecountEvent; // Optional, for related event
    area?: PeoplecountArea; // Optional, for related area
    sensor?: PeoplecountSensor; // Optional, for related sensor
}

export interface PeoplecountAreaSingleReset {
    id: number;
    area_id: number;
    reset_value: number;
    effective_at: string; // ISO 8601 date-time string (UTC)
    created_by: User;
    notes?: string;
    created_at: string;
    updated_at: string;
    area?: PeoplecountArea; // Optional, for related area
}

export interface PeoplecountAreaRecurringReset {
    id: number;
    area_id: number;
    reset_value: number;
    reset_time: string;
    timezone: string;
    next_occurrence: string; // ISO 8601 date-time string (UTC)
    notes?: string;
    created_at: string;
    updated_at: string;
    area?: PeoplecountArea; // Optional, for related area
}
