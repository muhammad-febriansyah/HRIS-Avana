import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import MovementController from '@/actions/App/Http/Controllers/Avana/MovementController';
import { AIcon, btnP, C, thCell } from '@/lib/avana';
import type { PaginationMeta } from './employees/types';

/** A single from→to change entry, as serialized by the controller. */
interface MovementChange {
    label: string;
    from: string | null;
    to: string | null;
}

/** A single career movement row as serialized by `MovementController@index`. */
interface MovementRow {
    id: number;
    employee: {
        name: string | null;
        employee_number: string | null;
    };
    movement_type: string;
    effective_date: string | null;
    changes: MovementChange[];
    employment_status: string | null;
    notes: string | null;
    created_at: string | null;
}

/** A selectable employee with its current placement, for prefilling the form. */
interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
    position_id: number | null;
    department_id: number | null;
    branch_id: number | null;
}

/** `{ id, name }` option used by the relation selects. */
interface NamedOption {
    id: number;
    name: string;
}

interface MutasiFilters {
    search?: string;
    movement_type?: string;
    per_page?: string;
}

interface MutasiProps {
    movements: {
        data: MovementRow[];
        meta: PaginationMeta;
    };
    employees: EmployeeOption[];
    positions: NamedOption[];
    departments: NamedOption[];
    branches: NamedOption[];
    filters: MutasiFilters;
}

/** Flat form payload backing the create movement form. */
interface MovementFormData {
    employee_id: string;
    movement_type: string;
    effective_date: string;
    position_id: string;
    department_id: string;
    branch_id: string;
    employment_status: string;
    notes: string;
}

const emptyForm: MovementFormData = {
    employee_id: '',
    movement_type: 'mutation',
    effective_date: '',
    position_id: '',
    department_id: '',
    branch_id: '',
    employment_status: '',
    notes: '',
};

/** Visual treatment per movement type, keyed by stored value. */
const movementMeta: Record<
    string,
    { label: string; color: string; bg: string }
> = {
    promotion: { label: 'Promosi', color: C.green, bg: 'rgba(22,163,74,.1)' },
    mutation: { label: 'Mutasi', color: C.primary, bg: 'rgba(47,84,201,.1)' },
    transfer: { label: 'Transfer', color: C.primary, bg: 'rgba(47,84,201,.1)' },
    demotion: { label: 'Demosi', color: C.amber, bg: 'rgba(217,119,6,.1)' },
    resign: { label: 'Resign', color: C.red, bg: 'rgba(220,38,38,.1)' },
    terminate: { label: 'Terminasi', color: C.red, bg: 'rgba(220,38,38,.1)' },
};

const movementOptions: { value: string; label: string }[] = [
    { value: 'mutation', label: 'Mutasi' },
    { value: 'promotion', label: 'Promosi' },
    { value: 'demotion', label: 'Demosi' },
    { value: 'transfer', label: 'Transfer' },
    { value: 'resign', label: 'Resign' },
    { value: 'terminate', label: 'Terminasi' },
];

const employmentStatusOptions: { value: string; label: string }[] = [
    { value: 'probation', label: 'Masa Percobaan' },
    { value: 'contract', label: 'Kontrak' },
    { value: 'permanent', label: 'Tetap' },
    { value: 'resigned', label: 'Resign' },
];

/** Success flash shape carried on every Inertia response. */
interface FlashPage {
    flash?: {
        success?: string;
    };
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

const textareaStyle: CSSProperties = {
    ...inputStyle,
    height: 'auto',
    minHeight: 78,
    padding: '11px 13px',
    resize: 'vertical',
    fontFamily: 'inherit',
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

/** Format an ISO date string to a short Indonesian date. */
function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

/** Badge metadata for a movement type, with a neutral fallback. */
function movementBadge(type: string) {
    return (
        movementMeta[type] ?? {
            label: type,
            color: C.muted,
            bg: 'rgba(107,114,128,.12)',
        }
    );
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

export default function AvanaMutasi({
    movements,
    employees,
    positions,
    departments,
    branches,
    filters,
}: MutasiProps) {
    const { flash } = usePage<FlashPage>().props;
    const meta = movements.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const isFirstSearch = useRef(true);

    const form = useForm<MovementFormData>({ ...emptyForm });

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

    /** Select an employee and prefill its current placement into the form. */
    const selectEmployee = (value: string) => {
        const employee = employees.find((item) => String(item.id) === value);

        form.setData((current) => ({
            ...current,
            employee_id: value,
            position_id: employee?.position_id
                ? String(employee.position_id)
                : '',
            department_id: employee?.department_id
                ? String(employee.department_id)
                : '',
            branch_id: employee?.branch_id ? String(employee.branch_id) : '',
        }));
    };

    const submitForm = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(MovementController.store(), {
            onSuccess: () => closeModal(),
        });
    };

    return (
        <>
            <Head title="Mutasi & Karir" />
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
                                Mutasi & Karir
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
                            Mutasi & Karir
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Catat mutasi, promosi &amp; resign serta riwayat
                            karir karyawan
                        </div>
                    </div>
                    <button onClick={openCreate} style={btnP}>
                        <AIcon name="plus" size={16} />
                        Buat Mutasi
                    </button>
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
                                placeholder="Cari nama karyawan…"
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
                            aria-label="Jenis Mutasi"
                            value={filters.movement_type ?? ''}
                            onChange={(event) =>
                                applyFilter('movement_type', event.target.value)
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Jenis</option>
                            {movementOptions.map((option) => (
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
                                minWidth: 820,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Karyawan</th>
                                    <th style={thCell}>Jenis</th>
                                    <th style={thCell}>Tanggal Efektif</th>
                                    <th style={thCell}>Perubahan</th>
                                    <th style={thCell}>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {movements.data.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={5}
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
                                                    name="route"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Belum ada riwayat mutasi.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {movements.data.map((movement) => {
                                    const badge = movementBadge(
                                        movement.movement_type,
                                    );

                                    return (
                                        <tr
                                            key={movement.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <div
                                                    style={{
                                                        fontSize: 13.5,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {movement.employee.name ??
                                                        '—'}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {movement.employee
                                                        .employee_number ?? '—'}
                                                </div>
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
                                                        color: badge.color,
                                                        background: badge.bg,
                                                    }}
                                                >
                                                    {badge.label}
                                                </span>
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.muted,
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {formatDate(
                                                    movement.effective_date,
                                                )}
                                            </td>
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                {movement.changes.length ===
                                                0 ? (
                                                    <span
                                                        style={{
                                                            fontSize: 13,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        —
                                                    </span>
                                                ) : (
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            flexDirection:
                                                                'column',
                                                            gap: 5,
                                                        }}
                                                    >
                                                        {movement.changes.map(
                                                            (change, index) => (
                                                                <div
                                                                    key={`${movement.id}-${index}`}
                                                                    style={{
                                                                        display:
                                                                            'flex',
                                                                        alignItems:
                                                                            'center',
                                                                        flexWrap:
                                                                            'wrap',
                                                                        gap: 6,
                                                                        fontSize: 12,
                                                                    }}
                                                                >
                                                                    <span
                                                                        style={{
                                                                            color: C.faint,
                                                                            minWidth: 64,
                                                                        }}
                                                                    >
                                                                        {
                                                                            change.label
                                                                        }
                                                                    </span>
                                                                    <span
                                                                        style={chipStyle(
                                                                            C.muted,
                                                                            C.surface,
                                                                        )}
                                                                    >
                                                                        {change.from ??
                                                                            '—'}
                                                                    </span>
                                                                    <AIcon
                                                                        name="arrow-right"
                                                                        size={
                                                                            13
                                                                        }
                                                                        color={
                                                                            C.faint
                                                                        }
                                                                    />
                                                                    <span
                                                                        style={chipStyle(
                                                                            C.primary,
                                                                            'rgba(47,84,201,.1)',
                                                                        )}
                                                                    >
                                                                        {change.to ??
                                                                            '—'}
                                                                    </span>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.muted,
                                                    maxWidth: 220,
                                                }}
                                            >
                                                {movement.notes ?? '—'}
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
                            mutasi
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

            {/* Create movement modal */}
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
                            Buat Mutasi
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Pilih karyawan lalu tentukan perubahan penempatan
                            atau status karirnya.
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
                                        selectEmployee(event.target.value)
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
                                <FieldError message={form.errors.employee_id} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Jenis Mutasi{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={form.data.movement_type}
                                    onChange={(event) =>
                                        form.setData(
                                            'movement_type',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.movement_type,
                                    )}
                                >
                                    {movementOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={form.errors.movement_type}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Tanggal Efektif{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="date"
                                    value={form.data.effective_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'effective_date',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.effective_date,
                                    )}
                                />
                                <FieldError
                                    message={form.errors.effective_date}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Posisi Baru
                                </label>
                                <select
                                    value={form.data.position_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'position_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.position_id,
                                    )}
                                >
                                    <option value="">Tidak berubah</option>
                                    {positions.map((position) => (
                                        <option
                                            key={position.id}
                                            value={position.id}
                                        >
                                            {position.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.position_id} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Departemen Baru
                                </label>
                                <select
                                    value={form.data.department_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'department_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.department_id,
                                    )}
                                >
                                    <option value="">Tidak berubah</option>
                                    {departments.map((department) => (
                                        <option
                                            key={department.id}
                                            value={department.id}
                                        >
                                            {department.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={form.errors.department_id}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Cabang Baru
                                </label>
                                <select
                                    value={form.data.branch_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'branch_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.branch_id,
                                    )}
                                >
                                    <option value="">Tidak berubah</option>
                                    {branches.map((branch) => (
                                        <option
                                            key={branch.id}
                                            value={branch.id}
                                        >
                                            {branch.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.branch_id} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Status Kepegawaian
                                </label>
                                <select
                                    value={form.data.employment_status}
                                    onChange={(event) =>
                                        form.setData(
                                            'employment_status',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.employment_status,
                                    )}
                                >
                                    <option value="">Tidak berubah</option>
                                    {employmentStatusOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={form.errors.employment_status}
                                />
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
                                    placeholder="Alasan atau keterangan mutasi…"
                                    style={withError(
                                        textareaStyle,
                                        !!form.errors.notes,
                                    )}
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
                                <AIcon name="check" size={16} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </>
    );
}

/** Pill style for a from/to value chip. */
function chipStyle(color: string, background: string): CSSProperties {
    return {
        display: 'inline-block',
        padding: '2px 9px',
        borderRadius: 100,
        fontSize: 11.5,
        fontWeight: 500,
        color,
        background,
    };
}
