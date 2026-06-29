import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import AttendancePenaltyController from '@/actions/App/Http/Controllers/Avana/AttendancePenaltyController';
import { AIcon, btnOut, btnP, C, rp, thCell } from '@/lib/avana';
import type { FlashProps, PaginationMeta } from './employees/types';

/** Compact employee option backing the manual penalty form. */
interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

/** Employee summary embedded in a penalty row. */
interface PenaltyEmployee {
    name: string;
    employee_number: string;
    initials: string;
    avatar_color: string;
}

/** A single attendance penalty row as serialized by the controller. */
interface PenaltyRow {
    id: number;
    employee: PenaltyEmployee | null;
    date: string;
    date_raw: string;
    violation_type: string;
    penalty_type: string;
    amount: number;
    notes: string | null;
    status: string;
}

interface SanksiFilters {
    search?: string;
    violation_type?: string;
    per_page?: string;
}

interface SanksiProps {
    penalties: {
        data: PenaltyRow[];
        meta: PaginationMeta;
    };
    employees: EmployeeOption[];
    filters: SanksiFilters;
}

/** Flat payload backing the manual "Tambah Sanksi" form. */
interface PenaltyFormData {
    employee_id: string;
    date: string;
    violation_type: string;
    penalty_type: string;
    amount: string;
    notes: string;
}

const emptyForm: PenaltyFormData = {
    employee_id: '',
    date: '',
    violation_type: 'late',
    penalty_type: 'warning',
    amount: '',
    notes: '',
};

/** Indonesian labels for the violation enum. */
const VIOLATION_LABELS: Record<string, string> = {
    late: 'Terlambat',
    absent: 'Alpa',
    incomplete: 'Belum Lengkap',
    early_leave: 'Pulang Cepat',
};

const violationOptions = Object.entries(VIOLATION_LABELS).map(
    ([value, label]) => ({ value, label }),
);

/** Badge palette for a violation type. */
function violationBadge(type: string) {
    const map: Record<string, [string, string]> = {
        late: ['#D97706', 'rgba(217,119,6,.1)'],
        absent: ['#DC2626', 'rgba(220,38,38,.1)'],
        incomplete: ['#D97706', 'rgba(217,119,6,.1)'],
        early_leave: ['#2F54C9', 'rgba(47,84,201,.1)'],
    };
    const [color, bg] = map[type] ?? ['#6B7280', 'rgba(107,114,128,.12)'];

    return { label: VIOLATION_LABELS[type] ?? type, color, bg };
}

/** Badge palette for the penalty type (warning vs deduction). */
function penaltyBadge(type: string) {
    return type === 'deduction'
        ? { label: 'Potongan', color: C.red, bg: 'rgba(220,38,38,.1)' }
        : { label: 'Peringatan', color: C.amber, bg: 'rgba(217,119,6,.1)' };
}

const filterSelectStyle: CSSProperties = {
    height: 38,
    padding: '0 12px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13,
    color: C.muted,
    background: '#fff',
    cursor: 'pointer',
    outline: 'none',
};

const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
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

const selectStyle: CSSProperties = {
    ...inputStyle,
    color: C.muted,
    cursor: 'pointer',
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

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

/** Build a windowed list of page numbers with ellipsis markers. */
function pageItems(current: number, last: number): (number | 'gap')[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const items: (number | 'gap')[] = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    if (start > 2) {
        items.push('gap');
    }

    for (let page = start; page <= end; page++) {
        items.push(page);
    }

    if (end < last - 1) {
        items.push('gap');
    }

    items.push(last);

    return items;
}

export default function AvanaSanksi({
    penalties,
    employees,
    filters,
}: SanksiProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = penalties.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [generateOpen, setGenerateOpen] = useState(false);
    const [confirm, setConfirm] = useState<PenaltyRow | null>(null);
    const isFirstSearch = useRef(true);

    const form = useForm<PenaltyFormData>({ ...emptyForm });
    const generateForm = useForm({ start_date: '', end_date: '' });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    useEffect(() => {
        if (isFirstSearch.current) {
            isFirstSearch.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                window.location.pathname,
                { ...filters, search: search || undefined, page: 1 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const applyFilter = (key: string, value: string) => {
        router.get(
            window.location.pathname,
            { ...filters, [key]: value || undefined, page: 1 },
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

    const openCreate = () => {
        form.clearErrors();
        form.setData({ ...emptyForm });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        form.reset();
        form.clearErrors();
    };

    const openGenerate = () => {
        generateForm.clearErrors();
        generateForm.setData({ start_date: '', end_date: '' });
        setGenerateOpen(true);
    };

    const closeGenerate = () => {
        setGenerateOpen(false);
        generateForm.reset();
        generateForm.clearErrors();
    };

    const submitForm = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(AttendancePenaltyController.store(), {
            onSuccess: () => closeModal(),
        });
    };

    const submitGenerate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        generateForm.submit(AttendancePenaltyController.generate(), {
            onSuccess: () => closeGenerate(),
        });
    };

    const deletePenalty = () => {
        if (!confirm) {
            return;
        }

        router.delete(AttendancePenaltyController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Sanksi Absensi" />
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
                            <span style={{ color: C.muted }}>
                                Sanksi Absensi
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
                            Sanksi Absensi
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola sanksi pelanggaran kehadiran karyawan
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <button onClick={openGenerate} style={btnOut}>
                            <AIcon name="wand-sparkles" size={16} />
                            Generate dari Absensi
                        </button>
                        <button onClick={openCreate} style={btnP}>
                            <AIcon name="plus" size={16} />
                            Tambah Sanksi
                        </button>
                    </div>
                </div>

                {/* Table card */}
                <div
                    style={{
                        background: '#fff',
                        border: `1px solid ${C.border}`,
                        borderRadius: 12,
                        boxShadow: '0 1px 2px rgba(15,23,42,.04)',
                        overflow: 'hidden',
                    }}
                >
                    {/* Filter bar */}
                    <div
                        style={{
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            display: 'flex',
                            gap: 10,
                            flexWrap: 'wrap',
                            alignItems: 'center',
                        }}
                    >
                        <div
                            style={{
                                position: 'relative',
                                flex: 1,
                                minWidth: 220,
                                maxWidth: 320,
                            }}
                        >
                            <AIcon
                                name="search"
                                size={16}
                                color={C.faint}
                                style={{
                                    position: 'absolute',
                                    left: 12,
                                    top: '50%',
                                    transform: 'translateY(-50%)',
                                }}
                            />
                            <input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nama atau NIK karyawan…"
                                style={{
                                    width: '100%',
                                    height: 38,
                                    padding: '0 12px 0 36px',
                                    background: C.surface,
                                    border: '1px solid transparent',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    outline: 'none',
                                    transition: '.15s',
                                }}
                            />
                        </div>
                        <select
                            aria-label="Jenis Pelanggaran"
                            value={filters.violation_type ?? ''}
                            onChange={(event) =>
                                applyFilter('violation_type', event.target.value)
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Pelanggaran</option>
                            {violationOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <div style={{ flex: 1 }} />
                    </div>

                    {/* Table */}
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 880,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Karyawan</th>
                                    <th style={thCell}>Tanggal</th>
                                    <th style={thCell}>Pelanggaran</th>
                                    <th style={thCell}>Jenis Sanksi</th>
                                    <th style={thCell}>Nominal</th>
                                    <th style={thCell}>Catatan</th>
                                    <th
                                        style={{
                                            ...thCell,
                                            padding: '12px 18px',
                                            textAlign: 'right',
                                        }}
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {penalties.data.length === 0 && (
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
                                                    name="shield-alert"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Belum ada sanksi absensi.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {penalties.data.map((penalty) => {
                                    const vb = violationBadge(
                                        penalty.violation_type,
                                    );
                                    const pb = penaltyBadge(
                                        penalty.penalty_type,
                                    );

                                    return (
                                        <tr
                                            key={penalty.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                                transition: 'background .15s',
                                            }}
                                        >
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 11,
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            width: 36,
                                                            height: 36,
                                                            borderRadius: '50%',
                                                            flex: 'none',
                                                            background:
                                                                penalty.employee
                                                                    ?.avatar_color ??
                                                                C.faint,
                                                            color: '#fff',
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            justifyContent:
                                                                'center',
                                                            fontSize: 12.5,
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        {penalty.employee
                                                            ?.initials ?? '?'}
                                                    </div>
                                                    <div style={{ minWidth: 0 }}>
                                                        <div
                                                            style={{
                                                                fontSize: 13.5,
                                                                fontWeight: 600,
                                                                color: C.navy,
                                                            }}
                                                        >
                                                            {penalty.employee
                                                                ?.name ?? '—'}
                                                        </div>
                                                        <div
                                                            style={{
                                                                fontSize: 12,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            {penalty.employee
                                                                ?.employee_number ??
                                                                '—'}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.muted,
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {penalty.date}
                                            </td>
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <span
                                                    style={{
                                                        display: 'inline-block',
                                                        padding: '3px 10px',
                                                        borderRadius: 100,
                                                        fontSize: 11.5,
                                                        fontWeight: 600,
                                                        color: vb.color,
                                                        background: vb.bg,
                                                    }}
                                                >
                                                    {vb.label}
                                                </span>
                                            </td>
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <span
                                                    style={{
                                                        display: 'inline-block',
                                                        padding: '3px 10px',
                                                        borderRadius: 100,
                                                        fontSize: 11.5,
                                                        fontWeight: 600,
                                                        color: pb.color,
                                                        background: pb.bg,
                                                    }}
                                                >
                                                    {pb.label}
                                                </span>
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color:
                                                        penalty.amount > 0
                                                            ? C.text
                                                            : C.faint,
                                                    fontWeight:
                                                        penalty.amount > 0
                                                            ? 600
                                                            : 400,
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {penalty.amount > 0
                                                    ? rp(penalty.amount)
                                                    : '—'}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.muted,
                                                    maxWidth: 240,
                                                }}
                                            >
                                                {penalty.notes ?? '—'}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 18px',
                                                    textAlign: 'right',
                                                }}
                                            >
                                                <button
                                                    title="Hapus"
                                                    onClick={() =>
                                                        setConfirm(penalty)
                                                    }
                                                    style={iconBtn}
                                                >
                                                    <AIcon
                                                        name="trash-2"
                                                        size={15}
                                                        color={C.red}
                                                    />
                                                </button>
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
                            </span>{' '}
                            sanksi
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
                                onClick={() => goToPage(meta.current_page - 1)}
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
                            {pageItems(meta.current_page, meta.last_page).map(
                                (item, index) =>
                                    item === 'gap' ? (
                                        <span
                                            key={`gap-${index}`}
                                            style={{
                                                color: C.faint,
                                                padding: '0 4px',
                                            }}
                                        >
                                            …
                                        </span>
                                    ) : (
                                        <button
                                            key={item}
                                            onClick={() => goToPage(item)}
                                            style={{
                                                height: 34,
                                                minWidth: 34,
                                                border:
                                                    item === meta.current_page
                                                        ? 'none'
                                                        : `1px solid ${C.border}`,
                                                background:
                                                    item === meta.current_page
                                                        ? C.primary
                                                        : '#fff',
                                                borderRadius: 8,
                                                fontSize: 13,
                                                color:
                                                    item === meta.current_page
                                                        ? '#fff'
                                                        : C.text,
                                                fontWeight:
                                                    item === meta.current_page
                                                        ? 600
                                                        : 400,
                                                cursor: 'pointer',
                                            }}
                                        >
                                            {item}
                                        </button>
                                    ),
                            )}
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

            {/* Create modal */}
            {modalOpen && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        zIndex: 80,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: 20,
                    }}
                >
                    <div
                        onClick={closeModal}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submitForm}
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
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Tambah Sanksi
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Catat sanksi pelanggaran kehadiran karyawan.
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            <div>
                                <label style={fieldLabelStyle}>
                                    Karyawan{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
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
                                    <option value="">Pilih karyawan…</option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={employee.id}
                                        >
                                            {employee.name} (
                                            {employee.employee_number})
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={form.errors.employee_id}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Tanggal{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="date"
                                    value={form.data.date}
                                    onChange={(event) =>
                                        form.setData('date', event.target.value)
                                    }
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.date,
                                    )}
                                />
                                <FieldError message={form.errors.date} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Pelanggaran{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={form.data.violation_type}
                                    onChange={(event) =>
                                        form.setData(
                                            'violation_type',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.violation_type,
                                    )}
                                >
                                    {violationOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={form.errors.violation_type}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Jenis Sanksi{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={form.data.penalty_type}
                                    onChange={(event) =>
                                        form.setData(
                                            'penalty_type',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.penalty_type,
                                    )}
                                >
                                    <option value="warning">Peringatan</option>
                                    <option value="deduction">Potongan</option>
                                </select>
                                <FieldError
                                    message={form.errors.penalty_type}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Nominal Potongan{' '}
                                    {form.data.penalty_type === 'deduction' && (
                                        <span style={{ color: C.red }}>*</span>
                                    )}
                                </label>
                                <input
                                    type="number"
                                    min={0}
                                    value={form.data.amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'amount',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="0"
                                    disabled={
                                        form.data.penalty_type !== 'deduction'
                                    }
                                    style={{
                                        ...withError(
                                            inputStyle,
                                            !!form.errors.amount,
                                        ),
                                        background:
                                            form.data.penalty_type !==
                                            'deduction'
                                                ? C.surface
                                                : '#fff',
                                    }}
                                />
                                <FieldError message={form.errors.amount} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>Catatan</label>
                                <textarea
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Keterangan tambahan…"
                                    rows={3}
                                    style={{
                                        ...withError(
                                            inputStyle,
                                            !!form.errors.notes,
                                        ),
                                        height: 'auto',
                                        padding: '11px 13px',
                                        resize: 'vertical',
                                        fontFamily: 'inherit',
                                    }}
                                />
                                <FieldError message={form.errors.notes} />
                            </div>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeModal}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: '#fff',
                                    color: C.text,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 500,
                                    cursor: 'pointer',
                                    transition: '.15s',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: C.primary,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 9,
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
                                <AIcon name="plus" size={16} color="#fff" />
                                Tambah
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Generate modal */}
            {generateOpen && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        zIndex: 80,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: 20,
                    }}
                >
                    <div
                        onClick={closeGenerate}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submitGenerate}
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 440,
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                            animation: 'toastIn .2s ease',
                        }}
                    >
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Generate dari Absensi
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Buat sanksi peringatan otomatis dari data keterlambatan
                            dan ketidakhadiran pada rentang tanggal.
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            <div>
                                <label style={fieldLabelStyle}>
                                    Tanggal Mulai{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="date"
                                    value={generateForm.data.start_date}
                                    onChange={(event) =>
                                        generateForm.setData(
                                            'start_date',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        inputStyle,
                                        !!generateForm.errors.start_date,
                                    )}
                                />
                                <FieldError
                                    message={generateForm.errors.start_date}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Tanggal Akhir{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="date"
                                    value={generateForm.data.end_date}
                                    onChange={(event) =>
                                        generateForm.setData(
                                            'end_date',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        inputStyle,
                                        !!generateForm.errors.end_date,
                                    )}
                                />
                                <FieldError
                                    message={generateForm.errors.end_date}
                                />
                            </div>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeGenerate}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: '#fff',
                                    color: C.text,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 500,
                                    cursor: 'pointer',
                                    transition: '.15s',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={generateForm.processing}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: C.primary,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 600,
                                    cursor: generateForm.processing
                                        ? 'not-allowed'
                                        : 'pointer',
                                    opacity: generateForm.processing ? 0.7 : 1,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon
                                    name="wand-sparkles"
                                    size={16}
                                    color="#fff"
                                />
                                Generate
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Confirm delete modal */}
            {confirm && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        zIndex: 80,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: 20,
                    }}
                >
                    <div
                        onClick={() => setConfirm(null)}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <div
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 400,
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                            animation: 'toastIn .2s ease',
                        }}
                    >
                        <div
                            style={{
                                width: 48,
                                height: 48,
                                borderRadius: 12,
                                background: 'rgba(220,38,38,.1)',
                                color: C.red,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                marginBottom: 16,
                            }}
                        >
                            <AIcon name="trash-2" size={22} color={C.red} />
                        </div>
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Hapus sanksi?
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 8,
                                lineHeight: 1.55,
                            }}
                        >
                            Sanksi untuk{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.employee?.name ?? 'karyawan ini'}
                            </strong>{' '}
                            akan dihapus permanen. Tindakan ini tidak dapat
                            dibatalkan.
                        </div>
                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                onClick={() => setConfirm(null)}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: '#fff',
                                    color: C.text,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 500,
                                    cursor: 'pointer',
                                    transition: '.15s',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                onClick={deletePenalty}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: C.red,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
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

const iconBtn: CSSProperties = {
    width: 32,
    height: 32,
    border: `1px solid ${C.border}`,
    background: '#fff',
    borderRadius: 8,
    cursor: 'pointer',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: '.15s',
};
