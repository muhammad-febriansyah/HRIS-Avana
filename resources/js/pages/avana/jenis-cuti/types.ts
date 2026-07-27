/**
 * Shared types for the AvanaHR "Jenis Cuti" (leave type) module pages. These
 * mirror the `LeaveTypeController` payloads (`index`, `create`, `edit`).
 */

export type { FlashProps } from '../employees/types';

/** How a sub-type resolves a toggle it may inherit from its parent. */
export type InheritableToggle = 'inherit' | 'yes' | 'no';

/** A sub-type row as serialized by `LeaveTypeController`. */
export interface LeaveSubTypeRow {
    id: number;
    code: string;
    name: string;
    /** Max days this sub-type may take from the parent quota; null = no cap. */
    sub_limit: number | null;
    /** null means "inherit the parent's setting". */
    allow_negative: boolean | null;
    requires_attachment: boolean | null;
    status: string;
    usage: number;
}

/** A leave type master row as serialized by `LeaveTypeController@index`. */
export interface LeaveTypeRow {
    id: number;
    code: string;
    name: string;
    default_quota: number;
    sub_limit: number | null;
    allow_negative: boolean | null;
    requires_attachment: boolean | null;
    status: string;
    usage: number;
    children: LeaveSubTypeRow[];
}

/** Props for the leave type list page (`index.tsx`). */
export interface JenisCutiIndexProps {
    leaveTypes: LeaveTypeRow[];
}

/** One editable sub-type row inside the branch repeater. */
export interface LeaveSubTypeFormData {
    /** Present when editing an existing sub-type, null for a new one. */
    id: number | null;
    code: string;
    name: string;
    /** Empty string = no cap, so the field can be cleared. */
    sub_limit: string;
    allow_negative: InheritableToggle;
    requires_attachment: InheritableToggle;
    status: string;
}

/** Form payload backing both the create and edit leave type forms. */
export interface LeaveTypeFormData {
    code: string;
    name: string;
    default_quota: string;
    allow_negative: boolean;
    requires_attachment: boolean;
    status: string;
    children: LeaveSubTypeFormData[];
}

/** Empty defaults for the create form. */
export const emptyLeaveTypeForm: LeaveTypeFormData = {
    code: '',
    name: '',
    default_quota: '12',
    allow_negative: false,
    requires_attachment: false,
    status: 'active',
    children: [],
};

/** A blank sub-type row, added when the admin clicks "Tambah Sub-Jenis". */
export const emptySubType: LeaveSubTypeFormData = {
    id: null,
    code: '',
    name: '',
    sub_limit: '',
    allow_negative: 'inherit',
    requires_attachment: 'inherit',
    status: 'active',
};

/** Selectable status enum options. */
export const STATUS_OPTIONS: { value: string; label: string }[] = [
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];

/** Options for a sub-type toggle that may defer to its parent. */
export const INHERIT_OPTIONS: { value: InheritableToggle; label: string }[] = [
    { value: 'inherit', label: 'Ikut induk' },
    { value: 'yes', label: 'Ya' },
    { value: 'no', label: 'Tidak' },
];

/** Turn a stored nullable boolean into the form's tri-state selector value. */
export function toInheritable(value: boolean | null): InheritableToggle {
    if (value === null) {
        return 'inherit';
    }

    return value ? 'yes' : 'no';
}

/** Map an API sub-type row onto its editable form shape. */
export function subTypeToForm(row: LeaveSubTypeRow): LeaveSubTypeFormData {
    return {
        id: row.id,
        code: row.code,
        name: row.name,
        sub_limit: row.sub_limit === null ? '' : String(row.sub_limit),
        allow_negative: toInheritable(row.allow_negative),
        requires_attachment: toInheritable(row.requires_attachment),
        status: row.status,
    };
}
