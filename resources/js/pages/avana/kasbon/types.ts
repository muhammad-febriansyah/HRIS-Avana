/**
 * Shared types for the AvanaHR kasbon (cash advance) module pages. These
 * mirror the `CashAdvanceController` payloads (`index`, `create`).
 */

import type { PaginationMeta } from '../employees/types';

export type { FlashProps, PaginationMeta } from '../employees/types';

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
    installments: number;
    monthly_deduction: number;
    request_date: string | null;
    reason: string | null;
    status: 'pending' | 'approved' | 'rejected' | 'paid';
    status_label: 'Menunggu' | 'Disetujui' | 'Ditolak' | 'Lunas';
}

/** A selectable employee `{ id, name, employee_number }`. */
export interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

/** Server-side filter state carried by the index page. */
export interface KasbonFilters {
    search?: string;
    status?: 'pending' | 'approved' | 'rejected' | 'paid';
    per_page?: string;
}

/** Props for the kasbon list page (`index.tsx`). */
export interface KasbonIndexProps {
    requests: {
        data: CashAdvanceRow[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    filters: KasbonFilters;
    employees: EmployeeOption[];
}

/** Props for the kasbon create page (`create.tsx`). */
export interface KasbonCreateProps {
    employees: EmployeeOption[];
}

/** Flat form payload backing the "Ajukan Kasbon" form. */
export interface CashAdvanceFormData {
    employee_id: string;
    amount: string;
    installments: string;
    request_date: string;
    reason: string;
}

/** Empty defaults for the create form. */
export const emptyKasbonForm: CashAdvanceFormData = {
    employee_id: '',
    amount: '',
    installments: '1',
    request_date: '',
    reason: '',
};

/** Selectable status filter options. */
export const STATUS_OPTIONS: { value: string; label: string }[] = [
    { value: 'pending', label: 'Menunggu' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'rejected', label: 'Ditolak' },
    { value: 'paid', label: 'Lunas' },
];
