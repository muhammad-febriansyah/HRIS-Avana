import { Head, Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import EmployeeController from '@/actions/App/Http/Controllers/Avana/EmployeeController';
import { AIcon, btnOut, btnP, C, statusBadge, thCell } from '@/lib/avana';
import type {
    Employee,
    FlashProps,
    NamedOption,
    PaginationMeta,
} from './types';

interface EmployeesIndexProps {
    employees: {
        data: Employee[];
        meta: PaginationMeta;
    };
    filters: Record<string, string | undefined>;
    branches: NamedOption[];
    departments: NamedOption[];
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

const sortHeaderButtonStyle: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 5,
    background: 'none',
    border: 'none',
    padding: 0,
    cursor: 'pointer',
    color: C.faint,
    fontSize: 11.5,
    fontWeight: 600,
    letterSpacing: '.04em',
    textTransform: 'uppercase',
};

const employmentTypes: { value: string; label: string }[] = [
    { value: 'probation', label: 'Masa Percobaan' },
    { value: 'contract', label: 'Kontrak' },
    { value: 'permanent', label: 'Tetap' },
    { value: 'resigned', label: 'Resign' },
];

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

export default function EmployeesIndex({
    employees,
    filters,
    branches,
    departments,
}: EmployeesIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = employees.meta;

    const [openMenu, setOpenMenu] = useState<number | null>(null);
    const [confirm, setConfirm] = useState<Employee | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstSearch = useRef(true);

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

    const toggleSort = (column: string) => {
        const direction =
            filters.sort === column && filters.direction === 'asc'
                ? 'desc'
                : 'asc';

        router.get(
            window.location.pathname,
            { ...filters, sort: column, direction, page: 1 },
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

    const deleteEmployee = () => {
        if (!confirm) {
            return;
        }

        router.delete(EmployeeController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    const sortIcon = (column: string) => {
        if (filters.sort !== column) {
            return (
                <AIcon
                    name="chevrons-up-down"
                    size={13}
                    color={C.faint}
                    style={{ opacity: 0.55 }}
                />
            );
        }

        return (
            <AIcon
                name={
                    filters.direction === 'asc' ? 'chevron-up' : 'chevron-down'
                }
                size={13}
                color={C.primary}
            />
        );
    };

    return (
        <>
            <Head title="Karyawan" />
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
                            <span>Beranda</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Karyawan</span>
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
                            Karyawan
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            {meta.total.toLocaleString('id-ID')} karyawan
                            terdaftar
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <a
                            href="/avana/laporan/export/karyawan"
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="download" size={16} />
                            Export
                        </a>
                        <Link
                            href={EmployeeController.create()}
                            style={{ ...btnP, textDecoration: 'none' }}
                        >
                            <AIcon name="plus" size={16} />
                            Tambah Karyawan
                        </Link>
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
                                placeholder="Cari nama atau email…"
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
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                        <select
                            aria-label="Tipe"
                            value={filters.employment_status ?? ''}
                            onChange={(event) =>
                                applyFilter(
                                    'employment_status',
                                    event.target.value,
                                )
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Tipe</option>
                            {employmentTypes.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                        <select
                            aria-label="Departemen"
                            value={filters.department_id ?? ''}
                            onChange={(event) =>
                                applyFilter('department_id', event.target.value)
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Departemen</option>
                            {departments.map((department) => (
                                <option
                                    key={department.id}
                                    value={String(department.id)}
                                >
                                    {department.name}
                                </option>
                            ))}
                        </select>
                        <select
                            aria-label="Cabang"
                            value={filters.branch_id ?? ''}
                            onChange={(event) =>
                                applyFilter('branch_id', event.target.value)
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Cabang</option>
                            {branches.map((branch) => (
                                <option
                                    key={branch.id}
                                    value={String(branch.id)}
                                >
                                    {branch.name}
                                </option>
                            ))}
                        </select>
                        <div style={{ flex: 1 }} />
                        <button
                            style={{
                                height: 38,
                                padding: '0 13px',
                                border: `1px solid ${C.border}`,
                                borderRadius: 8,
                                fontSize: 13,
                                color: C.muted,
                                background: '#fff',
                                cursor: 'pointer',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 7,
                                transition: '.15s',
                            }}
                        >
                            <AIcon name="sliders-horizontal" size={15} />
                            Kolom
                        </button>
                    </div>

                    {/* Table */}
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 840,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th
                                        style={{
                                            width: 44,
                                            padding: '12px 0 12px 18px',
                                            textAlign: 'left',
                                        }}
                                    >
                                        <input
                                            type="checkbox"
                                            style={{
                                                width: 15,
                                                height: 15,
                                                accentColor: C.primary,
                                            }}
                                        />
                                    </th>
                                    <th style={thCell}>
                                        <button
                                            onClick={() =>
                                                toggleSort('full_name')
                                            }
                                            style={sortHeaderButtonStyle}
                                        >
                                            Karyawan
                                            {sortIcon('full_name')}
                                        </button>
                                    </th>
                                    <th style={thCell}>Departemen</th>
                                    <th style={thCell}>Jabatan</th>
                                    <th style={thCell}>Status</th>
                                    <th style={thCell}>
                                        <button
                                            onClick={() =>
                                                toggleSort('join_date')
                                            }
                                            style={sortHeaderButtonStyle}
                                        >
                                            Tgl Masuk
                                            {sortIcon('join_date')}
                                        </button>
                                    </th>
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
                                {employees.data.length === 0 && (
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
                                                    name="users"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Tidak ada karyawan yang
                                                    ditemukan.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {employees.data.map((e) => {
                                    const badge = statusBadge(
                                        e.employment_label,
                                    );

                                    return (
                                        <tr
                                            key={e.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                                transition: 'background .15s',
                                            }}
                                        >
                                            <td
                                                style={{
                                                    padding: '13px 0 13px 18px',
                                                }}
                                            >
                                                <input
                                                    type="checkbox"
                                                    style={{
                                                        width: 15,
                                                        height: 15,
                                                        accentColor: C.primary,
                                                    }}
                                                />
                                            </td>
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
                                                                e.avatar_color,
                                                            color: '#fff',
                                                            display: 'flex',
                                                            alignItems:
                                                                'center',
                                                            justifyContent:
                                                                'center',
                                                            fontSize: 12.5,
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        {e.initials}
                                                    </div>
                                                    <div
                                                        style={{ minWidth: 0 }}
                                                    >
                                                        <div
                                                            style={{
                                                                fontSize: 13.5,
                                                                fontWeight: 600,
                                                                color: C.navy,
                                                            }}
                                                        >
                                                            {e.full_name}
                                                        </div>
                                                        <div
                                                            style={{
                                                                fontSize: 12,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            {e.email ?? '—'}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.text,
                                                }}
                                            >
                                                {e.department?.name ?? '—'}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.muted,
                                                }}
                                            >
                                                {e.position?.name ?? '—'}
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
                                                }}
                                            >
                                                {e.join_date ?? '—'}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 18px',
                                                    textAlign: 'right',
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        position: 'relative',
                                                        display: 'inline-block',
                                                    }}
                                                >
                                                    <button
                                                        onClick={() =>
                                                            setOpenMenu(
                                                                (prev) =>
                                                                    prev ===
                                                                    e.id
                                                                        ? null
                                                                        : e.id,
                                                            )
                                                        }
                                                        style={{
                                                            width: 32,
                                                            height: 32,
                                                            border: `1px solid ${C.border}`,
                                                            background: '#fff',
                                                            borderRadius: 8,
                                                            cursor: 'pointer',
                                                            color: C.muted,
                                                            display:
                                                                'inline-flex',
                                                            alignItems:
                                                                'center',
                                                            justifyContent:
                                                                'center',
                                                            transition: '.15s',
                                                        }}
                                                    >
                                                        <AIcon
                                                            name="ellipsis-vertical"
                                                            size={16}
                                                        />
                                                    </button>
                                                    <div
                                                        style={{
                                                            display:
                                                                openMenu ===
                                                                e.id
                                                                    ? 'block'
                                                                    : 'none',
                                                            position:
                                                                'absolute',
                                                            right: 0,
                                                            top: 38,
                                                            width: 148,
                                                            background: '#fff',
                                                            border: `1px solid ${C.border}`,
                                                            borderRadius: 10,
                                                            boxShadow:
                                                                '0 8px 24px rgba(15,23,42,.12)',
                                                            zIndex: 20,
                                                            padding: 5,
                                                            textAlign: 'left',
                                                        }}
                                                    >
                                                        <Link
                                                            href={EmployeeController.show(
                                                                e.id,
                                                            )}
                                                            style={{
                                                                width: '100%',
                                                                display: 'flex',
                                                                alignItems:
                                                                    'center',
                                                                gap: 9,
                                                                padding:
                                                                    '8px 10px',
                                                                border: 'none',
                                                                background:
                                                                    'none',
                                                                borderRadius: 7,
                                                                fontSize: 13,
                                                                color: C.text,
                                                                cursor: 'pointer',
                                                                transition:
                                                                    '.12s',
                                                                textDecoration:
                                                                    'none',
                                                            }}
                                                        >
                                                            <AIcon
                                                                name="eye"
                                                                size={15}
                                                                color={C.muted}
                                                            />
                                                            Lihat
                                                        </Link>
                                                        <Link
                                                            href={EmployeeController.edit(
                                                                e.id,
                                                            )}
                                                            style={{
                                                                width: '100%',
                                                                display: 'flex',
                                                                alignItems:
                                                                    'center',
                                                                gap: 9,
                                                                padding:
                                                                    '8px 10px',
                                                                border: 'none',
                                                                background:
                                                                    'none',
                                                                borderRadius: 7,
                                                                fontSize: 13,
                                                                color: C.text,
                                                                cursor: 'pointer',
                                                                transition:
                                                                    '.12s',
                                                                textDecoration:
                                                                    'none',
                                                            }}
                                                        >
                                                            <AIcon
                                                                name="pencil"
                                                                size={15}
                                                                color={C.muted}
                                                            />
                                                            Ubah
                                                        </Link>
                                                        <button
                                                            onClick={() => {
                                                                setConfirm(e);
                                                                setOpenMenu(
                                                                    null,
                                                                );
                                                            }}
                                                            style={{
                                                                width: '100%',
                                                                display: 'flex',
                                                                alignItems:
                                                                    'center',
                                                                gap: 9,
                                                                padding:
                                                                    '8px 10px',
                                                                border: 'none',
                                                                background:
                                                                    'none',
                                                                borderRadius: 7,
                                                                fontSize: 13,
                                                                color: C.red,
                                                                cursor: 'pointer',
                                                                transition:
                                                                    '.12s',
                                                            }}
                                                        >
                                                            <AIcon
                                                                name="trash-2"
                                                                size={15}
                                                                color={C.red}
                                                            />
                                                            Hapus
                                                        </button>
                                                    </div>
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
                            karyawan
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
                            Hapus karyawan?
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 8,
                                lineHeight: 1.55,
                            }}
                        >
                            Data{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.full_name}
                            </strong>{' '}
                            akan dihapus beserta seluruh riwayatnya. Tindakan
                            ini tidak dapat dibatalkan.
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
                                onClick={deleteEmployee}
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
