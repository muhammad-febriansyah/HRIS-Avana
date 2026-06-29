import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import CashAdvanceController from '@/actions/App/Http/Controllers/Avana/CashAdvanceController';
import { AIcon, C, card, rp, statusBadge } from '@/lib/avana';
import type { FlashProps, PaginationMeta } from './employees/types';

/** A single cash advance row as shaped by `CashAdvanceController`. */
interface CashAdvanceRow {
    id: number;
    employee: {
        name: string;
        employee_number: string | null;
        initials: string;
        avatar_color: string;
    } | null;
    amount: number;
    installments: number;
    monthly_deduction: number;
    request_date: string | null;
    reason: string | null;
    status: 'pending' | 'approved' | 'rejected' | 'paid';
    status_label: 'Menunggu' | 'Disetujui' | 'Ditolak' | 'Lunas';
}

interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

interface KasbonFilters {
    search?: string;
    status?: 'pending' | 'approved' | 'rejected' | 'paid';
    per_page?: string;
}

interface KasbonProps {
    requests: {
        data: CashAdvanceRow[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    filters: KasbonFilters;
    employees: EmployeeOption[];
}

/** Form payload backing the "Ajukan Kasbon" form. */
interface CashAdvanceFormData {
    employee_id: string;
    amount: string;
    installments: string;
    request_date: string;
    reason: string;
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

/** Inline error message rendered under a field, prototype error style. */
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

/** Inclusive rupiah preview of the per-month deduction (0 when incomplete). */
function monthlyDeduction(amount: string, installments: string): number {
    const value = Number(amount);
    const count = Number(installments);

    if (
        !Number.isFinite(value) ||
        value <= 0 ||
        !Number.isFinite(count) ||
        count < 1
    ) {
        return 0;
    }

    return Math.round(value / count);
}

export default function AvanaKasbon({
    requests,
    filters,
    employees,
}: KasbonProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = requests.meta;

    const form = useForm<CashAdvanceFormData>({
        employee_id: '',
        amount: '',
        installments: '1',
        request_date: '',
        reason: '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const previewDeduction = monthlyDeduction(
        form.data.amount,
        form.data.installments,
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(CashAdvanceController.store(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const applyFilters = (next: Partial<KasbonFilters>) => {
        router.get(
            window.location.pathname,
            { ...filters, ...next, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const approve = (id: number) => {
        router.post(
            CashAdvanceController.approve(id).url,
            {},
            { preserveScroll: true },
        );
    };

    const reject = (id: number) => {
        router.post(
            CashAdvanceController.reject(id).url,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Kasbon" />
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
                        <span style={{ color: C.muted }}>Kasbon</span>
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
                        Kasbon / Cash Advance
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Ajukan dan kelola persetujuan kasbon karyawan
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
                            <div
                                style={{
                                    fontSize: 15,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                Ajukan Kasbon
                            </div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    marginTop: 3,
                                }}
                            >
                                Lengkapi formulir di bawah ini.
                            </div>
                        </div>
                        <form
                            onSubmit={submit}
                            style={{
                                padding: '20px 22px',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 16,
                            }}
                        >
                            <Field
                                label="Karyawan"
                                required
                                error={form.errors.employee_id}
                            >
                                <select
                                    value={form.data.employee_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'employee_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.employee_id,
                                    )}
                                >
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={String(employee.id)}
                                        >
                                            {employee.name} (
                                            {employee.employee_number})
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field
                                label="Jumlah (Rp)"
                                required
                                error={form.errors.amount}
                            >
                                <input
                                    type="number"
                                    min="1"
                                    step="1000"
                                    placeholder="1000000"
                                    value={form.data.amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'amount',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        textInputStyle,
                                        !!form.errors.amount,
                                    )}
                                />
                            </Field>

                            <Field
                                label="Jumlah Cicilan (bulan)"
                                required
                                error={form.errors.installments}
                            >
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    placeholder="1"
                                    value={form.data.installments}
                                    onChange={(event) =>
                                        form.setData(
                                            'installments',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        textInputStyle,
                                        !!form.errors.installments,
                                    )}
                                />
                            </Field>

                            <Field
                                label="Tanggal Pengajuan"
                                required
                                error={form.errors.request_date}
                            >
                                <input
                                    type="date"
                                    value={form.data.request_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'request_date',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        dateInputStyle,
                                        !!form.errors.request_date,
                                    )}
                                />
                            </Field>

                            <div
                                style={{
                                    background: C.surface,
                                    borderRadius: 8,
                                    padding: '11px 13px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 9,
                                    fontSize: 12.5,
                                    color: C.muted,
                                }}
                            >
                                <AIcon
                                    name="info"
                                    size={15}
                                    color={C.primary}
                                />
                                Potongan/bln&nbsp;
                                <span
                                    style={{ color: C.text, fontWeight: 600 }}
                                >
                                    {rp(previewDeduction)}
                                </span>
                            </div>

                            <Field label="Alasan" error={form.errors.reason}>
                                <textarea
                                    rows={3}
                                    placeholder="Tuliskan alasan pengajuan kasbon"
                                    value={form.data.reason}
                                    onChange={(event) =>
                                        form.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        textareaStyle,
                                        !!form.errors.reason,
                                    )}
                                />
                            </Field>

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
                                    cursor: form.processing
                                        ? 'not-allowed'
                                        : 'pointer',
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
                            <div
                                style={{
                                    fontSize: 15,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                Daftar Kasbon
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 8,
                                    alignItems: 'center',
                                }}
                            >
                                <div style={{ position: 'relative' }}>
                                    <span
                                        style={{
                                            position: 'absolute',
                                            left: 10,
                                            top: '50%',
                                            transform: 'translateY(-50%)',
                                            display: 'inline-flex',
                                        }}
                                    >
                                        <AIcon
                                            name="search"
                                            size={15}
                                            color={C.faint}
                                        />
                                    </span>
                                    <input
                                        type="search"
                                        placeholder="Cari karyawan"
                                        defaultValue={filters.search ?? ''}
                                        onChange={(event) =>
                                            applyFilters({
                                                search:
                                                    event.target.value ||
                                                    undefined,
                                            })
                                        }
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
                                    onChange={(event) =>
                                        applyFilters({
                                            status: (event.target.value ||
                                                undefined) as KasbonFilters['status'],
                                        })
                                    }
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
                                    <option value="pending">Menunggu</option>
                                    <option value="approved">Disetujui</option>
                                    <option value="rejected">Ditolak</option>
                                    <option value="paid">Lunas</option>
                                </select>
                            </div>
                        </div>
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    minWidth: 760,
                                }}
                            >
                                <thead>
                                    <tr style={{ background: '#FAFBFD' }}>
                                        <th style={headThStyle}>Karyawan</th>
                                        <th style={headThStyle}>Jumlah</th>
                                        <th style={headThStyle}>Cicilan</th>
                                        <th style={headThStyle}>
                                            Potongan/bln
                                        </th>
                                        <th style={headThStyle}>Tanggal</th>
                                        <th style={headThStyle}>Status</th>
                                        <th
                                            style={{
                                                ...headThStyle,
                                                textAlign: 'right',
                                            }}
                                        >
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {requests.data.length === 0 && (
                                        <tr
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
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
                                                    <AIcon
                                                        name="wallet"
                                                        size={28}
                                                        color={C.faint}
                                                    />
                                                    <div>
                                                        Tidak ada pengajuan
                                                        kasbon.
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {requests.data.map((row) => {
                                        const badge = statusBadge(
                                            row.status_label,
                                        );

                                        return (
                                            <tr
                                                key={row.id}
                                                style={{
                                                    borderTop: `1px solid ${C.line}`,
                                                }}
                                            >
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            alignItems:
                                                                'center',
                                                            gap: 10,
                                                        }}
                                                    >
                                                        <div
                                                            style={{
                                                                width: 32,
                                                                height: 32,
                                                                borderRadius:
                                                                    '50%',
                                                                flex: 'none',
                                                                background:
                                                                    row.employee
                                                                        ?.avatar_color ??
                                                                    C.faint,
                                                                color: '#fff',
                                                                display: 'flex',
                                                                alignItems:
                                                                    'center',
                                                                justifyContent:
                                                                    'center',
                                                                fontSize: 11.5,
                                                                fontWeight: 600,
                                                            }}
                                                        >
                                                            {row.employee
                                                                ?.initials ??
                                                                '?'}
                                                        </div>
                                                        <div>
                                                            <div
                                                                style={{
                                                                    fontSize: 13,
                                                                    fontWeight: 500,
                                                                    color: C.text,
                                                                }}
                                                            >
                                                                {row.employee
                                                                    ?.name ??
                                                                    '—'}
                                                            </div>
                                                            <div
                                                                style={{
                                                                    fontSize: 11.5,
                                                                    color: C.faint,
                                                                }}
                                                            >
                                                                {row.employee
                                                                    ?.employee_number ??
                                                                    ''}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                        fontSize: 13,
                                                        fontWeight: 600,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {rp(row.amount)}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                        fontSize: 12.5,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {row.installments}x bln
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                        fontSize: 12.5,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {rp(row.monthly_deduction)}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                        fontSize: 12.5,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {row.request_date ?? '—'}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            padding: '3px 10px',
                                                            borderRadius: 100,
                                                            fontSize: 11.5,
                                                            fontWeight: 600,
                                                            color: badge.color,
                                                            background:
                                                                badge.bg,
                                                        }}
                                                    >
                                                        {badge.label}
                                                    </span>
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                        textAlign: 'right',
                                                    }}
                                                >
                                                    {row.status ===
                                                    'pending' ? (
                                                        <div
                                                            style={{
                                                                display:
                                                                    'inline-flex',
                                                                gap: 6,
                                                            }}
                                                        >
                                                            <button
                                                                onClick={() =>
                                                                    approve(
                                                                        row.id,
                                                                    )
                                                                }
                                                                style={{
                                                                    display:
                                                                        'inline-flex',
                                                                    alignItems:
                                                                        'center',
                                                                    gap: 5,
                                                                    height: 30,
                                                                    padding:
                                                                        '0 11px',
                                                                    border: 'none',
                                                                    borderRadius: 7,
                                                                    background:
                                                                        'rgba(22,163,74,.1)',
                                                                    color: C.green,
                                                                    fontSize: 12,
                                                                    fontWeight: 600,
                                                                    cursor: 'pointer',
                                                                    transition:
                                                                        '.15s',
                                                                }}
                                                            >
                                                                <AIcon
                                                                    name="check"
                                                                    size={14}
                                                                    color={
                                                                        C.green
                                                                    }
                                                                />
                                                                Setujui
                                                            </button>
                                                            <button
                                                                onClick={() =>
                                                                    reject(
                                                                        row.id,
                                                                    )
                                                                }
                                                                style={{
                                                                    width: 30,
                                                                    height: 30,
                                                                    border: 'none',
                                                                    borderRadius: 7,
                                                                    background:
                                                                        'rgba(220,38,38,.08)',
                                                                    color: C.red,
                                                                    cursor: 'pointer',
                                                                    display:
                                                                        'inline-flex',
                                                                    alignItems:
                                                                        'center',
                                                                    justifyContent:
                                                                        'center',
                                                                    transition:
                                                                        '.15s',
                                                                }}
                                                            >
                                                                <AIcon
                                                                    name="x"
                                                                    size={14}
                                                                    color={
                                                                        C.red
                                                                    }
                                                                />
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <span
                                                            style={{
                                                                fontSize: 12.5,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            —
                                                        </span>
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
                                <span
                                    style={{ color: C.text, fontWeight: 500 }}
                                >
                                    {meta.from ?? 0}–{meta.to ?? 0}
                                </span>{' '}
                                dari{' '}
                                <span
                                    style={{ color: C.text, fontWeight: 500 }}
                                >
                                    {meta.total.toLocaleString('id-ID')}
                                </span>
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 6,
                                    alignItems: 'center',
                                }}
                            >
                                <button
                                    disabled={meta.current_page <= 1}
                                    onClick={() =>
                                        goToPage(meta.current_page - 1)
                                    }
                                    style={{
                                        height: 34,
                                        minWidth: 34,
                                        padding: '0 10px',
                                        border: `1px solid ${C.border}`,
                                        background: '#fff',
                                        borderRadius: 8,
                                        fontSize: 13,
                                        color:
                                            meta.current_page <= 1
                                                ? C.faint
                                                : C.text,
                                        cursor:
                                            meta.current_page <= 1
                                                ? 'not-allowed'
                                                : 'pointer',
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: 5,
                                    }}
                                >
                                    <AIcon name="chevron-left" size={15} />
                                </button>
                                <span
                                    style={{
                                        fontSize: 13,
                                        color: C.muted,
                                        padding: '0 4px',
                                    }}
                                >
                                    {meta.current_page} / {meta.last_page}
                                </span>
                                <button
                                    disabled={
                                        meta.current_page >= meta.last_page
                                    }
                                    onClick={() =>
                                        goToPage(meta.current_page + 1)
                                    }
                                    style={{
                                        height: 34,
                                        minWidth: 34,
                                        padding: '0 10px',
                                        border: `1px solid ${C.border}`,
                                        background: '#fff',
                                        borderRadius: 8,
                                        fontSize: 13,
                                        color:
                                            meta.current_page >= meta.last_page
                                                ? C.faint
                                                : C.text,
                                        cursor:
                                            meta.current_page >= meta.last_page
                                                ? 'not-allowed'
                                                : 'pointer',
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
