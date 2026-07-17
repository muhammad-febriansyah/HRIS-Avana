/**
 * Shared types for the AvanaHR visiting (field visit) module pages. These
 * mirror the `FieldVisitController` payloads (`index`, `create`).
 */

import type { PaginationMeta } from '../employees/types';

export type { FlashProps, PaginationMeta } from '../employees/types';

/** Employee option surfaced in the "Catat Kunjungan" form. */
export interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

/** Branch option surfaced in the "Catat Kunjungan" form. */
export interface BranchOption {
    id: number;
    name: string;
}

/** Eager-loaded employee summary attached to a visit row. */
export interface VisitEmployee {
    id: number;
    name: string;
    employee_number: string | null;
    initials: string;
    avatar_color: string;
}

/** One line of a visit's tasklist. */
export interface VisitTask {
    id: number;
    title: string;
    is_done: boolean;
}

/** How far through its tasklist a visit is. */
export interface VisitTaskProgress {
    done: number;
    total: number;
}

/** A single field visit row as serialized by `FieldVisitController@index`. */
export interface VisitRow {
    id: number;
    employees: VisitEmployee[];
    branch: string | null;
    visit_date: string | null;
    location: string;
    client_name: string | null;
    purpose: string | null;
    notes: string | null;
    photo_urls: string[];
    latitude: number | null;
    longitude: number | null;
    status: string;
    tasks: VisitTask[];
    task_progress: VisitTaskProgress;
}

/** Active filters echoed back from the query string. */
export interface VisitingFilters {
    search?: string;
    date?: string;
    branch_id?: string;
    per_page?: string;
}

/** Props for the visiting list page (`index.tsx`). */
export interface VisitingIndexProps {
    visits: {
        data: VisitRow[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    employees: EmployeeOption[];
    branches: BranchOption[];
    filters: VisitingFilters;
}

/** Props for the visiting create page (`create.tsx`). */
export interface VisitingCreateProps {
    employees: EmployeeOption[];
    branches: BranchOption[];
}

/** Form payload backing the "Catat Kunjungan" form (file via forceFormData). */
export interface VisitFormData {
    employee_ids: string[];
    branch_id: string;
    visit_date: string;
    location: string;
    client_name: string;
    purpose: string;
    latitude: string;
    longitude: string;
    notes: string;
    photos: File[];
    tasks: string[];
}

/** Empty defaults for the create form. */
export const emptyVisitForm: VisitFormData = {
    employee_ids: [],
    branch_id: '',
    visit_date: '',
    location: '',
    client_name: '',
    purpose: '',
    latitude: '',
    longitude: '',
    notes: '',
    photos: [],
    tasks: [],
};
