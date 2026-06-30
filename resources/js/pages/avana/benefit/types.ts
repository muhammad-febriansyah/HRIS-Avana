/**
 * Shared types for the AvanaHR benefit module pages. These mirror the
 * `BenefitController` payloads (`index`, `create`, `edit`).
 */

export type { FlashProps } from '../employees/types';

/** A benefit master row as serialized by `BenefitController@index`. */
export interface BenefitRow {
    id: number;
    code: string;
    name: string;
    type: string;
    value: number;
    description: string | null;
    status: string;
}

/** An employee benefit assignment row. */
export interface AssignmentRow {
    id: number;
    employee: { name: string | null; employee_number: string | null } | null;
    benefit: { name: string | null; type: string } | null;
    start_date: string | null;
    end_date: string | null;
    status: string;
}

/** A selectable employee `{ id, name, employee_number }`. */
export interface EmployeeOption {
    id: number;
    name: string | null;
    employee_number: string | null;
}

/** Props for the benefit list page (`index.tsx`). */
export interface BenefitIndexProps {
    benefits: BenefitRow[];
    assignments: AssignmentRow[];
    employees: EmployeeOption[];
}

/** Flat form payload backing both the create and edit benefit forms. */
export interface BenefitFormData {
    code: string;
    name: string;
    type: string;
    value: string;
    description: string;
    status: string;
}

/** Empty defaults for the create form. */
export const emptyBenefitForm: BenefitFormData = {
    code: '',
    name: '',
    type: 'allowance',
    value: '0',
    description: '',
    status: 'active',
};

/** Flat form payload backing the assign modal (relation form on the list page). */
export interface AssignFormData {
    employee_id: string;
    benefit_id: string;
    start_date: string;
    end_date: string;
    notes: string;
}

/** Empty defaults for the assign form. */
export const emptyAssignForm: AssignFormData = {
    employee_id: '',
    benefit_id: '',
    start_date: '',
    end_date: '',
    notes: '',
};

/** Selectable benefit type enum options. */
export const TYPE_OPTIONS: { value: string; label: string }[] = [
    { value: 'insurance', label: 'Asuransi' },
    { value: 'allowance', label: 'Tunjangan' },
    { value: 'facility', label: 'Fasilitas' },
];

/** Indonesian label for a benefit type enum value. */
export function typeLabel(type: string): string {
    return TYPE_OPTIONS.find((option) => option.value === type)?.label ?? type;
}
