/**
 * Shared types for the AvanaHR timesheet module. These mirror the
 * `TimesheetController@index` payload.
 */

export type { FlashProps } from '../employees/types';

/** Approval state of a single logged entry. */
export type EntryStatus = 'pending' | 'approved' | 'rejected';

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
    status: EntryStatus;
    status_label: string;
    is_billable: boolean;
    bill_rate: number | null;
    cost_rate: number | null;
    bill_amount: number;
    cost_amount: number;
    source: string;
    approved_by: string | null;
    approved_at: string | null;
    rejection_reason: string | null;
}

/** One employee assigned to a project, with their rate overrides. */
export interface ProjectMemberRow {
    employee_id: number;
    employee: string | null;
    bill_rate: number | null;
    cost_rate: number | null;
}

/** A project row as serialized by `TimesheetController@index`. */
export interface ProjectRow {
    id: number;
    name: string;
    code: string | null;
    client_name: string | null;
    description: string | null;
    status: string;
    manager_id: number | null;
    manager: string | null;
    start_date: string | null;
    end_date: string | null;
    budget_amount: number | null;
    budget_hours: number | null;
    is_billable: boolean;
    default_bill_rate: number | null;
    default_cost_rate: number | null;
    timesheets_count: number;
    members: ProjectMemberRow[];
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
    status: EntryStatus | null;
    from: string;
    to: string;
}

/** KPI counters shown at the top of the index page. */
export interface TimesheetKpis {
    week_hours: number;
    active_projects: number;
    pending_entries: number;
    total_hours: number;
    total_entries: number;
    bill_amount: number;
    cost_amount: number;
    margin: number;
}

/** One project line in the profitability report. */
export interface ReportProjectRow {
    project_id: number;
    project: string;
    code: string | null;
    client_name: string | null;
    hours: number;
    billable_hours: number;
    bill_amount: number;
    cost_amount: number;
    margin: number;
    margin_pct: number | null;
    budget_amount: number | null;
    budget_used_pct: number | null;
    budget_hours: number | null;
    budget_hours_used_pct: number | null;
    entries: number;
}

/** One employee line in the profitability report. */
export interface ReportEmployeeRow {
    employee_id: number;
    employee: string;
    hours: number;
    bill_amount: number;
    cost_amount: number;
    entries: number;
}

/** The profitability report over the filtered window. */
export interface TimesheetReport {
    projects: ReportProjectRow[];
    employees: ReportEmployeeRow[];
    totals: {
        hours: number;
        bill_amount: number;
        cost_amount: number;
        margin: number;
        margin_pct: number;
    };
}

/** Which module actions this account holds. */
export interface TimesheetAbilities {
    create: boolean;
    update: boolean;
    archive: boolean;
    approve: boolean;
    export: boolean;
}

/** Props for the timesheet index page (`index.tsx`). */
export interface TimesheetIndexProps {
    entries: TimesheetEntry[];
    projects: ProjectRow[];
    employees: EmployeeOption[];
    filters: TimesheetFilters;
    kpis: TimesheetKpis;
    report: TimesheetReport;
    can: TimesheetAbilities;
}

/** The three panels the page is split into. */
export type TabKey = 'entries' | 'projects' | 'report';

/** One member row while the project form is open. */
export interface MemberFormRow {
    employee_id: string;
    bill_rate: string;
    cost_rate: string;
}

/** Flat form payload backing the project modal. */
export interface ProjectFormData {
    name: string;
    code: string;
    client_name: string;
    description: string;
    status: string;
    manager_id: string;
    start_date: string;
    end_date: string;
    budget_amount: string;
    budget_hours: string;
    is_billable: boolean;
    default_bill_rate: string;
    default_cost_rate: string;
    members: MemberFormRow[];
}

/** Empty defaults for the project form. */
export const emptyProjectForm: ProjectFormData = {
    name: '',
    code: '',
    client_name: '',
    description: '',
    status: 'active',
    manager_id: '',
    start_date: '',
    end_date: '',
    budget_amount: '',
    budget_hours: '',
    is_billable: true,
    default_bill_rate: '',
    default_cost_rate: '',
    members: [],
};

/** Fill the project form from an existing row, for the edit modal. */
export function projectToForm(project: ProjectRow): ProjectFormData {
    return {
        name: project.name,
        code: project.code ?? '',
        client_name: project.client_name ?? '',
        description: project.description ?? '',
        status: project.status,
        manager_id: project.manager_id ? String(project.manager_id) : '',
        start_date: project.start_date ?? '',
        end_date: project.end_date ?? '',
        budget_amount: project.budget_amount ? String(project.budget_amount) : '',
        budget_hours: project.budget_hours ? String(project.budget_hours) : '',
        is_billable: project.is_billable,
        default_bill_rate: project.default_bill_rate
            ? String(project.default_bill_rate)
            : '',
        default_cost_rate: project.default_cost_rate
            ? String(project.default_cost_rate)
            : '',
        members: project.members.map((member) => ({
            employee_id: String(member.employee_id),
            bill_rate: member.bill_rate ? String(member.bill_rate) : '',
            cost_rate: member.cost_rate ? String(member.cost_rate) : '',
        })),
    };
}

/** Flat form payload backing the entry modal. */
export interface EntryFormData {
    employee_id: string;
    project_id: string;
    date: string;
    hours: string;
    task: string;
    notes: string;
    is_billable: boolean;
}

/** Empty defaults for the entry form. */
export const emptyEntryForm: EntryFormData = {
    employee_id: '',
    project_id: '',
    date: '',
    hours: '',
    task: '',
    notes: '',
    is_billable: true,
};

/** Fill the entry form from an existing row, for the edit modal. */
export function entryToForm(entry: TimesheetEntry): EntryFormData {
    return {
        employee_id: String(entry.employee_id),
        project_id: String(entry.project_id),
        date: entry.date ?? '',
        hours: String(entry.hours),
        task: entry.task ?? '',
        notes: entry.notes ?? '',
        is_billable: entry.is_billable,
    };
}

/** Indonesian label for a project status enum value. */
export function projectStatusLabel(status: string): string {
    return status === 'active' ? 'Aktif' : 'Arsip';
}

/** Rupiah with no decimals, or an em dash when there is nothing to show. */
export function rupiah(value: number | null | undefined): string {
    if (value === null || value === undefined || value === 0) {
        return '—';
    }

    return 'Rp ' + Math.round(value).toLocaleString('id-ID');
}

/** "7,5 jam", trimming a trailing zero decimal. */
export function hoursLabel(value: number): string {
    return value.toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' jam';
}
