/**
 * Shared types for the AvanaHR HR analytics dashboard. These mirror the
 * read-only `AnalyticsController@index` payload (KPI cards + chart-ready
 * workforce metric series aggregated from existing tenant data).
 */

export type { FlashProps } from '../employees/types';

/** A single `{ label, value }` data point backing a bar/donut chart. */
export interface Series {
    label: string;
    value: number;
}

/** A headline KPI card. */
export interface Kpi {
    label: string;
    value: string;
    icon: string;
    color: string;
}

/** Latest payroll-run cost summary, or null when no run exists yet. */
export interface PayrollCost {
    period: string;
    gross: string;
    deduction: string;
    tax: string;
    net: string;
    employee_count: number;
}

/** Props for the analytics dashboard (`index.tsx`). */
export interface AnalyticsProps {
    period: string;
    kpis: Kpi[];
    activeStatus: Series[];
    byDepartment: Series[];
    byEmploymentStatus: Series[];
    byGender: Series[];
    attendance: Series[] | null;
    payroll: PayrollCost | null;
}
