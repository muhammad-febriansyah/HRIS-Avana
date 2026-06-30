/**
 * Shared types for the AvanaHR timesheet module. These mirror the
 * `TimesheetController@index` payload.
 */

export type { FlashProps } from '../employees/types';

/** A timesheet entry row as serialized by `TimesheetController@index`. */
export interface TimesheetEntry {
    id: number;
    employee: string | null;
    employee_id: number;
    project: string | null;
    project_id: number;
    date: string | null;
    hours: number;
    task: string | null;
    notes: string | null;
}

/** A project row as serialized by `TimesheetController@index`. */
export interface ProjectRow {
    id: number;
    name: string;
    code: string | null;
    status: string;
    timesheets_count: number;
}

/** A selectable employee `{ id, name, employee_number }`. */
export interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string | null;
}

/** Active index filters echoed back by the controller. */
export interface TimesheetFilters {
    project_id: string | null;
    employee_id: string | null;
}

/** KPI counters shown at the top of the index page. */
export interface TimesheetKpis {
    week_hours: number;
    active_projects: number;
    total_hours: number;
    total_entries: number;
}

/** Props for the timesheet index page (`index.tsx`). */
export interface TimesheetIndexProps {
    entries: TimesheetEntry[];
    projects: ProjectRow[];
    employees: EmployeeOption[];
    filters: TimesheetFilters;
    kpis: TimesheetKpis;
}

/** Flat form payload backing the add-project modal. */
export interface ProjectFormData {
    name: string;
    code: string;
    status: string;
}

/** Empty defaults for the add-project form. */
export const emptyProjectForm: ProjectFormData = {
    name: '',
    code: '',
    status: 'active',
};

/** Flat form payload backing the add-entry modal. */
export interface EntryFormData {
    employee_id: string;
    project_id: string;
    date: string;
    hours: string;
    task: string;
    notes: string;
}

/** Empty defaults for the add-entry form. */
export const emptyEntryForm: EntryFormData = {
    employee_id: '',
    project_id: '',
    date: '',
    hours: '',
    task: '',
    notes: '',
};

/** Indonesian label for a project status enum value. */
export function projectStatusLabel(status: string): string {
    return status === 'active' ? 'Aktif' : 'Arsip';
}
