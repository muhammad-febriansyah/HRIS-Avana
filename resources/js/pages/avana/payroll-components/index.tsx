import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import PayrollController from '@/actions/App/Http/Controllers/Avana/PayrollController';
import PositionComponentController from '@/actions/App/Http/Controllers/Avana/PositionComponentController';
import { AIcon, btnP, C } from '@/lib/avana';
import { ComponentMatrix } from './matrix';
import type {
    CalcBasis,
    FlashProps,
    PayrollComponentsProps,
} from './types';
import { cellKey } from './types';

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

                <ComponentMatrix
                    positions={positions}
                    components={components}
                    amounts={amounts}
                    onAmountChange={setAmount}
                    onBasisChange={changeBasis}
                />

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
