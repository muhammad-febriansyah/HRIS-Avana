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

/** A selectable subscription package option. */
export interface PackageOption {
    id: number;
    name: string;
    code: string;
    max_users: number;
    max_employees: number;
    max_branches: number;
}

/** A toggleable feature module option. */
export interface FeatureOption {
    id: number;
    code: string;
    name: string;
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
    billing_status: '',
    start_date: '',
    end_date: '',
};

/** Selectable tenant status enum options. */
export const STATUS_OPTIONS: { value: string; label: string }[] = [
    { value: 'trial', label: 'Trial' },
    { value: 'active', label: 'Aktif' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'inactive', label: 'Nonaktif' },
];
