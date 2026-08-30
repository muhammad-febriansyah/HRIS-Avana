/**
 * Shared types for the AvanaHR approval center page. These mirror the
 * `ApprovalController@index` payload (pending, history, counts).
 */

export type { FlashProps } from '../employees/types';

export type ApprovalType =
    | 'leave'
    | 'lembur'
    | 'izin'
    | 'wfh'
    | 'koreksi'
    | 'klaim'
    | 'dinas'
    | 'data'
    | 'timesheet';
export type ApprovalStatus = 'pending' | 'approved' | 'rejected' | 'paid';
export type ApprovalStatusLabel =
    'Menunggu' | 'Disetujui' | 'Ditolak' | 'Dibayar';

/** The employee summary shared by every approval row. */
export interface ApprovalEmployee {
    name: string;
    employee_number: string | null;
    initials: string;
    avatar_color: string;
}

/** A single aggregated approval row as serialized by `ApprovalController`. */
export interface ApprovalItem {
    type: ApprovalType;
    id: number;
    employee: ApprovalEmployee | null;
    title: string;
    detail: string;
    reason: string | null;
    requested_at: string | null;
    requested_ago: string | null;
    status: ApprovalStatus;
    status_label: ApprovalStatusLabel;
}

export interface ApprovalCounts {
    leave: number;
    lembur: number;
    izin: number;
    wfh: number;
    koreksi: number;
    klaim: number;
    dinas: number;
    data: number;
    timesheet: number;
    total: number;
}

/** Paging state for one table, computed server-side over the merged rows. */
export interface PageMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export interface ApprovalProps {
    pending: ApprovalItem[];
    pendingMeta: PageMeta;
    history: ApprovalItem[];
    historyMeta: PageMeta;
    counts: ApprovalCounts;
    filters: {
        jenis: FilterKey;
        per_page: number;
    };
    historyDays: number;
}

export type FilterKey = 'all' | ApprovalType;

/** Per-type presentation metadata: label, icon and accent colour. */
export const typeMeta: Record<
    ApprovalType,
    { label: string; icon: string; color: string }
> = {
    leave: { label: 'Cuti', icon: 'palmtree', color: '#2F54C9' },
    lembur: { label: 'Lembur', icon: 'clock', color: '#D97706' },
    izin: { label: 'Izin', icon: 'door-open', color: '#6E9BE6' },
    wfh: { label: 'WFH', icon: 'house', color: '#16A34A' },
    koreksi: { label: 'Koreksi', icon: 'pencil', color: '#8b5cf6' },
    klaim: { label: 'Klaim', icon: 'receipt', color: '#DB2777' },
    dinas: { label: 'Dinas', icon: 'plane', color: '#16A34A' },
    data: { label: 'Data', icon: 'user-round-cog', color: '#DC2626' },
    timesheet: { label: 'Timesheet', icon: 'clock', color: '#0891B2' },
};
