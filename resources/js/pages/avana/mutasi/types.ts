/**
 * Shared types for the AvanaHR mutasi (career movement) module pages. These
 * mirror the `MovementController` payloads (`index`, `create`, `store`).
 */

import type { FlashProps, PaginationMeta } from '../employees/types';

export type { FlashProps, PaginationMeta };

/** A single from→to change entry, as serialized by the controller. */
export interface MovementChange {
    label: string;
    from: string | null;
    to: string | null;
}

/** A single career movement row as serialized by `MovementController@index`. */
export interface MovementRow {
    id: number;
    employee: {
        name: string | null;
        employee_number: string | null;
    };
    movement_type: string;
    effective_date: string | null;
    changes: MovementChange[];
    employment_status: string | null;
    notes: string | null;
    created_at: string | null;
}

/** A selectable employee with its current placement, for prefilling the form. */
export interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
    position_id: number | null;
    department_id: number | null;
    branch_id: number | null;
}

/** `{ id, name }` option used by the relation selects. */
export interface NamedOption {
    id: number;
    name: string;
}

/** Filters carried by the mutasi list query string. */
export interface MutasiFilters {
    search?: string;
    movement_type?: string;
    per_page?: string;
}

/** Option lists shared by the create form. */
export interface MutasiFormOptions {
    employees: EmployeeOption[];
    positions: NamedOption[];
    departments: NamedOption[];
    branches: NamedOption[];
}

/** Props for the mutasi list page (`index.tsx`). */
export interface MutasiIndexProps extends MutasiFormOptions {
    movements: {
        data: MovementRow[];
        meta: PaginationMeta;
    };
    filters: MutasiFilters;
}

/** Props for the create movement page (`create.tsx`). */
export type MutasiCreateProps = MutasiFormOptions;

/** Flat form payload backing the create movement form. */
export interface MovementFormData {
    employee_id: string;
    movement_type: string;
    effective_date: string;
    position_id: string;
    department_id: string;
    branch_id: string;
    employment_status: string;
    notes: string;
}

/** Empty defaults for the create form. */
export const emptyMovementForm: MovementFormData = {
    employee_id: '',
    movement_type: 'mutation',
    effective_date: '',
    position_id: '',
    department_id: '',
    branch_id: '',
    employment_status: '',
    notes: '',
};

/** Selectable movement type enum options. */
export const movementOptions: { value: string; label: string }[] = [
    { value: 'mutation', label: 'Mutasi' },
    { value: 'promotion', label: 'Promosi' },
    { value: 'demotion', label: 'Demosi' },
    { value: 'transfer', label: 'Transfer' },
    { value: 'resign', label: 'Resign' },
    { value: 'terminate', label: 'Terminasi' },
];

/** Selectable employment status enum options. */
export const employmentStatusOptions: { value: string; label: string }[] = [
    { value: 'probation', label: 'Masa Percobaan' },
    { value: 'contract', label: 'Kontrak' },
    { value: 'permanent', label: 'Tetap' },
    { value: 'resigned', label: 'Resign' },
];
