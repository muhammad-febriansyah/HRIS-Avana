/**
 * Shared types for the AvanaHR duty travel (perjalanan dinas) module pages.
 * These mirror the `DutyTravelController` payloads (`index`, `create`).
 */

import type { PaginationMeta } from '../employees/types';

export type { FlashProps, PaginationMeta } from '../employees/types';

/** Employee summary embedded in each duty travel row. */
export interface RowEmployee {
    name: string;
    employee_number: string | null;
    initials: string;
    avatar_color: string;
}

export type TravelStatus = 'pending' | 'approved' | 'rejected' | 'completed';
export type TravelStatusLabel = 'Menunggu' | 'Disetujui' | 'Ditolak' | 'Selesai';

/** A single duty travel record as shaped by `DutyTravelController`. */
export interface TravelRow {
    id: number;
    employee: RowEmployee | null;
    destination: string;
    purpose: string | null;
    start_date: string | null;
    end_date: string | null;
    days: number | null;
    transport: string | null;
    estimated_cost: number | null;
    per_diem: number | null;
    status: TravelStatus;
    status_label: TravelStatusLabel;
}

/** A selectable employee `{ id, name, employee_number }`. */
export interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

/** Active list filters echoed back by `DutyTravelController@index`. */
export interface DinasFilters {
    search?: string;
    status?: TravelStatus;
    per_page?: string;
}

/** Props for the duty travel list page (`index.tsx`). */
export interface DinasIndexProps {
    travels: {
        data: TravelRow[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    filters: DinasFilters;
    employees: EmployeeOption[];
}

/** Props for the duty travel create page (`create.tsx`). */
export interface DinasCreateProps {
    employees: EmployeeOption[];
}

/** Flat form payload backing the "Ajukan Perjalanan Dinas" form. */
export interface TravelFormData {
    employee_id: string;
    destination: string;
    purpose: string;
    start_date: string;
    end_date: string;
    transport: string;
    estimated_cost: string;
    per_diem: string;
}

/** Empty defaults for the create form. */
export const emptyTravelForm: TravelFormData = {
    employee_id: '',
    destination: '',
    purpose: '',
    start_date: '',
    end_date: '',
    transport: '',
    estimated_cost: '',
    per_diem: '',
};

/** Selectable status filter options. */
export const statusOptions: { value: TravelStatus; label: string }[] = [
    { value: 'pending', label: 'Menunggu' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'rejected', label: 'Ditolak' },
    { value: 'completed', label: 'Selesai' },
];
