/**
 * Shared types for the AvanaHR claim & reimbursement module pages. These
 * mirror the `ClaimController` payloads (`index`, `create`, `edit`).
 */

export type { FlashProps } from '../employees/types';

/** A selectable employee `{ id, name, employee_number }`. */
export interface EmployeeOption {
    id: number;
    name: string | null;
    employee_number: string | null;
}

/** A `{ value, label }` option used by enum selects. */
export interface SelectOption {
    value: string;
    label: string;
}

/** A claim row as serialized by `ClaimController@index`. */
export interface ClaimRow {
    id: number;
    employee: { name: string | null; employee_number: string | null } | null;
    employee_id: number;
    claim_type: string;
    title: string;
    amount: number;
    claim_date: string | null;
    description: string | null;
    receipt_url: string | null;
    status: string;
    notes: string | null;
    approver: string | null;
    approved_at: string | null;
}

/** KPI counters shown at the top of the index page. */
export interface ClaimKpis {
    pending: number;
    approved: number;
    paid: number;
    total_amount: number;
}

/** Props for the claim list page (`index.tsx`). */
export interface KlaimIndexProps {
    claims: ClaimRow[];
    employees: EmployeeOption[];
    claimTypes: SelectOption[];
    kpis: ClaimKpis;
}

/** The claim payload surfaced to the edit screen. */
export interface ClaimEditModel {
    id: number;
    employee_id: number;
    claim_type: string;
    title: string;
    amount: number;
    claim_date: string | null;
    description: string | null;
    receipt_url: string | null;
    status: string;
    notes: string | null;
}

/** Props for the claim create page (`create.tsx`). */
export interface KlaimCreateProps {
    employees: EmployeeOption[];
    claimTypes: SelectOption[];
}

/** Props for the claim edit page (`edit.tsx`). */
export interface KlaimEditProps {
    claim: ClaimEditModel;
    employees: EmployeeOption[];
    claimTypes: SelectOption[];
}

/** Flat form payload backing both the create and edit claim forms. */
export interface ClaimFormData {
    employee_id: string;
    claim_type: string;
    title: string;
    amount: string;
    claim_date: string;
    description: string;
    notes: string;
    receipt: File | null;
}

/** Empty defaults for the create form. */
export const emptyClaimForm: ClaimFormData = {
    employee_id: '',
    claim_type: 'medical',
    title: '',
    amount: '0',
    claim_date: '',
    description: '',
    notes: '',
    receipt: null,
};

/** Indonesian label for a claim type enum value. */
export function claimTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        medical: 'Kesehatan',
        transport: 'Transportasi',
        meal: 'Konsumsi',
        glasses: 'Kacamata',
        other: 'Lainnya',
    };

    return labels[type] ?? type;
}

/** Indonesian label for a claim status enum value. */
export function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        pending: 'Menunggu',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        paid: 'Dibayar',
    };

    return labels[status] ?? status;
}
