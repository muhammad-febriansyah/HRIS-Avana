import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import ContractController from '@/actions/App/Http/Controllers/Avana/ContractController';
import { AIcon, btnP, C, rp, thCell } from '@/lib/avana';
import type { FlashProps, PaginationMeta } from './employees/types';

/** An employee selectable in the contract create/edit form. */
interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

/** Compact employee reference serialized on a contract row. */
interface ContractEmployee {
    name: string;
    employee_number: string;
}

/** A single contract row as serialized by `ContractController@index`. */
interface ContractRow {
    id: number;
    contract_number: string;
    employee: ContractEmployee | null;
    employee_id: number;
    contract_type: string;
    start_date: string | null;
    end_date: string | null;
    basic_salary: number;
    status: string;
    notes: string | null;
    expiring_soon: boolean;
    days_to_expiry: number | null;
}

/** Header strip counters serialized by the controller. */
interface KontrakStats {
    total: number;
    active: number;
    expiring_soon: number;
}

interface KontrakFilters {
    search?: string;
    status?: string;
    per_page?: string;
}

interface KontrakProps {
    contracts: {
        data: ContractRow[];
        meta: PaginationMeta;
    };
    employees: EmployeeOption[];
    stats: KontrakStats;
    filters: KontrakFilters;
}

/** Flat form payload backing both the create and edit contract forms. */
interface ContractFormData {
    employee_id: string;
    contract_number: string;
    contract_type: string;
    start_date: string;
    end_date: string;
    basic_salary: string;
    status: string;
    notes: string;
}

const emptyForm: ContractFormData = {
    employee_id: '',
    contract_number: '',
    contract_type: 'pkwt',
    start_date: '',
    end_date: '',
    basic_salary: '',
    status: 'active',
    notes: '',
};

/** Contract type options surfaced in the create/edit modal. */
const contractTypeOptions: { value: string; label: string }[] = [
    { value: 'pkwt', label: 'PKWT (Kontrak)' },
    { value: 'pkwtt', label: 'PKWTT (Tetap)' },
    { value: 'probation', label: 'Probation' },
];

/** Status options surfaced in the create/edit modal and filter bar. */
const statusOptions: { value: string; label: string }[] = [
    { value: 'active', label: 'Aktif' },
    { value: 'expired', label: 'Berakhir' },
    { value: 'terminated', label: 'Diberhentikan' },
];

/** Short label for a contract type code. */
function contractTypeLabel(type: string): string {
    return (
        contractTypeOptions.find((option) => option.value === type)?.label ??
        type.toUpperCase()
    );
}

/** Status pill colors for a contract status. */
function statusPill(status: string) {
    switch (status) {
        case 'active':
            return { label: 'Aktif', color: C.green, bg: 'rgba(22,163,74,.1)' };
        case 'expired':
            return {
                label: 'Berakhir',
                color: C.muted,
                bg: 'rgba(107,114,128,.12)',
            };
        case 'terminated':
            return {
                label: 'Diberhentikan',
                color: C.red,
                bg: 'rgba(220,38,38,.1)',
            };
        default:
            return {
                label: status,
                color: C.muted,
                bg: 'rgba(107,114,128,.12)',
            };
    }
}

/** Format an ISO date string to Indonesian short form. */
function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
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

/** A single counter in the header stat strip. */
function StatCard({
    label,
    value,
    color,
    icon,
}: {
    label: string;
    value: number;
    color: string;
    icon: string;
}) {
    return (
        <div
            style={{
                flex: 1,
                minWidth: 160,
                background: '#fff',
                border: `1px solid ${C.border}`,
                borderRadius: 12,
                boxShadow: '0 1px 2px rgba(15,23,42,.04)',
                padding: '16px 18px',
                display: 'flex',
                alignItems: 'center',
                gap: 13,
            }}
        >
            <div
                style={{
                    width: 40,
                    height: 40,
                    borderRadius: 10,
                    flex: 'none',
                    background: `${color}1a`,
                    color,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                }}
            >
                <AIcon name={icon} size={19} color={color} />
            </div>
            <div>
                <div
                    style={{
                        fontSize: 22,
                        fontWeight: 700,
                        color: C.navy,
                        lineHeight: 1.1,
                    }}
                >
                    {value.toLocaleString('id-ID')}
                </div>
                <div style={{ fontSize: 12.5, color: C.muted, marginTop: 2 }}>
                    {label}
                </div>
            </div>
        </div>
    );
}

export default function AvanaKontrak({
    contracts,
    employees,
    stats,
    filters,
}: KontrakProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = contracts.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<ContractRow | null>(null);
    const [confirm, setConfirm] = useState<ContractRow | null>(null);
    const isFirstSearch = useRef(true);

    const form = useForm<ContractFormData>({ ...emptyForm });

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
        setEditing(null);
        form.clearErrors();
        form.setData({ ...emptyForm });
        setModalOpen(true);
    };

    const openEdit = (contract: ContractRow) => {
        setEditing(contract);
        form.clearErrors();
        form.setData({
            employee_id: String(contract.employee_id),
            contract_number: contract.contract_number,
            contract_type: contract.contract_type,
            start_date: contract.start_date ?? '',
            end_date: contract.end_date ?? '',
            basic_salary: String(contract.basic_salary),
            status: contract.status,
            notes: contract.notes ?? '',
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const submitForm = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = { onSuccess: () => closeModal() };

        if (editing) {
            form.submit(ContractController.update(editing.id), options);
        } else {
            form.submit(ContractController.store(), options);
        }
    };

    const deleteContract = () => {
        if (!confirm) {
            return;
        }

        router.delete(ContractController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Kontrak Kerja" />
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
                            <span style={{ color: C.muted }}>Kontrak Kerja</span>
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
                            Kontrak Kerja
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola kontrak kerja karyawan &amp; pantau masa
                            berlakunya
                        </div>
                    </div>
                    <button onClick={openCreate} style={btnP}>
                        <AIcon name="plus" size={16} />
                        Tambah Kontrak
                    </button>
                </div>

                {/* Stat strip */}
                <div
                    style={{
                        display: 'flex',
                        gap: 14,
                        flexWrap: 'wrap',
                        marginBottom: 20,
                    }}
                >
                    <StatCard
                        label="Total Kontrak"
                        value={stats.total}
                        color={C.primary}
                        icon="file-text"
                    />
                    <StatCard
                        label="Kontrak Aktif"
                        value={stats.active}
                        color={C.green}
                        icon="circle-check"
                    />
                    <StatCard
                        label="Akan Berakhir ≤30 hari"
                        value={stats.expiring_soon}
                        color={C.amber}
                        icon="clock-alert"
                    />
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
                                placeholder="Cari no kontrak atau karyawan…"
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
                            aria-label="Status"
                            value={filters.status ?? ''}
                            onChange={(event) =>
                                applyFilter('status', event.target.value)
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Status</option>
                            {statusOptions.map((option) => (
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
                                    <th style={thCell}>No Kontrak</th>
                                    <th style={thCell}>Jenis</th>
                                    <th style={thCell}>Periode</th>
                                    <th style={thCell}>Gaji Pokok</th>
                                    <th style={thCell}>Status</th>
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
                                {contracts.data.length === 0 && (
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
                                                    name="file-text"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Tidak ada kontrak yang
                                                    ditemukan.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {contracts.data.map((contract) => {
                                    const pill = statusPill(contract.status);

                                    return (
                                        <tr
                                            key={contract.id}
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
                                                        fontSize: 13.5,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {contract.employee?.name ??
                                                        '—'}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {contract.employee
                                                        ?.employee_number ?? '—'}
                                                </div>
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.text,
                                                    fontWeight: 500,
                                                }}
                                            >
                                                {contract.contract_number}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.muted,
                                                }}
                                            >
                                                {contractTypeLabel(
                                                    contract.contract_type,
                                                )}
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
                                                    contract.start_date,
                                                )}{' '}
                                                –{' '}
                                                {contract.end_date
                                                    ? formatDate(
                                                          contract.end_date,
                                                      )
                                                    : 'Tetap'}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.text,
                                                    fontWeight: 500,
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {rp(contract.basic_salary)}
                                            </td>
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        flexDirection: 'column',
                                                        gap: 5,
                                                        alignItems:
                                                            'flex-start',
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            display:
                                                                'inline-block',
                                                            padding: '3px 10px',
                                                            borderRadius: 100,
                                                            fontSize: 11.5,
                                                            fontWeight: 600,
                                                            color: pill.color,
                                                            background: pill.bg,
                                                        }}
                                                    >
                                                        {pill.label}
                                                    </span>
                                                    {contract.expiring_soon && (
                                                        <span
                                                            style={{
                                                                display:
                                                                    'inline-flex',
                                                                alignItems:
                                                                    'center',
                                                                gap: 4,
                                                                padding:
                                                                    '3px 9px',
                                                                borderRadius: 100,
                                                                fontSize: 11,
                                                                fontWeight: 600,
                                                                color: C.red,
                                                                background:
                                                                    'rgba(220,38,38,.1)',
                                                            }}
                                                        >
                                                            <AIcon
                                                                name="triangle-alert"
                                                                size={11}
                                                                color={C.red}
                                                            />
                                                            Akan berakhir (
                                                            {
                                                                contract.days_to_expiry
                                                            }{' '}
                                                            hari)
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 18px',
                                                    textAlign: 'right',
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        display: 'inline-flex',
                                                        gap: 6,
                                                    }}
                                                >
                                                    <button
                                                        title="Ubah"
                                                        onClick={() =>
                                                            openEdit(contract)
                                                        }
                                                        style={iconBtn}
                                                    >
                                                        <AIcon
                                                            name="pencil"
                                                            size={15}
                                                            color={C.muted}
                                                        />
                                                    </button>
                                                    <button
                                                        title="Hapus"
                                                        onClick={() =>
                                                            setConfirm(contract)
                                                        }
                                                        style={iconBtn}
                                                    >
                                                        <AIcon
                                                            name="trash-2"
                                                            size={15}
                                                            color={C.red}
                                                        />
                                                    </button>
                                                </div>
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
                            kontrak
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

            {/* Create / edit modal */}
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
                            {editing ? 'Ubah Kontrak' : 'Tambah Kontrak'}
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Lengkapi detail kontrak kerja karyawan.
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
                                    No Kontrak{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.contract_number}
                                    onChange={(event) =>
                                        form.setData(
                                            'contract_number',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="No. PKWT-2026-001"
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.contract_number,
                                    )}
                                />
                                <FieldError
                                    message={form.errors.contract_number}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Jenis Kontrak{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={form.data.contract_type}
                                    onChange={(event) =>
                                        form.setData(
                                            'contract_type',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.contract_type,
                                    )}
                                >
                                    {contractTypeOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={form.errors.contract_type}
                                />
                            </div>

                            <div style={{ display: 'flex', gap: 12 }}>
                                <div style={{ flex: 1 }}>
                                    <label style={fieldLabelStyle}>
                                        Mulai{' '}
                                        <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input
                                        type="date"
                                        value={form.data.start_date}
                                        onChange={(event) =>
                                            form.setData(
                                                'start_date',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.start_date,
                                        )}
                                    />
                                    <FieldError
                                        message={form.errors.start_date}
                                    />
                                </div>
                                <div style={{ flex: 1 }}>
                                    <label style={fieldLabelStyle}>
                                        Selesai
                                    </label>
                                    <input
                                        type="date"
                                        value={form.data.end_date}
                                        onChange={(event) =>
                                            form.setData(
                                                'end_date',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.end_date,
                                        )}
                                    />
                                    <FieldError
                                        message={form.errors.end_date}
                                    />
                                </div>
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Gaji Pokok{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={form.data.basic_salary}
                                    onChange={(event) =>
                                        form.setData(
                                            'basic_salary',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Rupiah, mis. 7500000"
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.basic_salary,
                                    )}
                                />
                                <FieldError
                                    message={form.errors.basic_salary}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Status{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setData(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.status,
                                    )}
                                >
                                    {statusOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.status} />
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
                                    placeholder="Catatan tambahan (opsional)"
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
                                <AIcon
                                    name={editing ? 'check' : 'plus'}
                                    size={16}
                                    color="#fff"
                                />
                                {editing ? 'Simpan' : 'Tambah'}
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
                            Hapus kontrak?
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 8,
                                lineHeight: 1.55,
                            }}
                        >
                            Kontrak{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.contract_number}
                            </strong>{' '}
                            akan dihapus. Tindakan ini tidak dapat dibatalkan.
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
                                onClick={deleteContract}
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
