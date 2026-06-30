/**
 * Shared types for the AvanaHR kontrak (employee contract) module pages.
 * These mirror the `ContractController` payloads (`index`, `create`, `edit`).
 */

import { C } from '@/lib/avana';
import type { PaginationMeta } from '../employees/types';

export type { FlashProps, PaginationMeta } from '../employees/types';

/** An employee selectable in the contract create/edit form. */
export interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

/** Compact employee reference serialized on a contract row. */
export interface ContractEmployee {
    name: string;
    employee_number: string;
}

/** A single contract row as serialized by `ContractController@index`. */
export interface ContractRow {
    id: number;
    contract_number: string;
    employee: ContractEmployee | null;
    employee_id: number;
    contract_type: string;
    start_date: string | null;
    end_date: string | null;
    basic_salary: number;
    status: string;
    notes: string | null;
    expiring_soon: boolean;
    days_to_expiry: number | null;
}

/** Header strip counters serialized by the controller. */
export interface KontrakStats {
    total: number;
    active: number;
    expiring_soon: number;
}

export interface KontrakFilters {
    search?: string;
    status?: string;
    per_page?: string;
}

/** Props for the kontrak list page (`index.tsx`). */
export interface KontrakIndexProps {
    contracts: {
        data: ContractRow[];
        meta: PaginationMeta;
    };
    employees: EmployeeOption[];
    stats: KontrakStats;
    filters: KontrakFilters;
}

/** Flat form payload backing both the create and edit contract forms. */
export interface ContractFormData {
    employee_id: string;
    contract_number: string;
    contract_type: string;
    start_date: string;
    end_date: string;
    basic_salary: string;
    status: string;
    notes: string;
}

/** Empty defaults for the create form. */
export const emptyContractForm: ContractFormData = {
    employee_id: '',
    contract_number: '',
    contract_type: 'pkwt',
    start_date: '',
    end_date: '',
    basic_salary: '',
    status: 'active',
    notes: '',
};

/** Contract type options surfaced in the create/edit form. */
export const contractTypeOptions: { value: string; label: string }[] = [
    { value: 'pkwt', label: 'PKWT (Kontrak)' },
    { value: 'pkwtt', label: 'PKWTT (Tetap)' },
    { value: 'probation', label: 'Probation' },
];

/** Status options surfaced in the create/edit form and the filter bar. */
export const statusOptions: { value: string; label: string }[] = [
    { value: 'active', label: 'Aktif' },
    { value: 'expired', label: 'Berakhir' },
    { value: 'terminated', label: 'Diberhentikan' },
];

/** Short label for a contract type code. */
export function contractTypeLabel(type: string): string {
    return (
        contractTypeOptions.find((option) => option.value === type)?.label ??
        type.toUpperCase()
    );
}

/** Status pill colors for a contract status. */
export function statusPill(status: string): {
    label: string;
    color: string;
    bg: string;
} {
    switch (status) {
        case 'active':
            return { label: 'Aktif', color: C.green, bg: 'rgba(22,163,74,.1)' };
        case 'expired':
            return {
                label: 'Berakhir',
                color: C.muted,
                bg: 'rgba(107,114,128,.12)',
            };
        case 'terminated':
            return {
                label: 'Diberhentikan',
                color: C.red,
                bg: 'rgba(220,38,38,.1)',
            };
        default:
            return {
                label: status,
                color: C.muted,
                bg: 'rgba(107,114,128,.12)',
            };
    }
}

/** Format an ISO date string to Indonesian short form. */
export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
