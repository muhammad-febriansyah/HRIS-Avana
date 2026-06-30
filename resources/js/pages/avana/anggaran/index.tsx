import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import BudgetController from '@/actions/App/Http/Controllers/Avana/BudgetController';
import { ActionBtn, AIcon, btnOut, btnP, C, card, rp, RupiahInput, thCell } from '@/lib/avana';
import type { FlashProps } from '../employees/types';

interface BudgetRow {
    id: number;
    category: string;
    period_type: string;
    period: string;
    planned_amount: number;
    actual_amount: number;
    variance: number;
    variance_percent: number;
    notes: string | null;
}

interface SelectOption {
    value: string;
    label: string;
}

interface AnggaranIndexProps {
    budgets: BudgetRow[];
    categories: SelectOption[];
    periodTypes: SelectOption[];
    kpis: { total_planned: number; total_actual: number; usage_percent: number };
}

interface BudgetFormData {
    category: string;
    period_type: string;
    period: string;
    planned_amount: string;
    actual_amount: string;
    notes: string;
}

const emptyForm: BudgetFormData = { category: 'operational', period_type: 'monthly', period: '', planned_amount: '', actual_amount: '', notes: '' };

const kpiCardStyle: CSSProperties = { ...card, padding: '18px 20px', flex: '1 1 180px' };
const tdStyle: CSSProperties = { padding: '12px 16px', fontSize: 13, color: C.text };
const labelStyle: CSSProperties = { display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7, color: C.text };
const inputStyle: CSSProperties = { width: '100%', height: 42, padding: '0 13px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 13.5, color: C.text, background: '#fff', outline: 'none' };
const textareaStyle: CSSProperties = { width: '100%', padding: '11px 13px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 13.5, color: C.text, outline: 'none', resize: 'vertical', minHeight: 64 };

/** Look up an Indonesian label for an enum value. */
function labelOf(options: SelectOption[], value: string): string {
    return options.find((o) => o.value === value)?.label ?? value;
}

export default function AnggaranIndex({ budgets, categories, periodTypes, kpis }: AnggaranIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<BudgetRow | null>(null);
    const [confirm, setConfirm] = useState<BudgetRow | null>(null);
    const form = useForm<BudgetFormData>({ ...emptyForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const openAdd = () => {
        setEditing(null);
        form.clearErrors();
        form.setData({ ...emptyForm });
        setModalOpen(true);
    };

    const openEdit = (budget: BudgetRow) => {
        setEditing(budget);
        form.clearErrors();
        form.setData({
            category: budget.category,
            period_type: budget.period_type,
            period: budget.period,
            planned_amount: String(budget.planned_amount),
            actual_amount: String(budget.actual_amount),
            notes: budget.notes ?? '',
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const action = editing ? BudgetController.update(editing.id) : BudgetController.store();
        form.submit(action, { preserveScroll: true, onSuccess: () => closeModal() });
    };

    const remove = () => {
        if (!confirm) {
            return;
        }
        router.delete(BudgetController.destroy(confirm.id).url, { preserveScroll: true, onSuccess: () => setConfirm(null) });
    };

    const kpiItems = [
        { label: 'Total Rencana', value: rp(kpis.total_planned), icon: 'target', color: C.primary },
        { label: 'Total Realisasi', value: rp(kpis.total_actual), icon: 'wallet', color: C.sky },
        { label: 'Terpakai', value: `${kpis.usage_percent}%`, icon: 'gauge', color: kpis.usage_percent > 100 ? C.red : C.green },
    ];

    return (
        <>
            <Head title="Anggaran" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 22 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>Sistem</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Anggaran</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Anggaran (Budget)</h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>Rencana vs realisasi anggaran per kategori.</div>
                    </div>
                    <button onClick={openAdd} style={{ ...btnP, cursor: 'pointer' }}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Anggaran
                    </button>
                </div>

                {/* KPI cards */}
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginBottom: 22 }}>
                    {kpiItems.map((item) => (
                        <div key={item.label} style={kpiCardStyle}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                                <div style={{ width: 34, height: 34, borderRadius: 9, background: `${item.color}1a`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <AIcon name={item.icon} size={17} color={item.color} />
                                </div>
                                <span style={{ fontSize: 12.5, color: C.muted, fontWeight: 500 }}>{item.label}</span>
                            </div>
                            <div style={{ fontSize: 22, fontWeight: 700, color: C.navy, letterSpacing: '-.02em' }}>{item.value}</div>
                        </div>
                    ))}
                </div>

                {/* Budget table */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 820 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Kategori</th>
                                    <th style={thCell}>Periode</th>
                                    <th style={{ ...thCell, textAlign: 'right' }}>Rencana</th>
                                    <th style={{ ...thCell, textAlign: 'right' }}>Realisasi</th>
                                    <th style={{ ...thCell, textAlign: 'right' }}>Varian</th>
                                    <th style={{ ...thCell, textAlign: 'right', padding: '12px 18px' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {budgets.length === 0 && (
                                    <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td colSpan={6} style={{ padding: '48px 18px', textAlign: 'center', fontSize: 13.5, color: C.muted }}>
                                            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10 }}>
                                                <AIcon name="piggy-bank" size={28} color={C.faint} />
                                                <div>Belum ada anggaran.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {budgets.map((budget) => {
                                    const over = budget.variance < 0;

                                    return (
                                        <tr key={budget.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                            <td style={{ ...tdStyle, fontWeight: 600, color: C.navy }}>{labelOf(categories, budget.category)}</td>
                                            <td style={tdStyle}>
                                                {budget.period}
                                                <span style={{ fontSize: 11.5, color: C.faint, marginLeft: 6 }}>{labelOf(periodTypes, budget.period_type)}</span>
                                            </td>
                                            <td style={{ ...tdStyle, textAlign: 'right' }}>{rp(budget.planned_amount)}</td>
                                            <td style={{ ...tdStyle, textAlign: 'right' }}>{rp(budget.actual_amount)}</td>
                                            <td style={{ ...tdStyle, textAlign: 'right', fontWeight: 600, color: over ? C.red : C.green }}>
                                                {over ? '-' : '+'}{rp(Math.abs(budget.variance))}
                                                <span style={{ fontSize: 11.5, marginLeft: 6, color: over ? C.red : C.green }}>({budget.variance_percent}%)</span>
                                            </td>
                                            <td style={{ ...tdStyle, textAlign: 'right' }}>
                                                <div style={{ display: 'inline-flex', gap: 6 }}>
                                                    <ActionBtn icon="pencil" label="Ubah" variant="neutral" onClick={() => openEdit(budget)} />
                                                    <ActionBtn icon="trash-2" label="Hapus" variant="danger" onClick={() => setConfirm(budget)} />
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Add / edit modal */}
            {modalOpen && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
                    <div onClick={closeModal} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
                    <form onSubmit={submit} style={{ position: 'relative', width: '100%', maxWidth: 460, maxHeight: '90vh', overflowY: 'auto', background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26 }}>
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy, marginBottom: 4 }}>{editing ? 'Ubah Anggaran' : 'Tambah Anggaran'}</div>
                        <div style={{ fontSize: 13, color: C.muted, marginBottom: 18 }}>Tetapkan rencana dan realisasi anggaran.</div>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                                <div>
                                    <label style={labelStyle}>Kategori <span style={{ color: C.red }}>*</span></label>
                                    <select value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} style={{ ...inputStyle, cursor: 'pointer' }}>
                                        {categories.map((c) => (
                                            <option key={c.value} value={c.value}>{c.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label style={labelStyle}>Tipe Periode <span style={{ color: C.red }}>*</span></label>
                                    <select value={form.data.period_type} onChange={(e) => form.setData('period_type', e.target.value)} style={{ ...inputStyle, cursor: 'pointer' }}>
                                        {periodTypes.map((p) => (
                                            <option key={p.value} value={p.value}>{p.label}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label style={labelStyle}>Periode <span style={{ color: C.red }}>*</span></label>
                                <input type="text" value={form.data.period} onChange={(e) => form.setData('period', e.target.value)} placeholder="cth: 2026-07 atau 2026" style={{ ...inputStyle, ...(form.errors.period ? { borderColor: C.red } : {}) }} />
                                {form.errors.period && <div style={{ fontSize: 12, color: C.red, marginTop: 6 }}>{form.errors.period}</div>}
                            </div>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                                <div>
                                    <label style={labelStyle}>Rencana <span style={{ color: C.red }}>*</span></label>
                                    <RupiahInput value={form.data.planned_amount} onChange={(raw) => form.setData('planned_amount', raw)} invalid={!!form.errors.planned_amount} />
                                    {form.errors.planned_amount && <div style={{ fontSize: 12, color: C.red, marginTop: 6 }}>{form.errors.planned_amount}</div>}
                                </div>
                                <div>
                                    <label style={labelStyle}>Realisasi <span style={{ color: C.red }}>*</span></label>
                                    <RupiahInput value={form.data.actual_amount} onChange={(raw) => form.setData('actual_amount', raw)} invalid={!!form.errors.actual_amount} />
                                    {form.errors.actual_amount && <div style={{ fontSize: 12, color: C.red, marginTop: 6 }}>{form.errors.actual_amount}</div>}
                                </div>
                            </div>
                            <div>
                                <label style={labelStyle}>Catatan</label>
                                <textarea value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} placeholder="Catatan (opsional)" style={textareaStyle} />
                            </div>
                        </div>

                        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                            <button type="button" onClick={closeModal} style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}>Batal</button>
                            <button type="submit" disabled={form.processing} style={{ ...btnP, flex: 1, height: 44, justifyContent: 'center', opacity: form.processing ? 0.7 : 1, cursor: form.processing ? 'not-allowed' : 'pointer' }}>
                                <AIcon name="check" size={16} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Confirm delete */}
            {confirm && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
                    <div onClick={() => setConfirm(null)} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
                    <div style={{ position: 'relative', width: '100%', maxWidth: 400, background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26 }}>
                        <div style={{ width: 48, height: 48, borderRadius: 12, background: 'rgba(220,38,38,.1)', display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 16 }}>
                            <AIcon name="trash-2" size={22} color={C.red} />
                        </div>
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>Hapus anggaran?</div>
                        <div style={{ fontSize: 13.5, color: C.muted, marginTop: 8, lineHeight: 1.55 }}>
                            Anggaran {labelOf(categories, confirm.category)} periode {confirm.period} akan dihapus.
                        </div>
                        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                            <button onClick={() => setConfirm(null)} style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}>Batal</button>
                            <button onClick={remove} style={{ flex: 1, height: 44, background: C.red, color: '#fff', border: 'none', borderRadius: 9, fontSize: 14, fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                                <AIcon name="trash-2" size={16} />
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
