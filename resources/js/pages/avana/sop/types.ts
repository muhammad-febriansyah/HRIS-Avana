/**
 * Shared types for the AvanaHR SOP module. These mirror the
 * `SopController@index` payload.
 */

export type { FlashProps } from '../employees/types';

/** Visibility of an SOP: who the AI assistant may quote it to. */
export type SopVisibility = 'private' | 'public';

/** An SOP document row as serialized by `SopController@index`. */
export interface SopRow {
    id: number;
    sop_category_id: number | null;
    category: string | null;
    code: string | null;
    title: string;
    summary: string | null;
    content: string | null;
    visibility: SopVisibility;
    status: string;
    version: string | null;
    effective_date: string | null;
    file_name: string | null;
    file_size_label: string | null;
    has_content: boolean;
    uploaded_by: string | null;
    updated_at: string | null;
}

/** A "Jenis SOP" (category) row. */
export interface SopCategoryRow {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    status: string;
    sop_count: number;
}

/** KPI counters shown at the top of the index page. */
export interface SopKpis {
    total: number;
    public: number;
    private: number;
    categories: number;
    indexed: number;
}

/** Props for the SOP index page (`index.tsx`). */
export interface SopIndexProps {
    sops: SopRow[];
    categories: SopCategoryRow[];
    kpis: SopKpis;
}

/** Flat form payload backing the SOP create/edit modal. */
export interface SopFormData {
    sop_category_id: string;
    code: string;
    title: string;
    summary: string;
    content: string;
    visibility: SopVisibility;
    status: string;
    version: string;
    effective_date: string;
    file: File | null;
}

/** Flat form payload backing the "Jenis SOP" modal. */
export interface SopCategoryFormData {
    name: string;
    code: string;
    description: string;
    status: string;
}

/** Empty defaults for the SOP form. */
export const emptySopForm: SopFormData = {
    sop_category_id: '',
    code: '',
    title: '',
    summary: '',
    content: '',
    visibility: 'private',
    status: 'active',
    version: '',
    effective_date: '',
    file: null,
};

/** Empty defaults for the "Jenis SOP" form. */
export const emptySopCategoryForm: SopCategoryFormData = {
    name: '',
    code: '',
    description: '',
    status: 'active',
};

/** A `{ value, label }` option used by enum selects. */
export interface SelectOption {
    value: string;
    label: string;
}

/** Selectable visibility options, with the AI-access rule spelled out. */
export const VISIBILITY_OPTIONS: SelectOption[] = [
    {
        value: 'public',
        label: 'Public — semua karyawan bisa menanyakannya ke AI Assistant',
    },
    {
        value: 'private',
        label: 'Private — hanya pengguna dengan hak akses SOP',
    },
];

/** Selectable status options shared by both forms. */
export const STATUS_OPTIONS: SelectOption[] = [
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];
