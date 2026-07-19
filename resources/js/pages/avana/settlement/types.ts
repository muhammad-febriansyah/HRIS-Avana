/**
 * Shared types for the AvanaHR settlement module pages. Settlements are
 * standalone expense claims: line items + 11% tax, manager approval, then
 * Finance verification & payout. These mirror `SettlementController`.
 */

import type { PaginationMeta } from '../employees/types';

export type { FlashProps, PaginationMeta } from '../employees/types';

/** Where a settlement sits in the submit → manager → finance → paid flow. */
export type SettlementStatus =
    | 'draft'
    | 'submitted'
    | 'manager_approved'
    | 'paid'
    | 'rejected';

/** The employee the claim belongs to. */
export interface SettlementEmployee {
    name: string;
    employee_number: string | null;
    initials: string;
    avatar_color: string;
}

/** One expense line backing a settlement. */
export interface SettlementItemRow {
    id: number;
    category: string;
    category_label: string;
    description: string;
    /** Specifics under the category — flight number, hotel + nights, route. */
    detail: string | null;
    amount: number;
}

/** One fraud/tamper flag raised against an attachment. */
export interface FraudFlag {
    code: string;
    label: string;
    severity: 'low' | 'medium' | 'high';
}

/** One supporting document uploaded against a settlement. */
export interface SettlementAttachmentRow {
    id: number;
    name: string;
    url: string;
    fraud_score?: number | null;
    fraud_level?: 'low' | 'medium' | 'high' | null;
    fraud_flags?: FraudFlag[];
    extracted_amount?: number | null;
    extracted_vendor?: string | null;
    vision_summary?: string | null;
    analyzed_at?: string | null;
}

/** A settlement as shaped by `SettlementController@index`. */
export interface SettlementRow {
    id: number;
    number: string | null;
    employee: SettlementEmployee | null;
    title: string;
    category: string | null;
    category_label: string | null;
    department: string | null;
    submission_date: string | null;
    subtotal: number;
    tax_amount: number;
    total: number;
    status: SettlementStatus;
    status_label: string;
}

/** A settlement with its line items + payout + approval trail (`@show`). */
export interface SettlementDetail extends SettlementRow {
    destination: string | null;
    trip_start_date: string | null;
    trip_end_date: string | null;
    trip_days: number | null;
    bank_name: string | null;
    bank_account_number: string | null;
    bank_account_holder: string | null;
    bank_swift: string | null;
    notes: string | null;
    rejection_reason: string | null;
    manager_approved_by: string | null;
    manager_approved_at: string | null;
    finance_verified_by: string | null;
    paid_at: string | null;
    payment_method_label: string | null;
    payment_reference: string | null;
    rejected_by: string | null;
    rejected_at: string | null;
    fraud_level: 'low' | 'medium' | 'high' | null;
    fraud_checked_at: string | null;
    items: SettlementItemRow[];
    attachments: SettlementAttachmentRow[];
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
export interface SettlementFilters {
    search?: string;
    status?: SettlementStatus;
    per_page?: string;
}

/** Status counters shown above the list. */
export interface SettlementKpis {
    submitted: number;
    manager_approved: number;
    paid: number;
    paid_amount: number;
}

/** Props for the settlement list page (`index.tsx`). */
export interface SettlementIndexProps {
    settlements: {
        data: SettlementRow[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    filters: SettlementFilters;
    statusOptions: SelectOption[];
    kpis: SettlementKpis;
}

/** Props for the settlement create page (`create.tsx`). */
export interface SettlementCreateProps {
    employees: EmployeeOption[];
    categories: SelectOption[];
}

/** Props for the settlement edit page (`edit.tsx`). */
export interface SettlementEditProps {
    settlement: {
        id: number;
        number: string | null;
        employee_id: number;
        title: string;
        category: string | null;
        department: string | null;
        submission_date: string | null;
        notes: string | null;
        items: (SettlementLineInput & { detail: string | null })[];
        attachments: SettlementAttachmentRow[];
    };
    employees: EmployeeOption[];
    categories: SelectOption[];
}

/** Props for the settlement detail page (`show.tsx`). */
export interface SettlementShowProps {
    settlement: SettlementDetail;
    paymentMethods: SelectOption[];
}

/**
 * One editable expense line in the create/edit form. `detail` has no input on
 * web — it rides along so editing a mobile-filed settlement here does not wipe
 * the flight number / hotel line the employee entered on their phone.
 */
export interface SettlementLineInput {
    description: string;
    detail?: string;
    category: string;
    amount: string | number;
}

/** Flat form payload backing the create/edit form. */
export interface SettlementFormData {
    employee_id: string;
    title: string;
    category: string;
    department: string;
    submission_date: string;
    notes: string;
    items: SettlementLineInput[];
    attachments: File[];
    action: 'draft' | 'submit';
}

/**
 * A fresh blank expense line. Built per call — a shared object would make every
 * row added from it edit as one.
 */
export function emptySettlementLine(): SettlementLineInput {
    return { description: '', category: 'transportasi', amount: '' };
}

/** Empty defaults for the create form (one blank line to start). */
export const emptySettlementForm: SettlementFormData = {
    employee_id: '',
    title: '',
    category: '',
    department: '',
    submission_date: '',
    notes: '',
    items: [emptySettlementLine()],
    attachments: [],
    action: 'draft',
};
