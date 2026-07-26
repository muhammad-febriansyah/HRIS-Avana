/**
 * Shared types for the AvanaHR klien (tenant) module pages. These mirror the
 * `TenantController` payloads (`index`, `create`, `edit`).
 */

export type { FlashProps, PaginationMeta } from '../employees/types';

/** A single tenant row as serialized by `TenantController@index`. */
export interface TenantRow {
    id: number;
    name: string;
    slug: string;
    company_name: string | null;
    logo: string | null;
    status: string;
    billing_status: string | null;
    package: { id: number; name: string } | null;
    max_users: number;
    max_employees: number;
    max_branches: number;
    users_count: number;
    employees_count: number;
    branches_count: number;
    start_date: string | null;
    end_date: string | null;
    feature_codes: string[];
}

/** A selectable subscription package option, with the pricing behind it. */
export interface PackageOption {
    id: number;
    name: string;
    tagline?: string | null;
    code: string;
    price?: number;
    billing_cycle?: string;
    max_users: number;
    max_employees: number;
    max_branches: number;
    ai_token_quota?: number;
    is_popular?: boolean;
}

/** A toggleable feature module option. */
export interface FeatureOption {
    key: string;
    id: number | null;
    code: string | null;
    name: string;
    group: string;
    core: boolean;
}

/** Active filter values carried on the list page. */
export interface KlienFilters {
    search?: string;
    status?: string;
}

/** Flat tenant record backing the edit form (`TenantController@edit`). */
export interface TenantRecord {
    id: number;
    name: string;
    company_name: string | null;
    slug: string;
    package_id: number | null;
    status: string;
    max_users: number;
    max_employees: number;
    max_branches: number;
    billing_status: string | null;
    start_date: string | null;
    end_date: string | null;
}

/** Flat string-only payload backing both the create and edit tenant forms. */
export interface TenantFormData {
    name: string;
    company_name: string;
    slug: string;
    package_id: string;
    status: string;
    max_users: string;
    max_employees: string;
    max_branches: string;
    billing_status: string;
    start_date: string;
    end_date: string;
    /** Subscription period inputs the wizard derives `end_date` from. */
    billing_cycle: string;
    trial_days: string;
    /** Admin Tenant / HR login created together with the client (create only). */
    admin_name: string;
    admin_email: string;
    admin_password: string;
}

/** Empty defaults for the create form. */
export const emptyTenantForm: TenantFormData = {
    name: '',
    company_name: '',
    slug: '',
    package_id: '',
    status: 'trial',
    max_users: '',
    max_employees: '',
    max_branches: '',
    billing_status: 'active',
    start_date: '',
    end_date: '',
    billing_cycle: 'monthly',
    trial_days: '14',
    admin_name: '',
    admin_email: '',
    admin_password: '',
};

/** Billing status options for a client's account standing. */
export const BILLING_STATUS_OPTIONS: { value: string; label: string }[] = [
    { value: 'active', label: 'Lancar' },
    { value: 'pending', label: 'Menunggu Pembayaran' },
    { value: 'overdue', label: 'Menunggak' },
];

/** One Admin Tenant / HR account of a client, as listed on the tenant page. */
export interface TenantAdmin {
    id: number;
    name: string;
    email: string;
    status: string;
    created_at: string | null;
}

/** One-time credential hand-off flashed after creating or resetting an admin. */
export interface TenantCredentials {
    name: string;
    email: string;
    password: string;
}

/** Selectable tenant status enum options. */
export const STATUS_OPTIONS: { value: string; label: string }[] = [
    { value: 'trial', label: 'Trial' },
    { value: 'active', label: 'Aktif' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'inactive', label: 'Nonaktif' },
];
