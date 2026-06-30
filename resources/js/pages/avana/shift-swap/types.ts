/**
 * Shared types for the AvanaHR shift swap (tukar shift) module. These mirror
 * the `ShiftSwapController@index` payload.
 */

export type { FlashProps } from '../employees/types';

/** A shift swap request row as serialized by `ShiftSwapController@index`. */
export interface SwapRow {
    id: number;
    requester: string | null;
    requester_id: number;
    target: string | null;
    target_id: number;
    date: string | null;
    requester_shift: string | null;
    requester_shift_id: number | null;
    target_shift: string | null;
    target_shift_id: number | null;
    reason: string | null;
    status: string;
    status_label: string;
}

/** A selectable employee `{ id, name, employee_number }`. */
export interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string | null;
}

/** A selectable shift master row. */
export interface ShiftOption {
    id: number;
    code: string | null;
    name: string;
    start_time: string | null;
    end_time: string | null;
}

/** KPI counters shown at the top of the index page. */
export interface SwapKpis {
    pending: number;
    approved: number;
    rejected: number;
    total: number;
}

/** Props for the shift swap index page (`index.tsx`). */
export interface ShiftSwapIndexProps {
    swaps: SwapRow[];
    employees: EmployeeOption[];
    shifts: ShiftOption[];
    kpis: SwapKpis;
}

/** Flat form payload backing the create-swap modal. */
export interface SwapFormData {
    requester_id: string;
    target_id: string;
    date: string;
    requester_shift_id: string;
    target_shift_id: string;
    reason: string;
}

/** Empty defaults for the create-swap form. */
export const emptySwapForm: SwapFormData = {
    requester_id: '',
    target_id: '',
    date: '',
    requester_shift_id: '',
    target_shift_id: '',
    reason: '',
};
