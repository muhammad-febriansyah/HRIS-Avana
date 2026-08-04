/**
 * Shared types for the AvanaHR reimbursement (penggantian biaya yang sudah
 * ditalangi karyawan) module pages. These mirror the `ReimbursementController`
 * payloads (`index`, `create`, `edit`).
 */

import type { PaginationMeta } from '../employees/types';

export type { FlashProps, PaginationMeta } from '../employees/types';

/** Where a reimbursement sits in the pengajuan → approval → pembayaran flow. */
export type ReimbursementStatus = 'pending' | 'approved' | 'rejected' | 'paid';

/** A single reimbursement row as shaped by `ReimbursementController@index`. */
export interface ReimbursementRow {
    id: number;
    route_key: string;
    number: string;
    employee: {
        name: string;
        employee_number: string | null;
        initials: string;
        avatar_color: string;
    } | null;
    employee_id: number;
    category: string;
    category_label: string;
    title: string;
    amount: number;
    expense_date: string | null;
    description: string | null;
    receipt_url: string | null;
    status: ReimbursementStatus;
    status_label: string;
    notes: string | null;
    rejection_reason: string | null;
    approver: string | null;
    /** The viewer approved this one, so they may not also pay it. */
    self_approved: boolean;
    approved_at: string | null;
    paid_by: string | null;
    paid_at: string | null;
    payment_method_label: string | null;
    payment_reference: string | null;
}

/** A selectable employee `{ id, name, employee_number }`. */
export interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string | null;
}

/** A `{ value, label }` option pair. */
export interface SelectOption {
    value: string;
    label: string;
}

/** Server-side filter state carried by the index page. */
export interface ReimbursementFilters {
    search?: string;
    status?: ReimbursementStatus;
    category?: string;
    per_page?: string;
}

/** Status counters shown above the list. */
export interface ReimbursementKpis {
    pending: number;
    approved: number;
    paid: number;
    payable_amount: number;
}

/** Props for the reimbursement list page (`index.tsx`). */
export interface ReimbursementIndexProps {
    requests: {
        data: ReimbursementRow[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    filters: ReimbursementFilters;
    employees: EmployeeOption[];
    categories: SelectOption[];
    paymentMethods: SelectOption[];
    kpis: ReimbursementKpis;
}

/** Props for the reimbursement create page (`create.tsx`). */
export interface ReimbursementCreateProps {
    employees: EmployeeOption[];
    categories: SelectOption[];
}

/** Props for the reimbursement edit page (`edit.tsx`). */
export interface ReimbursementEditProps {
    reimbursement: {
        id: number;
        route_key: string;
        number: string;
        employee_id: number;
        category: string;
        title: string;
        amount: number;
        expense_date: string | null;
        description: string | null;
        receipt_url: string | null;
        notes: string | null;
    };
    employees: EmployeeOption[];
    categories: SelectOption[];
}

/** Flat form payload backing the "Ajukan Reimbursement" form. */
export interface ReimbursementFormData {
    employee_id: string;
    category: string;
    title: string;
    amount: string;
    expense_date: string;
    description: string;
    notes: string;
    receipt: File | null;
}

/** Empty defaults for the create form. */
export const emptyReimbursementForm: ReimbursementFormData = {
    employee_id: '',
    category: '',
    title: '',
    amount: '',
    expense_date: '',
    description: '',
    notes: '',
    receipt: null,
};

/** Selectable status filter options. */
export const STATUS_OPTIONS: SelectOption[] = [
    { value: 'pending', label: 'Menunggu' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'paid', label: 'Dibayar' },
    { value: 'rejected', label: 'Ditolak' },
];
