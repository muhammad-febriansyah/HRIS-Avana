/**
 * Shared types for the AvanaHR cash advance (uang muka operasional) module
 * pages. These mirror the `CashAdvanceController` payloads (`index`, `create`,
 * `edit`).
 */

import type { PaginationMeta } from '../employees/types';

export type { FlashProps, PaginationMeta } from '../employees/types';

/** Where a cash advance sits in the pengajuan → approval → pencairan flow. */
export type CashAdvanceStatus =
    'pending' | 'approved' | 'rejected' | 'disbursed' | 'settled';

/** A single cash advance row as shaped by `CashAdvanceController@index`. */
export interface CashAdvanceRow {
    id: number;
    employee: {
        name: string;
        employee_number: string | null;
        initials: string;
        avatar_color: string;
    } | null;
    amount: number;
    purpose: string | null;
    request_date: string | null;
    needed_date: string | null;
    reason: string | null;
    status: CashAdvanceStatus;
    status_label: string;
    disbursed_at: string | null;
    disbursement_method: string | null;
    disbursement_method_label: string | null;
    disbursement_reference: string | null;
    settlement_id: number | null;
    settlement_status: string | null;
}

/** A selectable employee `{ id, name, employee_number }`. */
export interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

/** A `{ value, label }` option pair. */
export interface SelectOption {
    value: string;
    label: string;
}

/** Server-side filter state carried by the index page. */
export interface KasbonFilters {
    search?: string;
    status?: CashAdvanceStatus;
    per_page?: string;
}

/** Status counters shown above the list. */
export interface KasbonKpis {
    pending: number;
    approved: number;
    disbursed: number;
    outstanding_amount: number;
}

/** Props for the cash advance list page (`index.tsx`). */
export interface KasbonIndexProps {
    requests: {
        data: CashAdvanceRow[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    filters: KasbonFilters;
    employees: EmployeeOption[];
    disbursementMethods: SelectOption[];
    kpis: KasbonKpis;
}

/** Props for the cash advance create page (`create.tsx`). */
export interface KasbonCreateProps {
    employees: EmployeeOption[];
}

/** Props for the cash advance edit page (`edit.tsx`). */
export interface KasbonEditProps {
    advance: {
        id: number;
        employee_id: number;
        amount: number;
        purpose: string | null;
        request_date: string | null;
        needed_date: string | null;
        reason: string | null;
    };
    employees: EmployeeOption[];
}

/** Flat form payload backing the "Ajukan Uang Muka" form. */
export interface CashAdvanceFormData {
    employee_id: string;
    amount: string;
    purpose: string;
    request_date: string;
    needed_date: string;
    reason: string;
}

/** Empty defaults for the create form. */
export const emptyKasbonForm: CashAdvanceFormData = {
    employee_id: '',
    amount: '',
    purpose: '',
    request_date: '',
    needed_date: '',
    reason: '',
};

/** Selectable status filter options. */
export const STATUS_OPTIONS: SelectOption[] = [
    { value: 'pending', label: 'Menunggu' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'disbursed', label: 'Dicairkan' },
    { value: 'settled', label: 'Selesai' },
    { value: 'rejected', label: 'Ditolak' },
];
