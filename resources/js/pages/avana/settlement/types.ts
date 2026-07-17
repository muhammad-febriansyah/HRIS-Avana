/**
 * Shared types for the AvanaHR settlement module pages. These mirror the
 * `SettlementController` payloads (`index`, `create`, `show`).
 */

import type { PaginationMeta } from '../employees/types';

export type { FlashProps, PaginationMeta } from '../employees/types';

/** Where a settlement sits in the draft → verifikasi → penyelesaian flow. */
export type SettlementStatus =
    'draft' | 'submitted' | 'approved' | 'rejected' | 'closed';

/**
 * Which way the money has to move once the receipts are in: the employee hands
 * leftover float back (`return`), the company pays what they overspent
 * (`topup`), or the spend matched the advance exactly (`balanced`).
 */
export type SettlementOutcome = 'return' | 'topup' | 'balanced';

/** The employee an advance was issued to. */
export interface SettlementEmployee {
    name: string;
    employee_number: string | null;
    initials: string;
    avatar_color: string;
}

/** One receipt line backing a settlement. */
export interface SettlementItemRow {
    id: number;
    category: string;
    category_label: string;
    description: string;
    spent_date: string | null;
    amount: number;
    receipt_url: string | null;
}

/** A settlement as shaped by `SettlementController@index`. */
export interface SettlementRow {
    id: number;
    number: string | null;
    employee: SettlementEmployee | null;
    cash_advance_id: number;
    purpose: string | null;
    settlement_date: string | null;
    advance_amount: number;
    total_spent: number;
    balance: number;
    outcome: SettlementOutcome;
    outcome_label: string;
    outstanding: number;
    status: SettlementStatus;
    status_label: string;
    returned_amount: number;
    returned_at: string | null;
    topup_amount: number;
    topup_paid_at: string | null;
    rejection_reason: string | null;
    notes: string | null;
}

/** A settlement with its receipt lines, as shaped by `@show`. */
export interface SettlementDetail extends SettlementRow {
    approver: string | null;
    approved_at: string | null;
    returned_received_by: string | null;
    topup_paid_by: string | null;
    topup_reference: string | null;
    items: SettlementItemRow[];
}

/** A disbursed advance still waiting to be accounted for. */
export interface SettleableAdvance {
    id: number;
    employee_name: string | null;
    employee_number: string | null;
    amount: number;
    purpose: string | null;
    disbursed_at: string | null;
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
    draft: number;
    submitted: number;
    closed: number;
    unsettled_advances: number;
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
    advances: SettleableAdvance[];
}

/** Props for the settlement detail page (`show.tsx`). */
export interface SettlementShowProps {
    settlement: SettlementDetail;
    categories: SelectOption[];
    paymentMethods: SelectOption[];
}

/** Flat form payload backing the "Buka Settlement" form. */
export interface SettlementFormData {
    cash_advance_id: string;
    settlement_date: string;
    notes: string;
}

/** Empty defaults for the create form. */
export const emptySettlementForm: SettlementFormData = {
    cash_advance_id: '',
    settlement_date: '',
    notes: '',
};

/** Flat form payload backing the "Tambah Bukti Pengeluaran" form. */
export interface SettlementItemFormData {
    category: string;
    description: string;
    spent_date: string;
    amount: string;
    receipt: File | null;
}
