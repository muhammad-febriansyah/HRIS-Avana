/**
 * Shared types for the AvanaHR Kompetensi (competency framework) module page.
 * These mirror the `CompetencyController@index` payload.
 */

export type { FlashProps } from '../employees/types';

/** A selectable `{ id, name }` employee option. */
export interface EmployeeOption {
    id: number;
    name: string;
}

/** A competency master row as serialized by `CompetencyController@index`. */
export interface CompetencyRow {
    id: number;
    name: string;
    category: string | null;
    description: string | null;
}

/** KPI counters shown at the top of the page. */
export interface CompetencyKpis {
    total_competencies: number;
    average_level: number;
}

/** Props for the competency index page (`index.tsx`). */
export interface KompetensiIndexProps {
    competencies: CompetencyRow[];
    employees: EmployeeOption[];
    /** Map of `"{employeeId}-{competencyId}"` → assessed level (1-5). */
    matrix: Record<string, number>;
    kpis: CompetencyKpis;
}

/** Flat form payload backing the add/edit competency modal. */
export interface CompetencyFormData {
    name: string;
    category: string;
    description: string;
}

/** Empty defaults for the competency form. */
export const emptyCompetencyForm: CompetencyFormData = {
    name: '',
    category: '',
    description: '',
};

/** Selectable proficiency levels 1-5 with Indonesian labels. */
export const LEVEL_OPTIONS: { value: number; label: string }[] = [
    { value: 1, label: '1 · Dasar' },
    { value: 2, label: '2 · Berkembang' },
    { value: 3, label: '3 · Cakap' },
    { value: 4, label: '4 · Mahir' },
    { value: 5, label: '5 · Ahli' },
];
