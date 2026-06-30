/**
 * Shared types for the AvanaHR asset management module pages. These mirror the
 * `AssetController` payloads (`index`, `create`, `edit`).
 */

export type { FlashProps } from '../employees/types';

/** A `{ value, label }` option used by enum selects. */
export interface SelectOption {
    value: string;
    label: string;
}

/** The active assignment embedded in an asset row. */
export interface CurrentAssignment {
    id: number;
    assigned_date: string | null;
    employee_name: string | null;
    employee_number: string | null;
}

/** An asset register row as serialized by `AssetController@index`. */
export interface AssetRow {
    id: number;
    code: string;
    name: string;
    category: string;
    purchase_date: string | null;
    purchase_cost: number;
    depreciation_years: number;
    condition: string;
    status: string;
    notes: string | null;
    book_value: number;
    current_assignment: CurrentAssignment | null;
}

/** An active assignment row shown in the assignments section. */
export interface AssignmentRow {
    id: number;
    asset: { code: string; name: string } | null;
    employee: { name: string | null; employee_number: string | null } | null;
    assigned_date: string | null;
    condition_note: string | null;
}

/** A selectable employee `{ id, name, employee_number }`. */
export interface EmployeeOption {
    id: number;
    name: string | null;
    employee_number: string | null;
}

/** KPI counters shown at the top of the index page. */
export interface AssetKpis {
    total: number;
    available: number;
    assigned: number;
    maintenance: number;
    total_value: number;
}

/** Props for the asset index page (`index.tsx`). */
export interface AsetIndexProps {
    assets: AssetRow[];
    assignments: AssignmentRow[];
    employees: EmployeeOption[];
    categories: string[];
    conditions: SelectOption[];
    statuses: SelectOption[];
    kpis: AssetKpis;
}

/** Option lists shared by the create and edit forms. */
export interface AssetFormOptions {
    categories: string[];
    conditions: SelectOption[];
    statuses: SelectOption[];
}

/** The asset record serialized by `AssetController@edit`. */
export interface AssetRecord {
    id: number;
    code: string;
    name: string;
    category: string;
    purchase_date: string | null;
    purchase_cost: number;
    depreciation_years: number;
    condition: string;
    status: string;
    notes: string | null;
    book_value: number;
}

/** Flat form payload backing both the create and edit asset forms. */
export interface AssetFormData {
    code: string;
    name: string;
    category: string;
    purchase_date: string;
    purchase_cost: string;
    depreciation_years: string;
    condition: string;
    status: string;
    notes: string;
}

/** Empty defaults for the create asset form. */
export const emptyAssetForm: AssetFormData = {
    code: '',
    name: '',
    category: 'Elektronik',
    purchase_date: '',
    purchase_cost: '0',
    depreciation_years: '4',
    condition: 'good',
    status: 'available',
    notes: '',
};

/** Flat form payload backing the assign modal on the index page. */
export interface AssignFormData {
    employee_id: string;
    assigned_date: string;
    condition_note: string;
}

/** Empty defaults for the assign form. */
export const emptyAssignForm: AssignFormData = {
    employee_id: '',
    assigned_date: '',
    condition_note: '',
};

/** Fallback Indonesian labels for asset condition enum values. */
const CONDITION_LABELS: Record<string, string> = {
    good: 'Baik',
    fair: 'Cukup',
    damaged: 'Rusak',
};

/** Fallback Indonesian labels for asset status enum values. */
const STATUS_LABELS: Record<string, string> = {
    available: 'Tersedia',
    assigned: 'Dipakai',
    maintenance: 'Perbaikan',
    retired: 'Pensiun',
};

/** Indonesian label for an asset condition enum value. */
export function conditionLabel(condition: string): string {
    return CONDITION_LABELS[condition] ?? condition;
}

/** Indonesian label for an asset status enum value. */
export function statusLabel(status: string): string {
    return STATUS_LABELS[status] ?? status;
}
