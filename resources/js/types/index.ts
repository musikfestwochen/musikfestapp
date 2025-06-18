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

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
}

export interface User {
    id: number;
    name: string;
    email: string;
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
}
