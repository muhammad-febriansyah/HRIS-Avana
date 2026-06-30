/**
 * Shared presentational bits for the payroll-components matrix: the
 * per-component calculation-basis editor.
 */

import { C } from '@/lib/avana';
import type { CalcBasis, ComponentRef } from './types';
import { calcBasisTag } from './types';

interface BasisSelectProps {
    component: ComponentRef;
    onChange: (basis: CalcBasis) => void;
}

/** Pill dropdown that edits a component's attendance calculation basis. */
export function BasisSelect({ component, onChange }: BasisSelectProps) {
    return (
        <select
            value={component.calc_basis}
            onChange={(event) => onChange(event.target.value as CalcBasis)}
            title="Basis perhitungan komponen"
            style={{
                fontSize: 10.5,
                fontWeight: 600,
                textTransform: 'none',
                letterSpacing: 0,
                padding: '2px 7px',
                borderRadius: 100,
                border: 'none',
                cursor: 'pointer',
                color: component.calc_basis === 'fixed' ? C.muted : C.primary,
                background:
                    component.calc_basis === 'fixed'
                        ? C.line
                        : 'rgba(47,84,201,.1)',
            }}
        >
            {(Object.keys(calcBasisTag) as CalcBasis[]).map((basis) => (
                <option key={basis} value={basis}>
                    {calcBasisTag[basis]}
                </option>
            ))}
        </select>
    );
}
