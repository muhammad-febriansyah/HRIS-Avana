/**
 * Shared types for the AvanaHR "Jenis Cuti" (leave type) module pages. These
 * mirror the `LeaveTypeController` payloads (`index`, `create`, `edit`).
 */

export type { FlashProps } from '../employees/types';

/** A leave type master row as serialized by `LeaveTypeController@index`. */
export interface LeaveTypeRow {
    id: number;
    code: string;
    name: string;
    default_quota: number;
    allow_negative: boolean;
    requires_attachment: boolean;
    status: string;
    usage: number;
}

/** Props for the leave type list page (`index.tsx`). */
export interface JenisCutiIndexProps {
    leaveTypes: LeaveTypeRow[];
}

/** Flat form payload backing both the create and edit leave type forms. */
export interface LeaveTypeFormData {
    code: string;
    name: string;
    default_quota: string;
    allow_negative: boolean;
    requires_attachment: boolean;
    status: string;
}

/** Empty defaults for the create form. */
export const emptyLeaveTypeForm: LeaveTypeFormData = {
    code: '',
    name: '',
    default_quota: '12',
    allow_negative: false,
    requires_attachment: false,
    status: 'active',
};

/** Selectable status enum options. */
export const STATUS_OPTIONS: { value: string; label: string }[] = [
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];
