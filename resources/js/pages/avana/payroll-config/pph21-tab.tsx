import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import PayrollConfigController from '@/actions/App/Http/Controllers/Avana/PayrollConfigController';
import { ActionBtn, AIcon, C, card, rp, RupiahInput } from '@/lib/avana';
import type { PkpRate, PtkpRate } from './types';

/**
 * Tenant-scoped PPh 21 configuration (BPR-manual method): the Tarif PTKP table
 * (annual allowance per marital status) and the progressive Tarif PKP brackets.
 */
export default function Pph21Tab({
    ptkpRates,
    pkpRates,
}: {
    ptkpRates: PtkpRate[];
    pkpRates: PkpRate[];
}) {
    const year = new Date().getFullYear();

    const ptkpForm = useForm({
        ptkp_status: '',
        year,
        amount: '',
        note: '',
    });

    const pkpForm = useForm({
        year,
        up_to: '',
        rate: '',
        sort_order: pkpRates.length,
    });

    const [tab, setTab] = useState<'ptkp' | 'pkp'>('ptkp');

    const submitPtkp = () => {
        ptkpForm.post(PayrollConfigController.storePtkpRate().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Tarif PTKP disimpan');
                ptkpForm.reset('ptkp_status', 'amount', 'note');
            },
            onError: () => toast.error('Periksa isian PTKP'),
        });
    };

    const submitPkp = () => {
        pkpForm.post(PayrollConfigController.storePkpRate().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Tarif PKP disimpan');
                pkpForm.reset('up_to', 'rate');
            },
            onError: () => toast.error('Periksa isian PKP'),
        });
    };

    const del = (url: string) =>
        router.delete(url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Tarif dihapus'),
        });

    const input: React.CSSProperties = {
        padding: '9px 11px',
        borderRadius: 8,
        border: `1px solid ${C.line}`,
        fontSize: 13.5,
        outline: 'none',
        color: C.text,
        width: '100%',
    };
    const th: React.CSSProperties = {
        textAlign: 'left',
        fontSize: 12,
        fontWeight: 600,
        color: C.muted,
        padding: '0 14px 11px',
        borderBottom: `1px solid ${C.line}`,
    };
    const td: React.CSSProperties = {
        fontSize: 13.5,
        color: C.text,
        padding: '12px 14px',
        borderBottom: `1px solid ${C.line}`,
    };

    return (
        <div>
            {/* Sub-tabs */}
            <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
                {(
                    [
                        ['ptkp', 'Tarif PTKP'],
                        ['pkp', 'Tarif PKP (progresif)'],
                    ] as const
                ).map(([key, label]) => (
                    <button
                        key={key}
                        onClick={() => setTab(key)}
                        style={{
                            padding: '8px 14px',
                            borderRadius: 8,
                            border: `1px solid ${tab === key ? C.primary : C.line}`,
                            background: tab === key ? C.primary + '10' : '#fff',
                            color: tab === key ? C.primary : C.muted,
                            fontSize: 13,
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'ptkp' ? (
                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    {/* Add PTKP */}
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 100px 1.3fr 1.3fr auto',
                            gap: 10,
                            padding: 16,
                            alignItems: 'end',
                            borderBottom: `1px solid ${C.line}`,
                            background: C.surface,
                        }}
                    >
                        <Field label="Status PTKP">
                            <input
                                style={input}
                                placeholder="TK/0, K/2 …"
                                value={ptkpForm.data.ptkp_status}
                                onChange={(e) =>
                                    ptkpForm.setData(
                                        'ptkp_status',
                                        e.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Tahun">
                            <input
                                type="number"
                                style={input}
                                value={ptkpForm.data.year}
                                onChange={(e) =>
                                    ptkpForm.setData(
                                        'year',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </Field>
                        <Field label="Nilai Setahun (Rp)">
                            <RupiahInput
                                value={ptkpForm.data.amount}
                                onChange={(raw) =>
                                    ptkpForm.setData('amount', raw)
                                }
                                style={input}
                            />
                        </Field>
                        <Field label="Keterangan">
                            <input
                                style={input}
                                value={ptkpForm.data.note}
                                onChange={(e) =>
                                    ptkpForm.setData('note', e.target.value)
                                }
                            />
                        </Field>
                        <button
                            onClick={submitPtkp}
                            disabled={ptkpForm.processing}
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                gap: 7,
                                padding: '9px 16px',
                                borderRadius: 8,
                                border: 'none',
                                background: C.primary,
                                color: '#fff',
                                fontSize: 13,
                                fontWeight: 600,
                                cursor: 'pointer',
                                height: 38,
                            }}
                        >
                            <AIcon name="plus" size={15} color="#fff" />
                            Tambah
                        </button>
                    </div>

                    <table
                        style={{ width: '100%', borderCollapse: 'collapse' }}
                    >
                        <thead>
                            <tr>
                                <th style={{ ...th, paddingTop: 16 }}>
                                    Status
                                </th>
                                <th style={{ ...th, paddingTop: 16 }}>Tahun</th>
                                <th style={{ ...th, paddingTop: 16 }}>
                                    Nilai Setahun
                                </th>
                                <th style={{ ...th, paddingTop: 16 }}>
                                    Keterangan
                                </th>
                                <th style={{ ...th, paddingTop: 16 }} />
                            </tr>
                        </thead>
                        <tbody>
                            {ptkpRates.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        style={{
                                            ...td,
                                            textAlign: 'center',
                                            color: C.faint,
                                        }}
                                    >
                                        Belum ada tarif PTKP.
                                    </td>
                                </tr>
                            ) : (
                                ptkpRates.map((r) => (
                                    <tr key={r.id}>
                                        <td
                                            style={{
                                                ...td,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {r.ptkp_status}
                                        </td>
                                        <td style={td}>{r.year}</td>
                                        <td style={td}>{rp(r.amount)}</td>
                                        <td style={{ ...td, color: C.muted }}>
                                            {r.note ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                ...td,
                                                textAlign: 'right',
                                            }}
                                        >
                                            <ActionBtn
                                                icon="trash-2"
                                                label="Hapus"
                                                variant="danger"
                                                onClick={() =>
                                                    del(
                                                        PayrollConfigController.destroyPtkpRate(
                                                            r.id,
                                                        ).url,
                                                    )
                                                }
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '100px 1.5fr 1fr 90px auto',
                            gap: 10,
                            padding: 16,
                            alignItems: 'end',
                            borderBottom: `1px solid ${C.line}`,
                            background: C.surface,
                        }}
                    >
                        <Field label="Tahun">
                            <input
                                type="number"
                                style={input}
                                value={pkpForm.data.year}
                                onChange={(e) =>
                                    pkpForm.setData(
                                        'year',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </Field>
                        <Field label="Sampai (Rp, kosong = tak hingga)">
                            <RupiahInput
                                value={pkpForm.data.up_to}
                                onChange={(raw) =>
                                    pkpForm.setData('up_to', raw)
                                }
                                placeholder="60000000"
                                style={input}
                            />
                        </Field>
                        <Field label="Tarif (0.05 = 5%)">
                            <input
                                type="number"
                                step="0.01"
                                style={input}
                                value={pkpForm.data.rate}
                                onChange={(e) =>
                                    pkpForm.setData('rate', e.target.value)
                                }
                            />
                        </Field>
                        <Field label="Urutan">
                            <input
                                type="number"
                                style={input}
                                value={pkpForm.data.sort_order}
                                onChange={(e) =>
                                    pkpForm.setData(
                                        'sort_order',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </Field>
                        <button
                            onClick={submitPkp}
                            disabled={pkpForm.processing}
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                gap: 7,
                                padding: '9px 16px',
                                borderRadius: 8,
                                border: 'none',
                                background: C.primary,
                                color: '#fff',
                                fontSize: 13,
                                fontWeight: 600,
                                cursor: 'pointer',
                                height: 38,
                            }}
                        >
                            <AIcon name="plus" size={15} color="#fff" />
                            Tambah
                        </button>
                    </div>

                    <table
                        style={{ width: '100%', borderCollapse: 'collapse' }}
                    >
                        <thead>
                            <tr>
                                <th style={{ ...th, paddingTop: 16 }}>
                                    Urutan
                                </th>
                                <th style={{ ...th, paddingTop: 16 }}>Tahun</th>
                                <th style={{ ...th, paddingTop: 16 }}>
                                    Sampai
                                </th>
                                <th style={{ ...th, paddingTop: 16 }}>Tarif</th>
                                <th style={{ ...th, paddingTop: 16 }} />
                            </tr>
                        </thead>
                        <tbody>
                            {pkpRates.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        style={{
                                            ...td,
                                            textAlign: 'center',
                                            color: C.faint,
                                        }}
                                    >
                                        Belum ada bracket PKP.
                                    </td>
                                </tr>
                            ) : (
                                pkpRates.map((r) => (
                                    <tr key={r.id}>
                                        <td style={td}>{r.sort_order}</td>
                                        <td style={td}>{r.year}</td>
                                        <td style={td}>
                                            {r.up_to !== null
                                                ? rp(r.up_to)
                                                : 'tak hingga'}
                                        </td>
                                        <td
                                            style={{
                                                ...td,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {(r.rate * 100).toFixed(2)}%
                                        </td>
                                        <td
                                            style={{
                                                ...td,
                                                textAlign: 'right',
                                            }}
                                        >
                                            <ActionBtn
                                                icon="trash-2"
                                                label="Hapus"
                                                variant="danger"
                                                onClick={() =>
                                                    del(
                                                        PayrollConfigController.destroyPkpRate(
                                                            r.id,
                                                        ).url,
                                                    )
                                                }
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <label style={{ display: 'block' }}>
            <span
                style={{
                    display: 'block',
                    fontSize: 11.5,
                    fontWeight: 600,
                    color: C.muted,
                    marginBottom: 5,
                }}
            >
                {label}
            </span>
            {children}
        </label>
    );
}
