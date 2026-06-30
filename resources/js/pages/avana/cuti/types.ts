/**
 * Shared types for the AvanaHR cuti (leave) module page. These mirror the
 * `App\Http\Controllers\Avana\LeaveController@index` payload along with the
 * related Overtime / PermissionRequest / Wfh request row shapes.
 */

import type { PaginationMeta } from '../employees/types';

export type { FlashProps, PaginationMeta } from '../employees/types';

/** A single leave request as serialized by `LeaveRequestResource`. */
export interface LeaveRequest {
    id: number;
    employee: {
        id: number;
        name: string;
        employee_number: string;
        initials: string;
        avatar_color: string;
    } | null;
    leave_type: { id: number; name: string } | null;
    start_date: string;
    end_date: string;
    start_date_raw: string | null;
    end_date_raw: string | null;
    total_days: number;
    durasi: string;
    reason: string | null;
    status: 'pending' | 'approved' | 'rejected';
    status_label: 'Menunggu' | 'Disetujui' | 'Ditolak';
}

export interface LeaveTypeOption {
    id: number;
    name: string;
    default_quota: number;
}

export interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

export interface LeaveBalance {
    id: number;
    jenis: string;
    total: number;
    sisa: number;
    pct: string;
}

export interface CutiFilters {
    search?: string;
    status?: 'pending' | 'approved' | 'rejected';
    leave_type_id?: string;
    sort?: string;
    direction?: string;
    per_page?: string;
}

/** Shared shape of a request row's employee summary across the submodules. */
export interface RequestEmployee {
    name: string;
    employee_number: string | null;
    initials: string;
    avatar_color: string;
}

export type RequestStatus = 'pending' | 'approved' | 'rejected';
export type RequestStatusLabel = 'Menunggu' | 'Disetujui' | 'Ditolak';

export interface OvertimeRow {
    id: number;
    employee: RequestEmployee | null;
    date: string | null;
    hours: number;
    reason: string | null;
    status: RequestStatus;
    status_label: RequestStatusLabel;
}

export interface PermissionRow {
    id: number;
    employee: RequestEmployee | null;
    date: string | null;
    type: string;
    start_time: string | null;
    end_time: string | null;
    reason: string | null;
    status: RequestStatus;
    status_label: RequestStatusLabel;
}

export interface WfhRow {
    id: number;
    employee: RequestEmployee | null;
    start_date: string | null;
    end_date: string | null;
    reason: string | null;
    status: RequestStatus;
    status_label: RequestStatusLabel;
}

export type TabKey = 'cuti' | 'lembur' | 'izin' | 'wfh';

export interface CutiProps {
    requests: {
        data: LeaveRequest[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    filters: CutiFilters;
    leaveTypes: LeaveTypeOption[];
    employees: EmployeeOption[];
    balances: LeaveBalance[];
    overtimeRequests: OvertimeRow[];
    permissionRequests: PermissionRow[];
    wfhRequests: WfhRow[];
}

/** Form payload backing the "Ajukan Cuti" form. */
export interface LeaveFormData {
    employee_id: string;
    leave_type_id: string;
    start_date: string;
    end_date: string;
    reason: string;
}

/** Form payload backing the "Ajukan Lembur" form. */
export interface OvertimeFormData {
    employee_id: string;
    date: string;
    hours: string;
    reason: string;
}

/** Form payload backing the "Ajukan Izin" form. */
export interface IzinFormData {
    employee_id: string;
    date: string;
    type: string;
    start_time: string;
    end_time: string;
    reason: string;
}

/** Form payload backing the "Ajukan WFH" form. */
export interface WfhFormData {
    employee_id: string;
    start_date: string;
    end_date: string;
    reason: string;
}

/** Inclusive working-day count between two ISO dates (0 when incomplete). */
export function leaveDayCount(start: string, end: string): number {
    if (!start || !end) {
        return 0;
    }

    const startMs = Date.parse(start);
    const endMs = Date.parse(end);

    if (Number.isNaN(startMs) || Number.isNaN(endMs) || endMs < startMs) {
        return 0;
    }

    return Math.floor((endMs - startMs) / 86_400_000) + 1;
}

/** Color cycle for saldo cards (balances carry no color). */
export const balanceColors = ['#2F54C9', '#16A34A', '#D97706'];

/** Icon cycle for saldo cards (balances carry no icon). */
export const balanceIcons = ['palmtree', 'thermometer', 'circle-alert'];

/** Tab definitions for the Cuti / Lembur / Izin / WFH switcher. */
export const tabs: { key: TabKey; label: string; icon: string }[] = [
    { key: 'cuti', label: 'Cuti', icon: 'palmtree' },
    { key: 'lembur', label: 'Lembur', icon: 'clock' },
    { key: 'izin', label: 'Izin', icon: 'door-open' },
    { key: 'wfh', label: 'WFH', icon: 'house' },
];
