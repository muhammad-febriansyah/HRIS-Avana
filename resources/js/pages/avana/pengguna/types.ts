/**
 * Shared types for the AvanaHR pengguna (user management) module pages. These
 * mirror the `UserController` payloads (`index`, `create`, `edit`).
 */

import type { PaginationMeta } from '../employees/types';

export type { FlashProps, PaginationMeta } from '../employees/types';

/** A role assignable to a user, as serialized by the controller. */
export interface RoleOption {
    id: number;
    name: string;
    code: string;
}

/** A tenant branch the user can be granted access to. */
export interface BranchOption {
    id: number;
    name: string;
    code: string;
}

/** A single user row as serialized by `UserController@index`. */
export interface UserRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    roles: RoleOption[];
    initials: string;
    avatar_color: string;
    data_scope: string;
    branch_ids: number[];
}

/** Active filters echoed back into the index DataTable. */
export interface PenggunaFilters {
    search?: string;
    status?: string;
    sort?: string;
    direction?: string;
    per_page?: string;
}

/** Props for the user list page (`index.tsx`). */
export interface PenggunaIndexProps {
    users: {
        data: UserRow[];
        meta: PaginationMeta;
    };
    roles: RoleOption[];
    branches: BranchOption[];
    filters: PenggunaFilters;
}

/** The flat user record prefilled into the edit form (`UserController@edit`). */
export interface UserEditRecord {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    role_ids: number[];
    data_scope: string;
    branch_ids: number[];
}

/** Flat form payload backing both the create and edit user forms. */
export interface UserFormData {
    name: string;
    email: string;
    phone: string;
    password: string;
    status: string;
    role_ids: number[];
    data_scope: string;
    branch_ids: number[];
}

/** Empty defaults for the create form. */
export const emptyUserForm: UserFormData = {
    name: '',
    email: '',
    phone: '',
    password: '',
    status: 'active',
    role_ids: [],
    data_scope: 'company',
    branch_ids: [],
};

/** Scope options surfaced in the create/edit form. */
export const scopeOptions: { value: string; label: string }[] = [
    { value: 'company', label: 'Semua Cabang (Company)' },
    { value: 'branch', label: 'Cabang Tertentu (Branch)' },
    { value: 'team', label: 'Tim (Team)' },
    { value: 'own', label: 'Data Sendiri (Own)' },
];

/** Short label for a scope value, shown as a table chip. */
export function scopeShortLabel(scope: string): string {
    switch (scope) {
        case 'branch':
            return 'Cabang';
        case 'team':
            return 'Tim';
        case 'own':
            return 'Pribadi';
        default:
            return 'Semua cabang';
    }
}
