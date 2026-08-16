export interface BalanceCell {
    leave_type_id: number;
    leave_type: string;
    /** False when the year was never opened for this employee. */
    has_balance: boolean;
    quota: number | null;
    used: number | null;
    remaining: number | null;
}

export interface BalanceRow {
    employee_id: number;
    name: string;
    employee_number: string | null;
    department: string | null;
    balances: BalanceCell[];
}

export interface LeaveTypeColumn {
    id: number;
    name: string;
    default_quota: number;
}

export interface DepartmentOption {
    id: number;
    name: string;
}

export interface SaldoCutiFilters {
    search: string | null;
    department_id: string | number | null;
    year: number;
    per_page: number;
}

export interface SaldoCutiKpis {
    employees: number;
    covered: number;
    uncovered: number;
    quota: number;
    used: number;
    remaining: number;
}

export interface SaldoCutiProps {
    rows: {
        data: BalanceRow[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
    };
    leaveTypes: LeaveTypeColumn[];
    departments: DepartmentOption[];
    filters: SaldoCutiFilters;
    years: number[];
    kpis: SaldoCutiKpis;
}

export interface FlashProps {
    flash?: { success?: string };
    [key: string]: unknown;
}
