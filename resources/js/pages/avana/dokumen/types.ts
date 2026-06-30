/**
 * Shared types for the AvanaHR employee documents (Dokumen Karyawan) module.
 * These mirror the `DokumenController@index` payload.
 */

export type { FlashProps } from '../employees/types';

/** A document row as serialized by `DokumenController@index`. */
export interface DocumentRow {
    id: number;
    employee_id: number;
    employee: string | null;
    name: string;
    type: string | null;
    file_size: number | null;
    file_size_label: string | null;
    uploaded_at: string | null;
    download_url: string | null;
}

/** A selectable employee `{ id, name }`. */
export interface EmployeeOption {
    id: number;
    name: string | null;
}

/** A `{ value, label }` option used by enum selects. */
export interface SelectOption {
    value: string;
    label: string;
}

/** KPI counters shown at the top of the index page. */
export interface DokumenKpis {
    total_documents: number;
    employees_with_documents: number;
    total_employees: number;
}

/** Props for the employee documents index page (`index.tsx`). */
export interface DokumenIndexProps {
    documents: DocumentRow[];
    employees: EmployeeOption[];
    kpis: DokumenKpis;
}

/** Flat form payload backing the upload-document modal. */
export interface DocumentFormData {
    employee_id: string;
    name: string;
    type: string;
    file: File | null;
}

/** Empty defaults for the upload-document form. */
export const emptyDocumentForm: DocumentFormData = {
    employee_id: '',
    name: '',
    type: '',
    file: null,
};

/** Selectable document type options. */
export const DOCUMENT_TYPE_OPTIONS: SelectOption[] = [
    { value: 'KTP', label: 'KTP' },
    { value: 'NPWP', label: 'NPWP' },
    { value: 'Ijazah', label: 'Ijazah' },
    { value: 'Kontrak', label: 'Kontrak' },
    { value: 'Sertifikat', label: 'Sertifikat' },
    { value: 'Lainnya', label: 'Lainnya' },
];
