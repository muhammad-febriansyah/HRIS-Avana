/**
 * Shared presentational bits for the payroll-components matrix: the rupiah
 * cell input and the per-component calculation-basis editor.
 */

import type { ChangeEvent } from 'react';
import { C } from '@/lib/avana';
import type { CalcBasis, ComponentRef } from './types';
import { calcBasisTag } from './types';

interface RupiahInputProps {
    value: number;
    onChange: (amount: number) => void;
}

/** Rupiah-prefixed numeric input with thousand separators. */
export function RupiahInput({ value, onChange }: RupiahInputProps) {
    const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
        const digits = event.target.value.replace(/\D/g, '');
        onChange(digits === '' ? 0 : Number(digits));
    };

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 5,
                height: 36,
                padding: '0 10px',
                border: `1px solid ${C.border}`,
                borderRadius: 8,
                background: '#fff',
                minWidth: 140,
            }}
        >
            <span style={{ fontSize: 12, color: C.faint, flex: 'none' }}>
                Rp
            </span>
            <input
                inputMode="numeric"
                value={value ? value.toLocaleString('id-ID') : ''}
                onChange={handleChange}
                placeholder="0"
                style={{
                    border: 'none',
                    outline: 'none',
                    width: '100%',
                    fontSize: 13,
                    color: C.text,
                    textAlign: 'right',
                    background: 'transparent',
                    fontVariantNumeric: 'tabular-nums',
                }}
            />
        </div>
    );
}

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
