/**
 * Shared types for the AvanaHR Talenta & Suksesi (9-box) module page. These
 * mirror the `TalentController@index` payload.
 */

export type { FlashProps } from '../employees/types';

/** A `{ value, label }` option used by enum selects. */
export interface SelectOption {
    value: string;
    label: string;
}

/** A selectable `{ id, name }` employee option. */
export interface EmployeeOption {
    id: number;
    name: string;
}

/** A talent assessment chip as serialized by `TalentController@index`. */
export interface AssessmentChip {
    id: number;
    employee_id: number;
    employee: string | null;
    employee_number: string | null;
    performance_level: string;
    potential_level: string;
    note: string | null;
    successor_for: string | null;
}

/** KPI counters shown at the top of the page. */
export interface TalentKpis {
    assessed: number;
    stars: number;
    risks: number;
}

/** Props for the talent index page (`index.tsx`). */
export interface TalentIndexProps {
    assessments: AssessmentChip[];
    successors: AssessmentChip[];
    employees: EmployeeOption[];
    levels: SelectOption[];
    kpis: TalentKpis;
}

/** Flat form payload backing the add/edit assessment form. */
export interface AssessmentFormData {
    employee_id: string;
    performance_level: string;
    potential_level: string;
    successor_for: string;
    note: string;
}

/** Empty defaults for the assessment form. */
export const emptyAssessmentForm: AssessmentFormData = {
    employee_id: '',
    performance_level: 'medium',
    potential_level: 'medium',
    successor_for: '',
    note: '',
};

/** Rank order used to position a level along a 9-box axis (low → high). */
export const LEVEL_RANK: Record<string, number> = {
    low: 0,
    medium: 1,
    high: 2,
};

/** Indonesian label for a rating level value. */
export function levelLabel(value: string): string {
    return { low: 'Rendah', medium: 'Sedang', high: 'Tinggi' }[value] ?? value;
}
