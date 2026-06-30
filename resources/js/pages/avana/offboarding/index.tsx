import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import OffboardingController from '@/actions/App/Http/Controllers/Avana/OffboardingController';
import { AIcon, ActionBtn, btnOut, btnP, C, card } from '@/lib/avana';

/* ============================================================
 * Offboarding board: resignation cases with clearance checklist.
 * ============================================================ */

interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

interface ClearanceRow {
    id: number;
    title: string;
    department: string | null;
    is_cleared: boolean;
}

interface CaseCard {
    id: number;
    employee_id: number;
    employee: { name: string; employee_number: string } | null;
    last_day: string | null;
    reason: string | null;
    status: string;
    items: ClearanceRow[];
    items_total: number;
    items_cleared: number;
}

interface OffboardingKpis {
    active: number;
    completed: number;
    pending_items: number;
}

interface OffboardingIndexProps {
    cases: CaseCard[];
    employees: EmployeeOption[];
    kpis: OffboardingKpis;
}

type FlashProps = { flash?: { success?: string } };

const labelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

const inputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

const textareaStyle: CSSProperties = {
    width: '100%',
    padding: '11px 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
    resize: 'vertical',
    minHeight: 72,
};

const kpiCardStyle: CSSProperties = { ...card, padding: '18px 20px', flex: '1 1 180px' };

export default function OffboardingIndex({ cases, employees, kpis }: OffboardingIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [caseModalOpen, setCaseModalOpen] = useState(false);
    const [expanded, setExpanded] = useState<number | null>(cases[0]?.id ?? null);
    const [confirm, setConfirm] = useState<CaseCard | null>(null);

    const caseForm = useForm({ employee_id: '', last_day: '', reason: '' });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const openCaseModal = () => {
        caseForm.clearErrors();
        caseForm.setData({ employee_id: employees[0] ? String(employees[0].id) : '', last_day: '', reason: '' });
        setCaseModalOpen(true);
    };

    const closeCaseModal = () => {
        setCaseModalOpen(false);
        caseForm.reset();
        caseForm.clearErrors();
    };

    const submitCase = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        caseForm.submit(OffboardingController.store(), { onSuccess: () => closeCaseModal() });
    };

    const toggleItem = (item: ClearanceRow) => {
        router.post(OffboardingController.toggleItem(item.id).url, {}, { preserveScroll: true });
    };

    const deleteCase = () => {
        if (!confirm) {
            return;
        }
        router.delete(OffboardingController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    const kpiItems = [
        { label: 'Kasus Aktif', value: kpis.active, icon: 'door-open', color: C.amber },
        { label: 'Selesai', value: kpis.completed, icon: 'circle-check', color: C.green },
        { label: 'Item Pending', value: kpis.pending_items, icon: 'list-todo', color: C.primary },
    ];

    return (
        <>
            <Head title="Offboarding" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 22 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>Manajemen</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Offboarding</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Offboarding &amp; Clearance</h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>Kelola proses pengunduran diri &amp; checklist clearance antar divisi.</div>
                    </div>
                    <button onClick={openCaseModal} disabled={employees.length === 0} style={{ ...btnP, opacity: employees.length === 0 ? 0.6 : 1, cursor: employees.length === 0 ? 'not-allowed' : 'pointer' }}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Kasus
                    </button>
                </div>

                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginBottom: 22 }}>
                    {kpiItems.map((item) => (
                        <div key={item.label} style={kpiCardStyle}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                                <div style={{ width: 34, height: 34, borderRadius: 9, background: `${item.color}1a`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <AIcon name={item.icon} size={17} color={item.color} />
                                </div>
                                <span style={{ fontSize: 12.5, color: C.muted, fontWeight: 500 }}>{item.label}</span>
                            </div>
                            <div style={{ fontSize: 26, fontWeight: 700, color: C.navy, letterSpacing: '-.02em' }}>{item.value}</div>
                        </div>
                    ))}
                </div>

                {cases.length === 0 && (
                    <div style={{ ...card, padding: '48px 18px', textAlign: 'center', color: C.muted, fontSize: 13.5 }}>
                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10 }}>
                            <AIcon name="door-open" size={28} color={C.faint} />
                            <div>Belum ada kasus offboarding.</div>
                        </div>
                    </div>
                )}

                <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                    {cases.map((item) => {
                        const pct = item.items_total > 0 ? Math.round((item.items_cleared / item.items_total) * 100) : 0;
                        const open = expanded === item.id;

                        return (
                            <div key={item.id} style={{ ...card, overflow: 'hidden' }}>
                                <div style={{ padding: '16px 20px', display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
                                    <div style={{ flex: '1 1 220px', minWidth: 0 }}>
                                        <div style={{ fontSize: 14.5, fontWeight: 600, color: C.navy }}>{item.employee?.name ?? '—'}</div>
                                        <div style={{ fontSize: 12.5, color: C.muted, marginTop: 2 }}>
                                            {item.employee?.employee_number ?? '—'}
                                            {item.last_day ? ` · Hari terakhir ${item.last_day}` : ''}
                                            {item.reason ? ` · ${item.reason}` : ''}
                                        </div>
                                    </div>
                                    <div style={{ flex: '1 1 240px', minWidth: 160 }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, color: C.muted, marginBottom: 6 }}>
                                            <span>{item.items_cleared}/{item.items_total} clearance</span>
                                            <span>{pct}%</span>
                                        </div>
                                        <div style={{ height: 8, borderRadius: 100, background: C.surface, overflow: 'hidden' }}>
                                            <div style={{ height: '100%', width: `${pct}%`, background: item.status === 'completed' ? C.green : C.amber, transition: 'width .2s' }} />
                                        </div>
                                    </div>
                                    <StatusBadge status={item.status} />
                                    <div style={{ display: 'inline-flex', gap: 6 }}>
                                        <ActionBtn icon={open ? 'chevron-up' : 'chevron-down'} label={open ? 'Tutup' : 'Clearance'} variant="neutral" onClick={() => setExpanded(open ? null : item.id)} />
                                        <ActionBtn icon="trash-2" label="Hapus" variant="danger" onClick={() => setConfirm(item)} />
                                    </div>
                                </div>

                                {open && (
                                    <div style={{ borderTop: `1px solid ${C.line}`, padding: '14px 20px', background: '#FAFBFD' }}>
                                        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                            {item.items.map((row) => (
                                                <div key={row.id} style={{ display: 'flex', alignItems: 'center', gap: 12, background: '#fff', border: `1px solid ${C.border}`, borderRadius: 9, padding: '10px 12px' }}>
                                                    <div style={{ flex: 1, minWidth: 0 }}>
                                                        <div style={{ fontSize: 13.5, fontWeight: 500, color: row.is_cleared ? C.faint : C.text, textDecoration: row.is_cleared ? 'line-through' : 'none' }}>{row.title}</div>
                                                        <div style={{ fontSize: 11.5, color: C.faint, marginTop: 2 }}>{row.department ?? 'Umum'}</div>
                                                    </div>
                                                    <ActionBtn icon={row.is_cleared ? 'rotate-ccw' : 'check'} label={row.is_cleared ? 'Batalkan' : 'Clear'} variant={row.is_cleared ? 'neutral' : 'success'} onClick={() => toggleItem(row)} />
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {caseModalOpen && (
                <ModalShell onClose={closeCaseModal} title="Tambah Kasus Offboarding" subtitle="Buka proses clearance untuk karyawan yang resign.">
                    <form onSubmit={submitCase}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                            <div>
                                <label style={labelStyle}>Karyawan <span style={{ color: C.red }}>*</span></label>
                                <select value={caseForm.data.employee_id} onChange={(e) => caseForm.setData('employee_id', e.target.value)} style={{ ...inputStyle, cursor: 'pointer' }}>
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((emp) => (
                                        <option key={emp.id} value={String(emp.id)}>{emp.name} ({emp.employee_number})</option>
                                    ))}
                                </select>
                                {caseForm.errors.employee_id && <FieldError message={caseForm.errors.employee_id} />}
                            </div>
                            <div>
                                <label style={labelStyle}>Hari Terakhir</label>
                                <input type="date" value={caseForm.data.last_day} onChange={(e) => caseForm.setData('last_day', e.target.value)} style={inputStyle} />
                                {caseForm.errors.last_day && <FieldError message={caseForm.errors.last_day} />}
                            </div>
                            <div>
                                <label style={labelStyle}>Alasan</label>
                                <textarea value={caseForm.data.reason} onChange={(e) => caseForm.setData('reason', e.target.value)} placeholder="Alasan pengunduran diri (opsional)" style={textareaStyle} />
                                {caseForm.errors.reason && <FieldError message={caseForm.errors.reason} />}
                            </div>
                        </div>
                        <ModalActions processing={caseForm.processing} onCancel={closeCaseModal} />
                    </form>
                </ModalShell>
            )}

            {confirm && (
                <ConfirmModal
                    title="Hapus kasus?"
                    body={<>Kasus offboarding <strong style={{ color: C.text }}>{confirm.employee?.name}</strong> beserta seluruh item clearance-nya akan dihapus.</>}
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteCase}
                />
            )}
        </>
    );
}

function StatusBadge({ status }: { status: string }) {
    const done = status === 'completed';
    return (
        <span style={{ display: 'inline-block', padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: done ? C.green : C.amber, background: done ? 'rgba(22,163,74,.1)' : 'rgba(217,119,6,.1)' }}>
            {done ? 'Selesai' : 'Proses'}
        </span>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }
    return (
        <div style={{ fontSize: 12, color: C.red, marginTop: 6, display: 'flex', alignItems: 'center', gap: 5 }}>
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

function ModalShell({ title, subtitle, onClose, children }: { title: string; subtitle: string; onClose: () => void; children: React.ReactNode }) {
    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
            <div style={{ position: 'relative', width: '100%', maxWidth: 460, maxHeight: '90vh', overflowY: 'auto', background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26 }}>
                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy, marginBottom: 4 }}>{title}</div>
                <div style={{ fontSize: 13, color: C.muted, marginBottom: 18 }}>{subtitle}</div>
                {children}
            </div>
        </div>
    );
}

function ModalActions({ processing, onCancel }: { processing: boolean; onCancel: () => void }) {
    return (
        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
            <button type="button" onClick={onCancel} style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}>Batal</button>
            <button type="submit" disabled={processing} style={{ ...btnP, flex: 1, height: 44, justifyContent: 'center', opacity: processing ? 0.7 : 1 }}>
                <AIcon name="check" size={16} color="#fff" />
                Simpan
            </button>
        </div>
    );
}

function ConfirmModal({ title, body, onCancel, onConfirm }: { title: string; body: React.ReactNode; onCancel: () => void; onConfirm: () => void }) {
    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
            <div onClick={onCancel} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
            <div style={{ position: 'relative', width: '100%', maxWidth: 400, background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26 }}>
                <div style={{ width: 48, height: 48, borderRadius: 12, background: 'rgba(220,38,38,.1)', color: C.red, display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 16 }}>
                    <AIcon name="trash-2" size={22} color={C.red} />
                </div>
                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>{title}</div>
                <div style={{ fontSize: 13.5, color: C.muted, marginTop: 8, lineHeight: 1.55 }}>{body}</div>
                <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                    <button onClick={onCancel} style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}>Batal</button>
                    <button onClick={onConfirm} style={{ flex: 1, height: 44, background: C.red, color: '#fff', border: 'none', borderRadius: 9, fontSize: 14, fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                        <AIcon name="trash-2" size={16} />
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    );
}
