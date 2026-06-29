import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import DutyTravelController from '@/actions/App/Http/Controllers/Avana/DutyTravelController';
import { AIcon, C, card, rp, statusBadge } from '@/lib/avana';
import type { FlashProps, PaginationMeta } from './employees/types';

/** Employee summary embedded in each duty travel row. */
interface RowEmployee {
    name: string;
    employee_number: string | null;
    initials: string;
    avatar_color: string;
}

type TravelStatus = 'pending' | 'approved' | 'rejected' | 'completed';
type TravelStatusLabel = 'Menunggu' | 'Disetujui' | 'Ditolak' | 'Selesai';

/** A single duty travel record as shaped by `DutyTravelController`. */
interface TravelRow {
    id: number;
    employee: RowEmployee | null;
    destination: string;
    purpose: string | null;
    start_date: string | null;
    end_date: string | null;
    days: number | null;
    transport: string | null;
    estimated_cost: number | null;
    per_diem: number | null;
    status: TravelStatus;
    status_label: TravelStatusLabel;
}

interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

interface DinasFilters {
    search?: string;
    status?: TravelStatus;
    per_page?: string;
}

interface DinasProps {
    travels: {
        data: TravelRow[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    filters: DinasFilters;
    employees: EmployeeOption[];
}

/** Form payload backing the "Ajukan Perjalanan Dinas" form. */
interface TravelFormData {
    employee_id: string;
    destination: string;
    purpose: string;
    start_date: string;
    end_date: string;
    transport: string;
    estimated_cost: string;
    per_diem: string;
}

const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
};

const selectStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.muted,
    background: '#fff',
    outline: 'none',
    cursor: 'pointer',
};

const dateInputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 11px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 12.5,
    color: C.muted,
    outline: 'none',
};

const textInputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
};

const textareaStyle: CSSProperties = {
    width: '100%',
    padding: '11px 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    outline: 'none',
    resize: 'vertical',
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

const headThStyle: CSSProperties = {
    padding: '11px 16px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
};

const statusOptions: { value: TravelStatus; label: string }[] = [
    { value: 'pending', label: 'Menunggu' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'rejected', label: 'Ditolak' },
    { value: 'completed', label: 'Selesai' },
];

/** Inline error message rendered under a field. */
function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <div style={errorTextStyle}>
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

/** Apply the red error border to a base input/select style when invalid. */
function withError(base: CSSProperties, hasError: boolean): CSSProperties {
    return hasError
        ? {
              ...base,
              border: `1px solid ${C.red}`,
              boxShadow: '0 0 0 3px rgba(220,38,38,.08)',
          }
        : base;
}

/** A single labelled field wrapper matching the prototype form style. */
function Field({
    label,
    required,
    error,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label style={fieldLabelStyle}>
                {label} {required && <span style={{ color: C.red }}>*</span>}
            </label>
            {children}
            <FieldError message={error} />
        </div>
    );
}

export default function AvanaDinas({ travels, filters, employees }: DinasProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = travels.meta;
    const [search, setSearch] = useState(filters.search ?? '');

    const form = useForm<TravelFormData>({
        employee_id: '',
        destination: '',
        purpose: '',
        start_date: '',
        end_date: '',
        transport: '',
        estimated_cost: '',
        per_diem: '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const submitTravel = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(DutyTravelController.store(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const applyFilters = (overrides: Partial<DinasFilters & { page: number }>) => {
        router.get(
            window.location.pathname,
            { ...filters, search, ...overrides },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        applyFilters({ page: 1 });
    };

    const setStatusFilter = (status: TravelStatus | undefined) => {
        applyFilters({ status, page: 1 });
    };

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const approveTravel = (id: number) =>
        router.post(DutyTravelController.approve(id).url, {}, { preserveScroll: true });

    const rejectTravel = (id: number) =>
        router.post(DutyTravelController.reject(id).url, {}, { preserveScroll: true });

    return (
        <>
            <Head title="Perjalanan Dinas" />
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
                        <span>Manajemen</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Perjalanan Dinas</span>
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
                        Perjalanan Dinas
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Ajukan dan kelola persetujuan perjalanan dinas tim Anda
                    </div>
                </div>

                <div
                    className="avn-abs"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '380px 1fr',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    {/* Form pengajuan */}
                    <div style={card}>
                        <div
                            style={{
                                padding: '18px 22px',
                                borderBottom: `1px solid ${C.line}`,
                            }}
                        >
                            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                                Ajukan Perjalanan Dinas
                            </div>
                            <div style={{ fontSize: 12.5, color: C.muted, marginTop: 3 }}>
                                Lengkapi formulir di bawah ini.
                            </div>
                        </div>
                        <form
                            onSubmit={submitTravel}
                            style={{
                                padding: '20px 22px',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 16,
                            }}
                        >
                            <Field label="Karyawan" required error={form.errors.employee_id}>
                                <select
                                    value={form.data.employee_id}
                                    onChange={(event) => form.setData('employee_id', event.target.value)}
                                    style={withError(selectStyle, !!form.errors.employee_id)}
                                >
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((employee) => (
                                        <option key={employee.id} value={String(employee.id)}>
                                            {employee.name} ({employee.employee_number})
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field label="Tujuan" required error={form.errors.destination}>
                                <input
                                    type="text"
                                    placeholder="Kota / lokasi tujuan"
                                    value={form.data.destination}
                                    onChange={(event) => form.setData('destination', event.target.value)}
                                    style={withError(textInputStyle, !!form.errors.destination)}
                                />
                            </Field>

                            <Field label="Keperluan" error={form.errors.purpose}>
                                <textarea
                                    rows={2}
                                    placeholder="Tuliskan keperluan perjalanan dinas"
                                    value={form.data.purpose}
                                    onChange={(event) => form.setData('purpose', event.target.value)}
                                    style={withError(textareaStyle, !!form.errors.purpose)}
                                />
                            </Field>

                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                                <Field label="Mulai" required error={form.errors.start_date}>
                                    <input
                                        type="date"
                                        value={form.data.start_date}
                                        onChange={(event) => form.setData('start_date', event.target.value)}
                                        style={withError(dateInputStyle, !!form.errors.start_date)}
                                    />
                                </Field>
                                <Field label="Selesai" required error={form.errors.end_date}>
                                    <input
                                        type="date"
                                        value={form.data.end_date}
                                        onChange={(event) => form.setData('end_date', event.target.value)}
                                        style={withError(dateInputStyle, !!form.errors.end_date)}
                                    />
                                </Field>
                            </div>

                            <Field label="Transportasi" error={form.errors.transport}>
                                <input
                                    type="text"
                                    placeholder="Pesawat / kereta / mobil"
                                    value={form.data.transport}
                                    onChange={(event) => form.setData('transport', event.target.value)}
                                    style={withError(textInputStyle, !!form.errors.transport)}
                                />
                            </Field>

                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                                <Field label="Estimasi Biaya" error={form.errors.estimated_cost}>
                                    <input
                                        type="number"
                                        min="0"
                                        step="1000"
                                        placeholder="0"
                                        value={form.data.estimated_cost}
                                        onChange={(event) => form.setData('estimated_cost', event.target.value)}
                                        style={withError(textInputStyle, !!form.errors.estimated_cost)}
                                    />
                                </Field>
                                <Field label="Uang Saku" error={form.errors.per_diem}>
                                    <input
                                        type="number"
                                        min="0"
                                        step="1000"
                                        placeholder="0"
                                        value={form.data.per_diem}
                                        onChange={(event) => form.setData('per_diem', event.target.value)}
                                        style={withError(textInputStyle, !!form.errors.per_diem)}
                                    />
                                </Field>
                            </div>

                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    width: '100%',
                                    height: 44,
                                    background: C.primary,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 8,
                                    fontSize: 14,
                                    fontWeight: 600,
                                    cursor: form.processing ? 'not-allowed' : 'pointer',
                                    opacity: form.processing ? 0.7 : 1,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="send" size={16} color="#fff" />
                                Kirim Pengajuan
                            </button>
                        </form>
                    </div>

                    {/* List + approval */}
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
                            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                                Daftar Perjalanan Dinas
                            </div>
                            <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                                <form onSubmit={submitSearch}>
                                    <input
                                        type="search"
                                        placeholder="Cari tujuan / karyawan…"
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        style={{
                                            height: 36,
                                            width: 200,
                                            padding: '0 12px',
                                            border: `1px solid ${C.border}`,
                                            borderRadius: 8,
                                            fontSize: 13,
                                            color: C.text,
                                            outline: 'none',
                                        }}
                                    />
                                </form>
                                <select
                                    value={filters.status ?? ''}
                                    onChange={(event) =>
                                        setStatusFilter((event.target.value || undefined) as TravelStatus | undefined)
                                    }
                                    style={{
                                        height: 36,
                                        padding: '0 12px',
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 8,
                                        fontSize: 13,
                                        color: C.muted,
                                        background: '#fff',
                                        outline: 'none',
                                        cursor: 'pointer',
                                    }}
                                >
                                    <option value="">Semua Status</option>
                                    {statusOptions.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div style={{ overflowX: 'auto' }}>
                            <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 720 }}>
                                <thead>
                                    <tr style={{ background: '#FAFBFD' }}>
                                        <th style={headThStyle}>Karyawan</th>
                                        <th style={headThStyle}>Tujuan</th>
                                        <th style={headThStyle}>Periode</th>
                                        <th style={headThStyle}>Estimasi</th>
                                        <th style={headThStyle}>Uang Saku</th>
                                        <th style={headThStyle}>Status</th>
                                        <th style={{ ...headThStyle, textAlign: 'right' }}>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {travels.data.length === 0 && (
                                        <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                            <td
                                                colSpan={7}
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
                                                    <AIcon name="plane" size={28} color={C.faint} />
                                                    <div>Tidak ada perjalanan dinas.</div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {travels.data.map((row) => {
                                        const badge = statusBadge(row.status_label);

                                        return (
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
                                                            <div
                                                                style={{
                                                                    fontSize: 13,
                                                                    fontWeight: 500,
                                                                    color: C.text,
                                                                }}
                                                            >
                                                                {row.employee?.name ?? '—'}
                                                            </div>
                                                            <div style={{ fontSize: 11.5, color: C.faint }}>
                                                                {row.employee?.employee_number ?? ''}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style={{ padding: '12px 16px' }}>
                                                    <div style={{ fontSize: 13, color: C.text }}>
                                                        {row.destination}
                                                    </div>
                                                    {row.purpose && (
                                                        <div style={{ fontSize: 11.5, color: C.faint }}>
                                                            {row.purpose}
                                                        </div>
                                                    )}
                                                </td>
                                                <td style={{ padding: '12px 16px', fontSize: 12.5, color: C.text }}>
                                                    {row.start_date} – {row.end_date}
                                                    {row.days !== null && (
                                                        <div style={{ fontSize: 11.5, color: C.faint }}>
                                                            {row.days} hari
                                                        </div>
                                                    )}
                                                </td>
                                                <td style={{ padding: '12px 16px', fontSize: 12.5, color: C.text }}>
                                                    {row.estimated_cost !== null ? rp(row.estimated_cost) : '—'}
                                                </td>
                                                <td style={{ padding: '12px 16px', fontSize: 12.5, color: C.text }}>
                                                    {row.per_diem !== null ? rp(row.per_diem) : '—'}
                                                </td>
                                                <td style={{ padding: '12px 16px' }}>
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
                                                </td>
                                                <td style={{ padding: '12px 16px', textAlign: 'right' }}>
                                                    {row.status === 'pending' ? (
                                                        <div style={{ display: 'inline-flex', gap: 6 }}>
                                                            <button
                                                                onClick={() => approveTravel(row.id)}
                                                                style={{
                                                                    display: 'inline-flex',
                                                                    alignItems: 'center',
                                                                    gap: 5,
                                                                    height: 30,
                                                                    padding: '0 11px',
                                                                    border: 'none',
                                                                    borderRadius: 7,
                                                                    background: 'rgba(22,163,74,.1)',
                                                                    color: C.green,
                                                                    fontSize: 12,
                                                                    fontWeight: 600,
                                                                    cursor: 'pointer',
                                                                    transition: '.15s',
                                                                }}
                                                            >
                                                                <AIcon name="check" size={14} color={C.green} />
                                                                Setujui
                                                            </button>
                                                            <button
                                                                onClick={() => rejectTravel(row.id)}
                                                                style={{
                                                                    width: 30,
                                                                    height: 30,
                                                                    border: 'none',
                                                                    borderRadius: 7,
                                                                    background: 'rgba(220,38,38,.08)',
                                                                    color: C.red,
                                                                    cursor: 'pointer',
                                                                    display: 'inline-flex',
                                                                    alignItems: 'center',
                                                                    justifyContent: 'center',
                                                                    transition: '.15s',
                                                                }}
                                                            >
                                                                <AIcon name="x" size={14} color={C.red} />
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <span style={{ fontSize: 12.5, color: C.faint }}>—</span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
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
                                dari{' '}
                                <span style={{ color: C.text, fontWeight: 500 }}>
                                    {meta.total.toLocaleString('id-ID')}
                                </span>
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
                                        gap: 5,
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
            </div>
        </>
    );
}
