/**
 * Shared types for the AvanaHR payroll module page. These mirror the
 * `PayrollController@index` payload (periods, summary, sample slip, filters).
 */

export type { FlashProps, PaginationMeta } from '../employees/types';

/** A single payroll period row as serialized by `PayrollPeriodResource`. */
export interface Period {
    id: number;
    periode: string;
    cycle: string;
    cycle_label: string;
    mulai: string | null;
    selesai: string | null;
    bayar: string | null;
    karyawan: number;
    netR: string;
    grossR: string;
    status: string;
    status_label: string;
    run_status: string | null;
}

/** Latest-run summary block backing the run-summary card. */
export interface PayrollSummary {
    period: string | null;
    period_id: number | null;
    pay_date: string | null;
    start_date: string | null;
    end_date: string | null;
    status: string | null;
    status_label: string;
    approval_note: string | null;
    rejection_note: string | null;
    total_gross: string;
    total_deduction: string;
    total_tax: string;
    total_net: string;
    employee_count: number;
}

/** A single earning/deduction line on the sample payslip. */
export interface SlipLine {
    k: string;
    v: string;
    /** One sentence: where this number comes from and which screen sets it. */
    why?: string | null;
}

/** Computed payslip preview — the picked employee, or the first active one. */
export interface Slip {
    employee: string;
    employee_id?: number | null;
    payslip_id?: number | null;
    earnings: SlipLine[];
    deductions: SlipLine[];
    /** Bruto pajak and the TER rate it resolved — shown, not deducted. */
    tax_info?: SlipLine[];
    /** Set to "import" when the figures come from an uploaded payroll. */
    source?: string | null;
    /**
     * Why the slip is incomplete: it could not be computed at all, or a per-day
     * attendance allowance is waiting for its cut-off to close.
     */
    notice?: string | null;
    gross: string;
    deduction: string;
    net: string;
}

/** A single employee who received pay in the selected period. */
export interface Recipient {
    id: number;
    employee_id: number;
    name: string;
    employee_number: string | null;
    gross: string;
    deduction: string;
    tax: string;
    net: string;
    tax_method: string | null;
}

export interface PayrollFilters {
    search?: string;
    status?: string;
    per_page?: string;
    period?: string;
    scheme?: string;
    only_paid?: string | boolean;
}

export interface PayrollDashboardKpi {
    key: string;
    label: string;
    icon: string;
    color: string;
    value: number;
    previous_value: number;
    change_percent: number | null;
}

export interface PayrollDashboardPoint {
    label: string;
    value: number;
}

export interface PayrollDashboardStatus {
    key: string;
    label: string;
    count: number;
    color: string;
}

export interface PayrollDashboardPayment {
    key: string;
    label: string;
    amount: number;
    count: number;
    percentage: number;
    color: string;
    icon: string;
}

export interface PayrollDashboardDepartment {
    code: string;
    label: string;
    value: number;
    employee_count: number;
}

export interface PayrollDashboard {
    period: string | null;
    period_id: number | null;
    previous_period: string | null;
    period_options: { id: number; label: string }[];
    kpis: PayrollDashboardKpi[];
    comparison: {
        current: number;
        previous: number;
        change_percent: number | null;
        difference: number;
    };
    trend: PayrollDashboardPoint[];
    distribution: PayrollDashboardPoint[];
    status: PayrollDashboardStatus[];
    departments: PayrollDashboardDepartment[];
    payment_summary: PayrollDashboardPayment[];
    insight: string;
}

export interface PayrollProps {
    periods: {
        data: Period[];
        meta: import('../employees/types').PaginationMeta;
        links: Record<string, string | null>;
    };
    summary: PayrollSummary & { recipient_count?: number };
    recipients: Recipient[];
    recipient_meta: import('../employees/types').PaginationMeta | null;
    slip: Slip;
    /** The shown run predates the tenant's latest payroll-config edit. */
    stale_run: boolean;
    /**
     * The attendance cut-off date the shown run still waits on, when it carries
     * per-present-day allowances that could not be counted yet. Null once every
     * allowance is settled. Approval and locking are refused while it is set.
     */
    pending_attendance_cut_off: string | null;
    /** Setup steps in documentation order, marked done from tenant data. */
    checklist: ChecklistStep[];
    /** Active employees selectable for the slip preview. */
    slip_employees: { id: number; name: string }[];
    filters: PayrollFilters;
}

/** One payroll setup step on the landing-page checklist. */
export interface ChecklistStep {
    key: string;
    label: string;
    done: boolean;
    href: string | null;
    hint: string | null;
}
