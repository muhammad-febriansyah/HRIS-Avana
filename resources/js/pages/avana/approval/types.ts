/**
 * Shared types for the AvanaHR approval center page. These mirror the
 * `ApprovalController@index` payload (pending, history, counts).
 */

export type { FlashProps } from '../employees/types';

export type ApprovalType = 'leave' | 'lembur' | 'izin' | 'wfh' | 'koreksi';
export type ApprovalStatus = 'pending' | 'approved' | 'rejected';
export type ApprovalStatusLabel = 'Menunggu' | 'Disetujui' | 'Ditolak';

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
    status: ApprovalStatus;
    status_label: ApprovalStatusLabel;
}

export interface ApprovalCounts {
    leave: number;
    lembur: number;
    izin: number;
    wfh: number;
    koreksi: number;
    total: number;
}

export interface ApprovalProps {
    pending: ApprovalItem[];
    history: ApprovalItem[];
    counts: ApprovalCounts;
}

export type FilterKey = 'all' | ApprovalType;

/** Per-type presentation metadata: label, icon and accent colour. */
export const typeMeta: Record<ApprovalType, { label: string; icon: string; color: string }> = {
    leave: { label: 'Cuti', icon: 'palmtree', color: '#2F54C9' },
    lembur: { label: 'Lembur', icon: 'clock', color: '#D97706' },
    izin: { label: 'Izin', icon: 'door-open', color: '#6E9BE6' },
    wfh: { label: 'WFH', icon: 'house', color: '#16A34A' },
    koreksi: { label: 'Koreksi', icon: 'pencil', color: '#8b5cf6' },
};

/** The filter chips shown above the pending table. */
export const filters: { key: FilterKey; label: string }[] = [
    { key: 'all', label: 'Semua' },
    { key: 'leave', label: 'Cuti' },
    { key: 'lembur', label: 'Lembur' },
    { key: 'izin', label: 'Izin' },
    { key: 'wfh', label: 'WFH' },
    { key: 'koreksi', label: 'Koreksi' },
];
