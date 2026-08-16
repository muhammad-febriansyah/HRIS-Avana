/** Shared types for the Insentif screens, mirroring `IncentiveController`. */

export type IncentiveBasis = 'attendance' | 'performance' | 'target' | 'manual';

export type IncentiveRuleRow = {
    id: number;
    sequence: number;
    min_value: number | null;
    max_value: number | null;
    amount_type: 'fixed' | 'per_unit' | 'percent_of_basic';
    amount: number;
    notes: string | null;
};

export type IncentiveSchemeRow = {
    id: number;
    route_key: string;
    code: string;
    name: string;
    basis: IncentiveBasis;
    component: string | null;
    payroll_component_id: number | null;
    effective_start_date: string | null;
    effective_end_date: string | null;
    rounding: string;
    rounding_unit: number;
    prorate_partial_period: boolean;
    status: string;
    notes: string | null;
    assignments_count: number;
    rules: IncentiveRuleRow[];
};

export type IncentiveCalculationRow = {
    id: number;
    route_key: string;
    scheme: string | null;
    scheme_name: string | null;
    employee: string | null;
    employee_number: string | null;
    measured_value: number;
    amount: number;
    computed_amount: number | null;
    overridden: boolean;
    status: 'draft' | 'pending' | 'approved' | 'rejected' | 'locked';
    reason: string | null;
    approver: string | null;
    approved_at: string | null;
    period?: string | null;
};

export type IncentiveAssignmentRow = {
    id: number;
    route_key: string;
    scheme: string | null;
    employee: string | null;
    employee_number: string | null;
    effective_start_date: string | null;
    effective_end_date: string | null;
    status: string;
};

export type PeriodOption = { id: number; name: string; status: string };
export type EmployeeOption = { id: number; name: string; employee_number: string };
export type ComponentOption = { id: number; code: string | null; name: string };

/** How each basis reads on screen. */
export const BASIS_LABELS: Record<IncentiveBasis, string> = {
    attendance: 'Kehadiran',
    performance: 'Kinerja',
    target: 'Target',
    manual: 'Manual',
};

/** What the measured figure means, per basis. */
export const BASIS_UNITS: Record<IncentiveBasis, string> = {
    attendance: 'hari hadir',
    performance: 'skor',
    target: 'pencapaian',
    manual: '—',
};

export const AMOUNT_TYPE_LABELS: Record<string, string> = {
    fixed: 'Nominal tetap',
    per_unit: 'Per satuan',
    percent_of_basic: '% dari gaji pokok',
};

export const STATUS_LABELS: Record<string, string> = {
    draft: 'Draft',
    pending: 'Menunggu persetujuan',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    locked: 'Terkunci (dibayar)',
};

export const rupiah = (value: number): string =>
    'Rp ' + Math.round(value).toLocaleString('id-ID');
