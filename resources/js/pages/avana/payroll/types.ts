/**
 * Shared types for the AvanaHR payroll module page. These mirror the
 * `PayrollController@index` payload (periods, summary, sample slip, filters).
 */

export type { FlashProps, PaginationMeta } from '../employees/types';

/** A single payroll period row as serialized by `PayrollPeriodResource`. */
export interface Period {
    id: number;
    periode: string;
    bayar: string | null;
    karyawan: number;
    netR: string;
    grossR: string;
    status: string;
    status_label: string;
}

/** Latest-run summary block backing the run-summary card. */
export interface PayrollSummary {
    period: string | null;
    status: string | null;
    status_label: string;
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
}

/** Computed sample payslip for the first active employee. */
export interface Slip {
    employee: string;
    earnings: SlipLine[];
    deductions: SlipLine[];
    gross: string;
    deduction: string;
    net: string;
}

export interface PayrollFilters {
    search?: string;
    status?: string;
    per_page?: string;
}

export interface PayrollProps {
    periods: {
        data: Period[];
        meta: import('../employees/types').PaginationMeta;
        links: Record<string, string | null>;
    };
    summary: PayrollSummary;
    slip: Slip;
    filters: PayrollFilters;
}
