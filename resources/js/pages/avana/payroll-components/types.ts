/**
 * Shared types, constants and pure helpers for the AvanaHR
 * payroll-components (per-position component nominal) module. These mirror
 * the `PositionComponentController@index` payload.
 */

export type { FlashProps, PaginationMeta } from '../employees/types';

/** A job/position the matrix rows are keyed on. */
export interface PositionRef {
    id: number;
    name: string;
}

/** Calculation basis for an attendance-aware payroll component. */
export type CalcBasis = 'fixed' | 'per_present_day' | 'per_overtime_hour';

/** A payroll component the matrix columns are keyed on. */
export interface ComponentRef {
    id: number;
    name: string;
    type: 'earning' | 'deduction';
    calc_basis: CalcBasis;
}

/** A single persisted position × component nominal. */
export interface MatrixEntry {
    position_id: number;
    payroll_component_id: number;
    amount: number;
}

/** Props for the payroll-components matrix page (`index.tsx`). */
export interface PayrollComponentsProps {
    positions: PositionRef[];
    components: ComponentRef[];
    matrix: MatrixEntry[];
}

/** Short tag describing a component's calculation basis. */
export const calcBasisTag: Record<CalcBasis, string> = {
    fixed: 'Tetap',
    per_present_day: '/hari hadir',
    per_overtime_hour: '/jam lembur',
};

/** State key for a single matrix cell. */
export function cellKey(positionId: number, componentId: number): string {
    return `${positionId}:${componentId}`;
}
