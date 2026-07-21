import { router, useForm } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import { AIcon, ActionBtn, btnP, C, card } from '@/lib/avana';
import type { Formula, Option } from './types';

const input: CSSProperties = {
    width: '100%',
    padding: '9px 11px',
    borderRadius: 8,
    border: `1px solid ${C.line}`,
    fontSize: 13.5,
    outline: 'none',
    color: C.text,
    background: '#fff',
};
const th: CSSProperties = {
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    padding: '10px 14px',
    textTransform: 'uppercase',
};
const td: CSSProperties = { fontSize: 13, color: C.text, padding: '11px 14px' };

export default function FormulaTab({
    formulas,
    componentOptions,
}: {
    formulas: Formula[];
    componentOptions: Option[];
}) {
    const [openId, setOpenId] = useState<number | null>(null);

    const nameForm = useForm({ name: '', note: '' });
    const itemForm = useForm({
        tipe: 'penerimaan',
        payroll_component_id: '',
        operator: '+',
        nilai: '',
        prorate: false,
    });

    const addFormula = () =>
        nameForm.post('/avana/payroll/komponen/formula', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Formula dibuat');
                nameForm.reset();
            },
            onError: () => toast.error('Nama formula wajib diisi'),
        });

    const addItem = (formulaId: number) =>
        itemForm.post(`/avana/payroll/komponen/formula/${formulaId}/item`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Item formula ditambah');
                itemForm.reset();
            },
            onError: () => toast.error('Periksa isian item'),
        });

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            {/* create formula */}
            <div
                style={{
                    ...card,
                    padding: 18,
                    display: 'flex',
                    gap: 12,
                    alignItems: 'flex-end',
                    flexWrap: 'wrap',
                }}
            >
                <div style={{ flex: '1 1 220px' }}>
                    <label
                        style={{
                            fontSize: 13,
                            fontWeight: 600,
                            display: 'block',
                            marginBottom: 6,
                        }}
                    >
                        Nama Formula
                    </label>
                    <input
                        style={input}
                        value={nameForm.data.name}
                        onChange={(e) =>
                            nameForm.setData('name', e.target.value)
                        }
                        placeholder="mis. Total Tunjangan"
                    />
                </div>
                <div style={{ flex: '2 1 320px' }}>
                    <label
                        style={{
                            fontSize: 13,
                            fontWeight: 600,
                            display: 'block',
                            marginBottom: 6,
                        }}
                    >
                        Catatan
                    </label>
                    <input
                        style={input}
                        value={nameForm.data.note}
                        onChange={(e) =>
                            nameForm.setData('note', e.target.value)
                        }
                    />
                </div>
                <button
                    style={btnP}
                    disabled={nameForm.processing}
                    onClick={addFormula}
                >
                    <AIcon name="plus" size={15} color="#fff" />
                    Tambah Formula
                </button>
            </div>

            {formulas.length === 0 && (
                <div style={{ ...card, padding: 40, textAlign: 'center' }}>
                    <div style={{ fontSize: 14, color: C.faint }}>
                        Belum ada formula perhitungan.
                    </div>
                </div>
            )}

            {formulas.map((f) => (
                <div
                    key={f.id}
                    style={{ ...card, padding: 0, overflow: 'hidden' }}
                >
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                            padding: '14px 18px',
                            borderBottom:
                                openId === f.id
                                    ? `1px solid ${C.line}`
                                    : 'none',
                        }}
                    >
                        <div>
                            <div
                                style={{
                                    fontSize: 14.5,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                {f.name}
                            </div>
                            <div style={{ fontSize: 12, color: C.faint }}>
                                {f.items.length} item
                                {f.note ? ` · ${f.note}` : ''}
                            </div>
                        </div>
                        <div style={{ display: 'flex', gap: 6 }}>
                            <ActionBtn
                                icon={
                                    openId === f.id
                                        ? 'chevron-up'
                                        : 'chevron-down'
                                }
                                label={openId === f.id ? 'Tutup' : 'Item'}
                                onClick={() =>
                                    setOpenId(openId === f.id ? null : f.id)
                                }
                            />
                            <ActionBtn
                                icon="trash-2"
                                label="Hapus"
                                variant="danger"
                                onClick={() =>
                                    router.delete(
                                        `/avana/payroll/komponen/formula/${f.id}`,
                                        { preserveScroll: true },
                                    )
                                }
                            />
                        </div>
                    </div>

                    {openId === f.id && (
                        <div style={{ padding: '14px 18px' }}>
                            {f.items.length > 0 && (
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                        marginBottom: 14,
                                    }}
                                >
                                    <thead>
                                        <tr>
                                            {[
                                                'Tipe',
                                                'Komponen',
                                                'Operator',
                                                'Nilai',
                                                '',
                                            ].map((h) => (
                                                <th key={h} style={th}>
                                                    {h}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {f.items.map((it) => (
                                            <tr
                                                key={it.id}
                                                style={{
                                                    borderTop: `1px solid ${C.line}`,
                                                }}
                                            >
                                                <td style={td}>{it.tipe}</td>
                                                <td style={td}>
                                                    {it.component ?? '—'}
                                                </td>
                                                <td style={td}>
                                                    {it.operator}
                                                </td>
                                                <td style={td}>{it.nilai}</td>
                                                <td style={td}>
                                                    <ActionBtn
                                                        icon="trash-2"
                                                        label="Hapus"
                                                        variant="danger"
                                                        onClick={() =>
                                                            router.delete(
                                                                `/avana/payroll/komponen/formula-item/${it.id}`,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}

                            <div
                                style={{
                                    display: 'flex',
                                    gap: 8,
                                    flexWrap: 'wrap',
                                    alignItems: 'flex-end',
                                }}
                            >
                                <select
                                    style={{ ...input, width: 130 }}
                                    value={itemForm.data.payroll_component_id}
                                    onChange={(e) =>
                                        itemForm.setData(
                                            'payroll_component_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Komponen…</option>
                                    {componentOptions.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    style={{ ...input, width: 70 }}
                                    value={itemForm.data.operator}
                                    onChange={(e) =>
                                        itemForm.setData(
                                            'operator',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {['+', '-', '*', '/'].map((o) => (
                                        <option key={o} value={o}>
                                            {o}
                                        </option>
                                    ))}
                                </select>
                                <input
                                    type="number"
                                    style={{ ...input, width: 110 }}
                                    placeholder="Nilai"
                                    value={itemForm.data.nilai}
                                    onChange={(e) =>
                                        itemForm.setData(
                                            'nilai',
                                            e.target.value,
                                        )
                                    }
                                />
                                <button
                                    style={btnP}
                                    onClick={() => addItem(f.id)}
                                >
                                    <AIcon name="plus" size={14} color="#fff" />
                                    Tambah Item
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}
