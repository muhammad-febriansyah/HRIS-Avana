import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ChangeEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import PayrollController from '@/actions/App/Http/Controllers/Avana/PayrollController';
import PositionComponentController from '@/actions/App/Http/Controllers/Avana/PositionComponentController';
import { AIcon, btnP, C, card } from '@/lib/avana';
import type { FlashProps } from './employees/types';

/** A job/position the matrix rows are keyed on. */
interface PositionRef {
    id: number;
    name: string;
}

/** Calculation basis for an attendance-aware payroll component. */
type CalcBasis = 'fixed' | 'per_present_day' | 'per_overtime_hour';

/** A payroll component the matrix columns are keyed on. */
interface ComponentRef {
    id: number;
    name: string;
    type: 'earning' | 'deduction';
    calc_basis: CalcBasis;
}

/** A single persisted position × component nominal. */
interface MatrixEntry {
    position_id: number;
    payroll_component_id: number;
    amount: number;
}

interface PayrollComponentsProps {
    positions: PositionRef[];
    components: ComponentRef[];
    matrix: MatrixEntry[];
}

/** Short tag describing a component's calculation basis. */
const calcBasisTag: Record<CalcBasis, string> = {
    fixed: 'Tetap',
    per_present_day: '/hari hadir',
    per_overtime_hour: '/jam lembur',
};

/** State key for a single matrix cell. */
function cellKey(positionId: number, componentId: number): string {
    return `${positionId}:${componentId}`;
}

interface RupiahInputProps {
    value: number;
    onChange: (amount: number) => void;
}

/** Rupiah-prefixed numeric input with thousand separators. */
function RupiahInput({ value, onChange }: RupiahInputProps) {
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

export default function AvanaPayrollComponents({
    positions,
    components,
    matrix,
}: PayrollComponentsProps) {
    const { flash } = usePage<FlashProps>().props;

    const [amounts, setAmounts] = useState<Record<string, number>>(() => {
        const seed: Record<string, number> = {};

        for (const entry of matrix) {
            seed[cellKey(entry.position_id, entry.payroll_component_id)] =
                entry.amount;
        }

        return seed;
    });

    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const setAmount = (
        positionId: number,
        componentId: number,
        amount: number,
    ) => {
        setAmounts((current) => ({
            ...current,
            [cellKey(positionId, componentId)]: amount,
        }));
    };

    const changeBasis = (componentId: number, basis: CalcBasis) => {
        router.put(
            PositionComponentController.updateBasis().url,
            { payroll_component_id: componentId, calc_basis: basis },
            { preserveScroll: true, preserveState: false },
        );
    };

    const save = () => {
        const items = positions.flatMap((position) =>
            components.map((component) => ({
                position_id: position.id,
                payroll_component_id: component.id,
                amount: amounts[cellKey(position.id, component.id)] ?? 0,
            })),
        );

        router.put(
            PositionComponentController.update().url,
            { items },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const hasMatrix = positions.length > 0 && components.length > 0;

    return (
        <>
            <Head title="Komponen Gaji per Jabatan" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ marginBottom: 22 }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 7,
                            fontSize: 12.5,
                            color: C.faint,
                            marginBottom: 7,
                        }}
                    >
                        <Link
                            href={PayrollController.index().url}
                            style={{ color: C.faint, textDecoration: 'none' }}
                        >
                            Payroll
                        </Link>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>
                            Komponen per Jabatan
                        </span>
                    </div>
                    <h1
                        style={{
                            fontSize: 24,
                            fontWeight: 600,
                            color: C.navy,
                            margin: 0,
                            letterSpacing: '-.01em',
                        }}
                    >
                        Komponen Gaji per Jabatan
                    </h1>
                    <div
                        style={{
                            fontSize: 14,
                            color: C.muted,
                            marginTop: 4,
                            maxWidth: 720,
                        }}
                    >
                        Atur nominal komponen (uang makan, transport, lembur)
                        per jabatan. Komponen berbasis absensi dikali jumlah
                        hari hadir / jam lembur saat payroll.
                    </div>
                </div>

                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 640,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th
                                        style={{
                                            padding: '12px 18px',
                                            textAlign: 'left',
                                            fontSize: 11.5,
                                            fontWeight: 600,
                                            color: C.faint,
                                            textTransform: 'uppercase',
                                            position: 'sticky',
                                            left: 0,
                                            background: '#FAFBFD',
                                            minWidth: 180,
                                        }}
                                    >
                                        Jabatan
                                    </th>
                                    {components.map((component) => (
                                        <th
                                            key={component.id}
                                            style={{
                                                padding: '11px 16px',
                                                textAlign: 'right',
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.faint,
                                                textTransform: 'uppercase',
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    flexDirection: 'column',
                                                    alignItems: 'flex-end',
                                                    gap: 4,
                                                }}
                                            >
                                                <span
                                                    style={{
                                                        color: C.text,
                                                    }}
                                                >
                                                    {component.name}
                                                </span>
                                                <select
                                                    value={component.calc_basis}
                                                    onChange={(event) =>
                                                        changeBasis(
                                                            component.id,
                                                            event.target
                                                                .value as CalcBasis,
                                                        )
                                                    }
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
                                                        color:
                                                            component.calc_basis ===
                                                            'fixed'
                                                                ? C.muted
                                                                : C.primary,
                                                        background:
                                                            component.calc_basis ===
                                                            'fixed'
                                                                ? C.line
                                                                : 'rgba(47,84,201,.1)',
                                                    }}
                                                >
                                                    {(
                                                        Object.keys(
                                                            calcBasisTag,
                                                        ) as CalcBasis[]
                                                    ).map((basis) => (
                                                        <option
                                                            key={basis}
                                                            value={basis}
                                                        >
                                                            {calcBasisTag[basis]}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {!hasMatrix && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={
                                                Math.max(components.length, 1) +
                                                1
                                            }
                                            style={{
                                                padding: '48px 18px',
                                                textAlign: 'center',
                                                fontSize: 13.5,
                                                color: C.muted,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    flexDirection: 'column',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                }}
                                            >
                                                <AIcon
                                                    name="sliders-horizontal"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Belum ada jabatan atau
                                                    komponen untuk
                                                    dikonfigurasi.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {hasMatrix &&
                                    positions.map((position) => (
                                        <tr
                                            key={position.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                style={{
                                                    padding: '12px 18px',
                                                    fontSize: 13.5,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                    position: 'sticky',
                                                    left: 0,
                                                    background: '#fff',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {position.name}
                                            </td>
                                            {components.map((component) => (
                                                <td
                                                    key={component.id}
                                                    style={{
                                                        padding: '10px 16px',
                                                        textAlign: 'right',
                                                    }}
                                                >
                                                    <RupiahInput
                                                        value={
                                                            amounts[
                                                                cellKey(
                                                                    position.id,
                                                                    component.id,
                                                                )
                                                            ] ?? 0
                                                        }
                                                        onChange={(amount) =>
                                                            setAmount(
                                                                position.id,
                                                                component.id,
                                                                amount,
                                                            )
                                                        }
                                                    />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Sticky save bar */}
                <div
                    style={{
                        position: 'sticky',
                        bottom: 0,
                        marginTop: 18,
                        padding: '14px 18px',
                        background: 'rgba(255,255,255,.92)',
                        backdropFilter: 'blur(6px)',
                        border: `1px solid ${C.border}`,
                        borderRadius: 12,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 12,
                        flexWrap: 'wrap',
                        boxShadow: '0 -1px 8px rgba(15,23,42,.05)',
                    }}
                >
                    <div style={{ fontSize: 12.5, color: C.muted }}>
                        {positions.length} jabatan · {components.length}{' '}
                        komponen
                    </div>
                    <button
                        onClick={save}
                        disabled={processing || !hasMatrix}
                        style={{
                            ...btnP,
                            cursor:
                                processing || !hasMatrix
                                    ? 'not-allowed'
                                    : 'pointer',
                            opacity: processing || !hasMatrix ? 0.7 : 1,
                        }}
                    >
                        <AIcon name="save" size={16} color="#fff" />
                        Simpan
                    </button>
                </div>
            </div>
        </>
    );
}
