/**
 * Shared types for the AvanaHR OKR & Goal module pages.
 * These mirror the `OkrController` payloads (`index`, `create`, `edit`).
 */

export type { FlashProps } from '../employees/types';

/** A key result nested under an objective. */
export interface KeyResultRow {
    id: number;
    title: string;
    target_value: number;
    current_value: number;
    unit: string | null;
    progress: number;
}

/** An objective row as serialized by `OkrController@index`. */
export interface ObjectiveRow {
    id: number;
    title: string;
    description: string | null;
    level: string;
    status: string;
    progress: number;
    cycle_id: number | null;
    cycle: string | null;
    employee_id: number | null;
    employee: string | null;
    key_results: KeyResultRow[];
}

/** A `{ value, label }` option used by enum selects. */
export interface SelectOption {
    value: string;
    label: string;
}

/** A selectable cycle `{ id, name }`. */
export interface CycleOption {
    id: number;
    name: string;
}

/** A selectable employee `{ id, name, employee_number }`. */
export interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string | null;
}

/** KPI counters shown at the top of the index page. */
export interface OkrKpis {
    total_objectives: number;
    avg_progress: number;
    on_track: number;
}

/** Props for the OKR index page (`index.tsx`). */
export interface OkrIndexProps {
    objectives: ObjectiveRow[];
    cycles: CycleOption[];
    employees: EmployeeOption[];
    levels: SelectOption[];
    statuses: SelectOption[];
    kpis: OkrKpis;
}

/** Props shared by the create/edit objective pages. */
export interface OkrFormOptions {
    cycles: CycleOption[];
    employees: EmployeeOption[];
    levels: SelectOption[];
    statuses: SelectOption[];
}

/** Flat form payload backing both the create and edit objective forms. */
export interface ObjectiveFormData {
    title: string;
    description: string;
    level: string;
    status: string;
    cycle_id: string;
    employee_id: string;
}

/** Empty defaults for the create objective form. */
export const emptyObjectiveForm: ObjectiveFormData = {
    title: '',
    description: '',
    level: 'individual',
    status: 'active',
    cycle_id: '',
    employee_id: '',
};

/** Flat form payload backing the add/edit key result inline form. */
export interface KeyResultFormData {
    title: string;
    target_value: string;
    current_value: string;
    unit: string;
}

/** Empty defaults for the add key result form. */
export const emptyKeyResultForm: KeyResultFormData = {
    title: '',
    target_value: '100',
    current_value: '0',
    unit: '',
};

/** Selectable objective level enum options. */
export const LEVEL_OPTIONS: SelectOption[] = [
    { value: 'company', label: 'Perusahaan' },
    { value: 'team', label: 'Tim' },
    { value: 'individual', label: 'Individu' },
];

/** Selectable objective status enum options. */
export const STATUS_OPTIONS: SelectOption[] = [
    { value: 'draft', label: 'Draf' },
    { value: 'active', label: 'Aktif' },
    { value: 'done', label: 'Selesai' },
    { value: 'cancelled', label: 'Dibatalkan' },
];

/** Indonesian label for an objective level enum value. */
export function levelLabel(level: string): string {
    return LEVEL_OPTIONS.find((option) => option.value === level)?.label ?? level;
}

/** Indonesian label for an objective status enum value. */
export function statusLabel(status: string): string {
    return STATUS_OPTIONS.find((option) => option.value === status)?.label ?? status;
}
