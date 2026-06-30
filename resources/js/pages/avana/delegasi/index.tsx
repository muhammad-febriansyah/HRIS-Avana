import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ApprovalDelegationController from '@/actions/App/Http/Controllers/Avana/ApprovalDelegationController';
import { AIcon, ActionBtn, btnOut, btnP, C, card, thCell } from '@/lib/avana';

/* ---------- types (mirror ApprovalDelegationController payloads) ---------- */

interface EmployeeRef {
    name: string | null;
    employee_number: string | null;
}

interface DelegationRow {
    id: number;
    delegator: EmployeeRef | null;
    delegate: EmployeeRef | null;
    scope: 'leave' | 'overtime' | 'all';
    scope_label: string;
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
}

interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string | null;
}

interface ScopeOption {
    value: string;
    label: string;
}

interface DelegasiIndexProps {
    delegations: DelegationRow[];
    employees: EmployeeOption[];
    scopes: ScopeOption[];
    kpis: {
        active_delegations: number;
        total_delegations: number;
    };
}

interface FlashProps {
    flash?: { success?: string };
    [key: string]: unknown;
}

interface DelegationFormData {
    delegator_id: string;
    delegate_id: string;
    scope: string;
    start_date: string;
    end_date: string;
    [key: string]: string;
}

const emptyForm: DelegationFormData = {
    delegator_id: '',
    delegate_id: '',
    scope: 'all',
    start_date: '',
    end_date: '',
};

const fieldLabelStyle: CSSProperties = { display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7, color: C.text };
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
const selectStyle: CSSProperties = { ...inputStyle, color: C.muted, cursor: 'pointer' };

function withError(base: CSSProperties, hasError: boolean): CSSProperties {
    return hasError ? { ...base, border: `1px solid ${C.red}`, boxShadow: '0 0 0 3px rgba(220,38,38,.08)' } : base;
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

function ActivePill({ active }: { active: boolean }) {
    const color = active ? C.green : C.muted;
    const bg = active ? 'rgba(22,163,74,.1)' : 'rgba(107,114,128,.12)';

    return (
        <span style={{ padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color, background: bg }}>
            {active ? 'Aktif' : 'Nonaktif'}
        </span>
    );
}

const headThStyle: CSSProperties = { ...thCell, padding: '11px 16px' };

export default function DelegasiIndex({ delegations, employees, scopes, kpis }: DelegasiIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [modalOpen, setModalOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState<DelegationRow | null>(null);
    const form = useForm<DelegationFormData>({ ...emptyForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const openModal = () => {
        form.clearErrors();
        form.setData({ ...emptyForm, start_date: new Date().toISOString().slice(0, 10) });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        form.reset();
        form.clearErrors();
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(ApprovalDelegationController.store().url, { onSuccess: () => closeModal() });
    };

    const toggle = (id: number) => {
        router.post(ApprovalDelegationController.toggle(id).url, {}, { preserveScroll: true });
    };

    const remove = () => {
        if (!confirmDelete) {
            return;
        }

        router.delete(ApprovalDelegationController.destroy(confirmDelete.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirmDelete(null),
        });
    };

    const kpiItems = [
        { label: 'Delegasi Aktif', value: kpis.active_delegations, icon: 'user-check', color: C.green },
        { label: 'Total Delegasi', value: kpis.total_delegations, icon: 'users', color: C.primary },
    ];

    return (
        <>
            <Head title="Delegasi Approval" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 22 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>HR Core</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Delegasi</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Delegasi Approval</h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>Limpahkan wewenang persetujuan saat seseorang berhalangan.</div>
                    </div>
                    <button onClick={openModal} style={btnP}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Buat Delegasi
                    </button>
                </div>

                {/* KPI cards */}
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginBottom: 22 }}>
                    {kpiItems.map((item) => (
                        <div key={item.label} style={{ ...card, padding: '18px 20px', flex: '1 1 220px' }}>
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

                {/* List */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ padding: '16px 18px', borderBottom: `1px solid ${C.border}`, fontSize: 15, fontWeight: 600, color: C.navy }}>
                        Daftar Delegasi
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 820 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={headThStyle}>Pemberi</th>
                                    <th style={headThStyle}>Penerima</th>
                                    <th style={headThStyle}>Cakupan</th>
                                    <th style={headThStyle}>Periode</th>
                                    <th style={headThStyle}>Status</th>
                                    <th style={{ ...headThStyle, textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {delegations.length === 0 && (
                                    <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td colSpan={6} style={{ padding: '48px 18px', textAlign: 'center', fontSize: 13.5, color: C.muted }}>
                                            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10 }}>
                                                <AIcon name="user-check" size={28} color={C.faint} />
                                                <div>Belum ada delegasi approval.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {delegations.map((row) => (
                                    <tr key={row.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td style={{ padding: '13px 16px' }}>
                                            <div style={{ fontSize: 13, fontWeight: 600, color: C.navy }}>{row.delegator?.name ?? '—'}</div>
                                            <div style={{ fontSize: 11.5, color: C.faint }}>{row.delegator?.employee_number ?? ''}</div>
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                <AIcon name="arrow-right" size={14} color={C.faint} />
                                                <div>
                                                    <div style={{ fontSize: 13, fontWeight: 600, color: C.navy }}>{row.delegate?.name ?? '—'}</div>
                                                    <div style={{ fontSize: 11.5, color: C.faint }}>{row.delegate?.employee_number ?? ''}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style={{ padding: '13px 16px', fontSize: 13, color: C.text }}>{row.scope_label}</td>
                                        <td style={{ padding: '13px 16px', fontSize: 12.5, color: C.muted }}>
                                            {row.start_date ?? '—'} &ndash; {row.end_date ?? '—'}
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <ActivePill active={row.is_active} />
                                        </td>
                                        <td style={{ padding: '13px 16px', textAlign: 'right' }}>
                                            <div style={{ display: 'inline-flex', gap: 6, flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                                                <ActionBtn
                                                    icon={row.is_active ? 'pause' : 'play'}
                                                    label={row.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                                    variant={row.is_active ? 'warning' : 'success'}
                                                    onClick={() => toggle(row.id)}
                                                />
                                                <ActionBtn icon="trash-2" label="Hapus" variant="danger" onClick={() => setConfirmDelete(row)} />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Create modal */}
            {modalOpen && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
                    <div onClick={closeModal} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
                    <form
                        onSubmit={submit}
                        style={{ position: 'relative', width: '100%', maxWidth: 460, maxHeight: '90vh', overflowY: 'auto', background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26, animation: 'toastIn .2s ease' }}
                    >
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy, marginBottom: 4 }}>Buat Delegasi</div>
                        <div style={{ fontSize: 13, color: C.muted, marginBottom: 18 }}>Tetapkan siapa yang menggantikan persetujuan.</div>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Pemberi Delegasi <span style={{ color: C.red }}>*</span>
                                </label>
                                <select value={form.data.delegator_id} onChange={(event) => form.setData('delegator_id', event.target.value)} style={withError(selectStyle, !!form.errors.delegator_id)}>
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((employee) => (
                                        <option key={employee.id} value={String(employee.id)}>
                                            {employee.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.delegator_id} />
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Penerima Delegasi <span style={{ color: C.red }}>*</span>
                                </label>
                                <select value={form.data.delegate_id} onChange={(event) => form.setData('delegate_id', event.target.value)} style={withError(selectStyle, !!form.errors.delegate_id)}>
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((employee) => (
                                        <option key={employee.id} value={String(employee.id)}>
                                            {employee.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.delegate_id} />
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Cakupan <span style={{ color: C.red }}>*</span>
                                </label>
                                <select value={form.data.scope} onChange={(event) => form.setData('scope', event.target.value)} style={withError(selectStyle, !!form.errors.scope)}>
                                    {scopes.map((scope) => (
                                        <option key={scope.value} value={scope.value}>
                                            {scope.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.scope} />
                            </div>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Mulai <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input type="date" value={form.data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} style={withError(inputStyle, !!form.errors.start_date)} />
                                    <FieldError message={form.errors.start_date} />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Selesai <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input type="date" value={form.data.end_date} onChange={(event) => form.setData('end_date', event.target.value)} style={withError(inputStyle, !!form.errors.end_date)} />
                                    <FieldError message={form.errors.end_date} />
                                </div>
                            </div>
                        </div>

                        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                            <button type="button" onClick={closeModal} style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}>
                                Batal
                            </button>
                            <button type="submit" disabled={form.processing} style={{ ...btnP, flex: 1, height: 44, justifyContent: 'center', opacity: form.processing ? 0.7 : 1 }}>
                                <AIcon name="check" size={16} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Delete confirmation */}
            {confirmDelete && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
                    <div onClick={() => setConfirmDelete(null)} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
                    <div style={{ position: 'relative', width: '100%', maxWidth: 400, background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26, animation: 'toastIn .2s ease' }}>
                        <div style={{ width: 48, height: 48, borderRadius: 12, background: 'rgba(220,38,38,.1)', color: C.red, display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 16 }}>
                            <AIcon name="trash-2" size={22} color={C.red} />
                        </div>
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>Hapus delegasi?</div>
                        <div style={{ fontSize: 13.5, color: C.muted, marginTop: 8, lineHeight: 1.55 }}>Delegasi approval ini akan dihapus permanen.</div>
                        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                            <button onClick={() => setConfirmDelete(null)} style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}>
                                Batal
                            </button>
                            <button
                                onClick={remove}
                                style={{ flex: 1, height: 44, background: C.red, color: '#fff', border: 'none', borderRadius: 9, fontSize: 14, fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}
                            >
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
