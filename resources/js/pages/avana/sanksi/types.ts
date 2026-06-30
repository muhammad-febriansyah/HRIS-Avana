/**
 * Shared types for the AvanaHR sanksi (attendance penalty) module pages.
 * These mirror the `AttendancePenaltyController` payloads (`index`, `create`).
 */

import type { PaginationMeta } from '../employees/types';

export type { FlashProps, PaginationMeta } from '../employees/types';

/** Compact employee option backing the manual penalty form. */
export interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

/** Employee summary embedded in a penalty row. */
export interface PenaltyEmployee {
    name: string;
    employee_number: string;
    initials: string;
    avatar_color: string;
}

/** A single attendance penalty row as serialized by the controller. */
export interface PenaltyRow {
    id: number;
    employee: PenaltyEmployee | null;
    date: string;
    date_raw: string;
    violation_type: string;
    penalty_type: string;
    amount: number;
    notes: string | null;
    status: string;
}

/** Active filter state echoed back from the controller. */
export interface SanksiFilters {
    search?: string;
    violation_type?: string;
    per_page?: string;
}

/** Props for the sanksi list page (`index.tsx`). */
export interface SanksiIndexProps {
    penalties: {
        data: PenaltyRow[];
        meta: PaginationMeta;
    };
    employees: EmployeeOption[];
    filters: SanksiFilters;
}

/** Props for the sanksi create page (`create.tsx`). */
export interface SanksiCreateProps {
    employees: EmployeeOption[];
}

/** Flat payload backing the manual "Tambah Sanksi" form. */
export interface PenaltyFormData {
    employee_id: string;
    date: string;
    violation_type: string;
    penalty_type: string;
    amount: string;
    notes: string;
}

/** Empty defaults for the create form. */
export const emptyPenaltyForm: PenaltyFormData = {
    employee_id: '',
    date: '',
    violation_type: 'late',
    penalty_type: 'warning',
    amount: '',
    notes: '',
};

/** Indonesian labels for the violation enum. */
export const VIOLATION_LABELS: Record<string, string> = {
    late: 'Terlambat',
    absent: 'Alpa',
    incomplete: 'Belum Lengkap',
    early_leave: 'Pulang Cepat',
};

/** Selectable violation type enum options. */
export const violationOptions = Object.entries(VIOLATION_LABELS).map(
    ([value, label]) => ({ value, label }),
);
