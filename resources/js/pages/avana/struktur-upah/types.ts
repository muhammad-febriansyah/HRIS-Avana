/**
 * Shared types for the AvanaHR salary structure (struktur & skala upah)
 * module. These mirror the `SalaryStructureController@index` payload.
 */

export type { FlashProps } from '../employees/types';

/** A salary grade row as serialized by `SalaryStructureController@index`. */
export interface GradeRow {
    id: number;
    grade_code: string;
    grade_name: string;
    level: number;
    min_salary: number;
    mid_salary: number;
    max_salary: number;
}

/** KPI counters shown at the top of the index page. */
export interface SalaryKpis {
    total_grades: number;
    lowest_salary: number;
    highest_salary: number;
}

/** Props for the salary structure index page (`index.tsx`). */
export interface SalaryStructureIndexProps {
    grades: GradeRow[];
    kpis: SalaryKpis;
}

/** Flat form payload backing both the create and edit grade forms. */
export interface GradeFormData {
    grade_code: string;
    grade_name: string;
    level: string;
    min_salary: string;
    mid_salary: string;
    max_salary: string;
}

/** Empty defaults for the create grade form. */
export const emptyGradeForm: GradeFormData = {
    grade_code: '',
    grade_name: '',
    level: '1',
    min_salary: '',
    mid_salary: '',
    max_salary: '',
};
