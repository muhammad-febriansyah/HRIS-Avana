export type ComplianceStatus = 'pending' | 'done';
export type StepState = 'done' | 'warn' | 'pending';

export interface PeriodOption {
    id: number;
    name: string;
}

export interface Pph21Summary {
    period: string | null;
    period_id: number | null;
    start_date: string | null;
    end_date: string | null;
    employee_count: number;
    employee_delta: number | null;
    gross: string;
    gross_raw: number;
    gross_delta_pct: number | null;
    previous_period: string | null;
    tax_due: string;
    tax_due_raw: number;
    tax_withheld: string;
    tax_withheld_raw: number;
    withheld_pct: number;
}

export interface ComplianceStep {
    key: string;
    label: string;
    state: StepState;
    detail: string;
}

export interface ComplianceRecord {
    deposit_status: ComplianceStatus;
    deposit_date: string | null;
    deposit_ntpn: string | null;
    report_status: ComplianceStatus;
    report_date: string | null;
    report_ntte: string | null;
    note: string | null;
}

export interface Compliance {
    period_id: number | null;
    steps: ComplianceStep[];
    done: number;
    total: number;
    overall: StepState;
    record: ComplianceRecord;
}

export interface CompletenessBar {
    label: string;
    done: number;
    total: number;
}

export interface CompletenessIssue {
    employee_id: number;
    name: string;
    employee_number: string | null;
    missing: string[];
}

export interface Completeness {
    bars: CompletenessBar[];
    issues: CompletenessIssue[];
    issue_total: number;
}

export interface RecapRow {
    id: number;
    name: string;
    employees: number;
    gross: string;
    tax: string;
    bukti_potong: string;
    deposit_status: ComplianceStatus;
    report_status: ComplianceStatus;
}

export interface Pph21EmployeeRow {
    id: number;
    employee_id: number;
    employee_route_key: string | null;
    name: string;
    employee_number: string | null;
    npwp: string | null;
    ptkp_status: string | null;
    ptkp_valid: boolean;
    ter_category: string | null;
    ter_rate: number | null;
    gross: string;
    tax: string;
    method: string | null;
    method_label: string | null;
    run_status: string | null;
}

export interface Pph21Filters {
    period: number | null;
    search: string;
    scheme: string;
    per_page: number;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface Pph21ReportProps {
    periods: PeriodOption[];
    summary: Pph21Summary;
    compliance: Compliance;
    completeness: Completeness;
    recap: RecapRow[];
    employees: Pph21EmployeeRow[];
    employee_meta: PaginationMeta | null;
    filters: Pph21Filters;
    can: { update_compliance: boolean };
}

/** Withholding schemes the drill-down table can be narrowed to. */
export const SCHEME_OPTIONS: { value: string; label: string }[] = [
    { value: '', label: 'Semua skema' },
    { value: 'ter_bulanan', label: 'TER Bulanan' },
    { value: 'ter_bulanan_thr', label: 'TER Bulanan (THR)' },
    { value: 'ter_harian', label: 'TER Harian' },
    { value: 'pasal17', label: 'Pasal 17' },
    { value: '50pct_pasal17', label: '50% × Pasal 17' },
    { value: 'annual_reconciliation', label: 'Rekonsiliasi Tahunan' },
    { value: 'exempt', label: 'Dikecualikan' },
];
