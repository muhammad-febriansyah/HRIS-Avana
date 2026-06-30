import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import LoanController from '@/actions/App/Http/Controllers/Avana/LoanController';
import {
    AIcon,
    ActionBtn,
    btnOut,
    btnP,
    C,
    card,
    rp,
    RupiahInput,
    statusBadge,
    thCell,
} from '@/lib/avana';

/* ---------- types (mirror LoanController payloads) ---------- */

interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string | null;
}

interface LoanRow {
    id: number;
    employee: {
        name: string;
        employee_number: string | null;
        initials: string;
        avatar_color: string;
    } | null;
    amount: number;
    tenor_months: number;
    interest_rate: number;
    monthly_installment: number;
    purpose: string | null;
    status: 'pending' | 'approved' | 'rejected' | 'paid';
    status_label: string;
    approved_at: string | null;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface LoanFilters {
    search?: string;
    status?: string;
    per_page?: string;
}

interface PinjamanIndexProps {
    loans: {
        data: LoanRow[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    filters: LoanFilters;
    employees: EmployeeOption[];
    kpis: {
        outstanding_total: number;
        pending_count: number;
    };
}

interface FlashProps {
    flash?: { success?: string };
    [key: string]: unknown;
}

interface LoanFormData {
    employee_id: string;
    amount: string;
    tenor_months: string;
    interest_rate: string;
    purpose: string;
    [key: string]: string;
}

const emptyForm: LoanFormData = {
    employee_id: '',
    amount: '',
    tenor_months: '6',
    interest_rate: '0',
    purpose: '',
};

const STATUS_OPTIONS = [
    { value: 'pending', label: 'Menunggu' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'rejected', label: 'Ditolak' },
    { value: 'paid', label: 'Lunas' },
];

const fieldLabelStyle: CSSProperties = {
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

const selectStyle: CSSProperties = { ...inputStyle, color: C.muted, cursor: 'pointer' };

function withError(base: CSSProperties, hasError: boolean): CSSProperties {
    return hasError
        ? { ...base, border: `1px solid ${C.red}`, boxShadow: '0 0 0 3px rgba(220,38,38,.08)' }
        : base;
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

function StatusPill({ label }: { label: string }) {
    const badge = statusBadge(label);

    return (
        <span
            style={{
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color: badge.color,
                background: badge.bg,
            }}
        >
            {badge.label}
        </span>
    );
}

const headThStyle: CSSProperties = { ...thCell, padding: '11px 16px' };

export default function PinjamanIndex({ loans, filters, employees, kpis }: PinjamanIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = loans.meta;

    const [modalOpen, setModalOpen] = useState(false);
    const form = useForm<LoanFormData>({ ...emptyForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const installmentPreview = useMemo(() => {
        const amount = Number(form.data.amount || 0);
        const tenor = Number(form.data.tenor_months || 0);
        const rate = Number(form.data.interest_rate || 0);

        if (amount <= 0 || tenor <= 0) {
            return 0;
        }

        return Math.round((amount * (1 + rate / 100)) / tenor);
    }, [form.data.amount, form.data.tenor_months, form.data.interest_rate]);

    const applyFilters = (next: Partial<LoanFilters>) => {
        router.get(
            window.location.pathname,
            { ...filters, ...next, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(window.location.pathname, { ...filters, page }, { preserveState: true, preserveScroll: true });
    };

    const openModal = () => {
        form.clearErrors();
        form.setData({ ...emptyForm, employee_id: employees[0] ? String(employees[0].id) : '' });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        form.reset();
        form.clearErrors();
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(LoanController.store().url, { onSuccess: () => closeModal() });
    };

    const approve = (id: number) => {
        router.post(LoanController.approve(id).url, {}, { preserveScroll: true });
    };

    const reject = (id: number) => {
        router.post(LoanController.reject(id).url, {}, { preserveScroll: true });
    };

    const kpiItems = [
        { label: 'Total Outstanding', value: rp(kpis.outstanding_total), icon: 'wallet', color: C.primary },
        { label: 'Menunggu Persetujuan', value: kpis.pending_count, icon: 'clock', color: C.amber },
    ];

    return (
        <>
            <Head title="Pinjaman" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>Payroll</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Pinjaman</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>
                            Pinjaman Karyawan
                        </h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                            Ajukan dan kelola persetujuan pinjaman karyawan.
                        </div>
                    </div>
                    <button onClick={openModal} style={btnP}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Ajukan Pinjaman
                    </button>
                </div>

                {/* KPI cards */}
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginBottom: 22 }}>
                    {kpiItems.map((item) => (
                        <div key={item.label} style={{ ...card, padding: '18px 20px', flex: '1 1 220px' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                                <div
                                    style={{
                                        width: 34,
                                        height: 34,
                                        borderRadius: 9,
                                        background: `${item.color}1a`,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <AIcon name={item.icon} size={17} color={item.color} />
                                </div>
                                <span style={{ fontSize: 12.5, color: C.muted, fontWeight: 500 }}>{item.label}</span>
                            </div>
                            <div style={{ fontSize: 24, fontWeight: 700, color: C.navy, letterSpacing: '-.02em' }}>{item.value}</div>
                        </div>
                    ))}
                </div>

                {/* List */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            flexWrap: 'wrap',
                            gap: 12,
                        }}
                    >
                        <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Daftar Pinjaman</div>
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                            <div style={{ position: 'relative' }}>
                                <span style={{ position: 'absolute', left: 10, top: '50%', transform: 'translateY(-50%)', display: 'inline-flex' }}>
                                    <AIcon name="search" size={15} color={C.faint} />
                                </span>
                                <input
                                    type="search"
                                    placeholder="Cari karyawan"
                                    defaultValue={filters.search ?? ''}
                                    onChange={(event) => applyFilters({ search: event.target.value || undefined })}
                                    style={{
                                        height: 36,
                                        padding: '0 12px 0 32px',
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 8,
                                        fontSize: 12.5,
                                        color: C.text,
                                        outline: 'none',
                                        width: 180,
                                    }}
                                />
                            </div>
                            <select
                                value={filters.status ?? ''}
                                onChange={(event) => applyFilters({ status: event.target.value || undefined })}
                                style={{
                                    height: 36,
                                    padding: '0 10px',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 8,
                                    fontSize: 12.5,
                                    color: C.muted,
                                    background: '#fff',
                                    outline: 'none',
                                    cursor: 'pointer',
                                }}
                            >
                                <option value="">Semua Status</option>
                                {STATUS_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 860 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={headThStyle}>Karyawan</th>
                                    <th style={headThStyle}>Jumlah</th>
                                    <th style={headThStyle}>Tenor</th>
                                    <th style={headThStyle}>Bunga</th>
                                    <th style={headThStyle}>Cicilan/bln</th>
                                    <th style={headThStyle}>Status</th>
                                    <th style={{ ...headThStyle, textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {loans.data.length === 0 && (
                                    <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td colSpan={7} style={{ padding: '48px 18px', textAlign: 'center', fontSize: 13.5, color: C.muted }}>
                                            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10 }}>
                                                <AIcon name="hand-coins" size={28} color={C.faint} />
                                                <div>Belum ada pengajuan pinjaman.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {loans.data.map((row) => (
                                    <tr key={row.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td style={{ padding: '12px 16px' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                <div
                                                    style={{
                                                        width: 32,
                                                        height: 32,
                                                        borderRadius: '50%',
                                                        flex: 'none',
                                                        background: row.employee?.avatar_color ?? C.faint,
                                                        color: '#fff',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        fontSize: 11.5,
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    {row.employee?.initials ?? '?'}
                                                </div>
                                                <div>
                                                    <div style={{ fontSize: 13, fontWeight: 500, color: C.text }}>{row.employee?.name ?? '—'}</div>
                                                    <div style={{ fontSize: 11.5, color: C.faint }}>{row.employee?.employee_number ?? ''}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style={{ padding: '12px 16px', fontSize: 13, fontWeight: 600, color: C.text }}>{rp(row.amount)}</td>
                                        <td style={{ padding: '12px 16px', fontSize: 12.5, color: C.muted }}>{row.tenor_months}x bln</td>
                                        <td style={{ padding: '12px 16px', fontSize: 12.5, color: C.muted }}>{row.interest_rate}%</td>
                                        <td style={{ padding: '12px 16px', fontSize: 12.5, color: C.text }}>{rp(row.monthly_installment)}</td>
                                        <td style={{ padding: '12px 16px' }}>
                                            <StatusPill label={row.status_label} />
                                        </td>
                                        <td style={{ padding: '12px 16px', textAlign: 'right' }}>
                                            {row.status === 'pending' ? (
                                                <div style={{ display: 'inline-flex', gap: 6, flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                                                    <ActionBtn icon="check" label="Setujui" variant="success" onClick={() => approve(row.id)} />
                                                    <ActionBtn icon="x" label="Tolak" variant="warning" onClick={() => reject(row.id)} />
                                                </div>
                                            ) : (
                                                <span style={{ fontSize: 12.5, color: C.faint }}>—</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination footer */}
                    <div
                        style={{
                            padding: '14px 18px',
                            borderTop: `1px solid ${C.border}`,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            flexWrap: 'wrap',
                            gap: 12,
                        }}
                    >
                        <div style={{ fontSize: 13, color: C.muted }}>
                            Menampilkan{' '}
                            <span style={{ color: C.text, fontWeight: 500 }}>
                                {meta.from ?? 0}–{meta.to ?? 0}
                            </span>{' '}
                            dari <span style={{ color: C.text, fontWeight: 500 }}>{meta.total.toLocaleString('id-ID')}</span>
                        </div>
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                            <button
                                disabled={meta.current_page <= 1}
                                onClick={() => goToPage(meta.current_page - 1)}
                                style={{
                                    height: 34,
                                    minWidth: 34,
                                    padding: '0 10px',
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    color: meta.current_page <= 1 ? C.faint : C.text,
                                    cursor: meta.current_page <= 1 ? 'not-allowed' : 'pointer',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                }}
                            >
                                <AIcon name="chevron-left" size={15} />
                            </button>
                            <span style={{ fontSize: 13, color: C.muted, padding: '0 4px' }}>
                                {meta.current_page} / {meta.last_page}
                            </span>
                            <button
                                disabled={meta.current_page >= meta.last_page}
                                onClick={() => goToPage(meta.current_page + 1)}
                                style={{
                                    height: 34,
                                    minWidth: 34,
                                    padding: '0 10px',
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    color: meta.current_page >= meta.last_page ? C.faint : C.text,
                                    cursor: meta.current_page >= meta.last_page ? 'not-allowed' : 'pointer',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                }}
                            >
                                <AIcon name="chevron-right" size={15} />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Request modal */}
            {modalOpen && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
                    <div onClick={closeModal} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
                    <form
                        onSubmit={submit}
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 460,
                            maxHeight: '90vh',
                            overflowY: 'auto',
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                            animation: 'toastIn .2s ease',
                        }}
                    >
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy, marginBottom: 4 }}>Ajukan Pinjaman</div>
                        <div style={{ fontSize: 13, color: C.muted, marginBottom: 18 }}>Buat pengajuan pinjaman untuk seorang karyawan.</div>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Karyawan <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={form.data.employee_id}
                                    onChange={(event) => form.setData('employee_id', event.target.value)}
                                    style={withError(selectStyle, !!form.errors.employee_id)}
                                >
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((employee) => (
                                        <option key={employee.id} value={String(employee.id)}>
                                            {employee.name}
                                            {employee.employee_number ? ` (${employee.employee_number})` : ''}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.employee_id} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Jumlah Pinjaman <span style={{ color: C.red }}>*</span>
                                </label>
                                <RupiahInput
                                    value={form.data.amount}
                                    onChange={(raw) => form.setData('amount', raw)}
                                    invalid={!!form.errors.amount}
                                />
                                <FieldError message={form.errors.amount} />
                            </div>

                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Tenor (bln) <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input
                                        type="number"
                                        min={1}
                                        value={form.data.tenor_months}
                                        onChange={(event) => form.setData('tenor_months', event.target.value)}
                                        style={withError(inputStyle, !!form.errors.tenor_months)}
                                    />
                                    <FieldError message={form.errors.tenor_months} />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>Bunga (%)</label>
                                    <input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={form.data.interest_rate}
                                        onChange={(event) => form.setData('interest_rate', event.target.value)}
                                        style={withError(inputStyle, !!form.errors.interest_rate)}
                                    />
                                    <FieldError message={form.errors.interest_rate} />
                                </div>
                            </div>

                            <div
                                style={{
                                    background: C.surface,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 10,
                                    padding: '12px 14px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                }}
                            >
                                <span style={{ fontSize: 12.5, color: C.muted }}>Estimasi cicilan / bulan</span>
                                <span style={{ fontSize: 15, fontWeight: 700, color: C.primary }}>{rp(installmentPreview)}</span>
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>Tujuan</label>
                                <textarea
                                    value={form.data.purpose}
                                    onChange={(event) => form.setData('purpose', event.target.value)}
                                    placeholder="Tujuan pinjaman (opsional)"
                                    style={{
                                        ...withError(inputStyle, !!form.errors.purpose),
                                        height: 'auto',
                                        minHeight: 72,
                                        padding: '11px 13px',
                                        resize: 'vertical',
                                    }}
                                />
                                <FieldError message={form.errors.purpose} />
                            </div>
                        </div>

                        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                            <button type="button" onClick={closeModal} style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}>
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    ...btnP,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: form.processing ? 0.7 : 1,
                                    cursor: form.processing ? 'not-allowed' : 'pointer',
                                }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Ajukan
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </>
    );
}
