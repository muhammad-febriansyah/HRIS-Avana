import { Head, Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import EmployeeController from '@/actions/App/Http/Controllers/Avana/EmployeeController';
import {
    ActionBtn,
    AIcon,
    btnOut,
    btnP,
    C,
    statusBadge,
    thCell,
} from '@/lib/avana';
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
    /** "1 perangkat 1 akun" — with it off there is no binding to reset. */
    device_binding_enabled: boolean;
    /** Tenant accounts with no employee behind them, offered by "Tautkan Akun". */
    linkableUsers: LinkableUser[];
}

type LinkableUser = {
    id: number;
    name: string;
    email: string;
    roles: string;
};

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
    device_binding_enabled,
    linkableUsers,
}: EmployeesIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = employees.meta;

    const [confirm, setConfirm] = useState<Employee | null>(null);
    const [resetTarget, setResetTarget] = useState<Employee | null>(null);
    const [linkTarget, setLinkTarget] = useState<Employee | null>(null);
    const [linkUserId, setLinkUserId] = useState('');
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [bulkConfirm, setBulkConfirm] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstSearch = useRef(true);
    const headerCheckbox = useRef<HTMLInputElement>(null);

    const pageIds = employees.data.map((row) => row.id);
    const allPageSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.has(id));
    const somePageSelected = pageIds.some((id) => selected.has(id));

    useEffect(() => {
        if (headerCheckbox.current) {
            headerCheckbox.current.indeterminate =
                somePageSelected && !allPageSelected;
        }
    }, [somePageSelected, allPageSelected]);

    const toggleAllPage = () => {
        setSelected((prev) => {
            const next = new Set(prev);

            if (allPageSelected) {
                pageIds.forEach((id) => next.delete(id));
            } else {
                pageIds.forEach((id) => next.add(id));
            }

            return next;
        });
    };

    const toggleOne = (id: number) => {
        setSelected((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const bulkDelete = () => {
        router.delete(EmployeeController.bulkDestroy().url, {
            data: { ids: Array.from(selected) },
            preserveScroll: true,
            onSuccess: () => {
                setSelected(new Set());
                setBulkConfirm(false);
            },
        });
    };

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    useEffect(() => {
        if (flash?.error) {
            toast.error(flash.error, { id: flash.error });
        }
    }, [flash?.error]);

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

        router.delete(EmployeeController.destroy(confirm.route_key).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    /**
     * Unbind the employee's phone so they can sign in from a new one. Only
     * offered while a device is actually bound.
     */
    const resetDevice = () => {
        if (!resetTarget) {
            return;
        }

        router.post(
            EmployeeController.resetDevice(resetTarget.route_key).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setResetTarget(null),
            },
        );
    };

    /** Attach the picked login account to the employee behind the modal. */
    const linkAccount = () => {
        if (!linkTarget || !linkUserId) {
            return;
        }

        router.post(
            EmployeeController.linkAccount(linkTarget.route_key).url,
            { user_id: Number(linkUserId) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setLinkTarget(null);
                    setLinkUserId('');
                },
            },
        );
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
                            href="/avana/employees/bulk"
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="users" size={16} />
                            Tambah Massal
                        </Link>
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
                        <select
                            aria-label="Akun Login"
                            value={filters.akun ?? ''}
                            onChange={(event) =>
                                applyFilter('akun', event.target.value)
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Akun</option>
                            <option value="tanpa">Belum ada akun</option>
                        </select>
                        <div style={{ flex: 1 }} />
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
                                            ref={headerCheckbox}
                                            type="checkbox"
                                            checked={allPageSelected}
                                            onChange={toggleAllPage}
                                            aria-label="Pilih semua"
                                            style={{
                                                width: 15,
                                                height: 15,
                                                accentColor: C.primary,
                                                cursor: 'pointer',
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
                                                    checked={selected.has(e.id)}
                                                    onChange={() =>
                                                        toggleOne(e.id)
                                                    }
                                                    aria-label={`Pilih ${e.full_name}`}
                                                    style={{
                                                        width: 15,
                                                        height: 15,
                                                        accentColor: C.primary,
                                                        cursor: 'pointer',
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
                                                    {e.photo_url ? (
                                                        <img
                                                            src={e.photo_url}
                                                            alt={e.full_name}
                                                            style={{
                                                                width: 36,
                                                                height: 36,
                                                                borderRadius:
                                                                    '50%',
                                                                objectFit:
                                                                    'cover',
                                                                flex: 'none',
                                                            }}
                                                        />
                                                    ) : (
                                                        <div
                                                            style={{
                                                                width: 36,
                                                                height: 36,
                                                                borderRadius:
                                                                    '50%',
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
                                                    )}
                                                    <div
                                                        style={{ minWidth: 0 }}
                                                    >
                                                        <div
                                                            style={{
                                                                display: 'flex',
                                                                alignItems:
                                                                    'center',
                                                                gap: 7,
                                                                fontSize: 13.5,
                                                                fontWeight: 600,
                                                                color: C.navy,
                                                            }}
                                                        >
                                                            {e.full_name}
                                                            {!e.has_login && (
                                                                <span
                                                                    title="Belum punya akun login — tidak bisa masuk aplikasi mobile"
                                                                    style={{
                                                                        padding:
                                                                            '2px 8px',
                                                                        borderRadius: 100,
                                                                        fontSize: 10.5,
                                                                        fontWeight: 600,
                                                                        color: C.amber,
                                                                        background:
                                                                            'rgba(217,119,6,.1)',
                                                                        whiteSpace:
                                                                            'nowrap',
                                                                    }}
                                                                >
                                                                    Belum ada
                                                                    akun
                                                                </span>
                                                            )}
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
                                                        display: 'inline-flex',
                                                        gap: 6,
                                                        justifyContent:
                                                            'flex-end',
                                                        flexWrap: 'wrap',
                                                    }}
                                                >
                                                    <ActionBtn
                                                        icon="eye"
                                                        label="Lihat"
                                                        variant="warning"
                                                        onClick={() =>
                                                            router.visit(
                                                                EmployeeController.show(
                                                                    e.route_key,
                                                                ).url,
                                                            )
                                                        }
                                                    />
                                                    <ActionBtn
                                                        icon="pencil"
                                                        label="Ubah"
                                                        variant="success"
                                                        onClick={() =>
                                                            router.visit(
                                                                EmployeeController.edit(
                                                                    e.route_key,
                                                                ).url,
                                                            )
                                                        }
                                                    />
                                                    {!e.has_login &&
                                                        linkableUsers.length >
                                                            0 && (
                                                            <ActionBtn
                                                                icon="link"
                                                                label="Tautkan"
                                                                variant="primary"
                                                                onClick={() => {
                                                                    setLinkUserId(
                                                                        '',
                                                                    );
                                                                    setLinkTarget(
                                                                        e,
                                                                    );
                                                                }}
                                                            />
                                                        )}
                                                    {e.has_login &&
                                                        device_binding_enabled && (
                                                            <ActionBtn
                                                                icon="smartphone"
                                                                label="Reset HP"
                                                                variant="neutral"
                                                                onClick={() =>
                                                                    setResetTarget(
                                                                        e,
                                                                    )
                                                                }
                                                            />
                                                        )}
                                                    {e.has_login && (
                                                        <ActionBtn
                                                            icon={
                                                                e.account_active
                                                                    ? 'user-x'
                                                                    : 'user-check'
                                                            }
                                                            label={
                                                                e.account_active
                                                                    ? 'Nonaktif'
                                                                    : 'Aktifkan'
                                                            }
                                                            variant={
                                                                e.account_active
                                                                    ? 'warning'
                                                                    : 'success'
                                                            }
                                                            onClick={() =>
                                                                router.post(
                                                                    EmployeeController.toggleAccount(
                                                                        e.route_key,
                                                                    ).url,
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    )}
                                                    <ActionBtn
                                                        icon="trash-2"
                                                        label="Hapus"
                                                        variant="danger"
                                                        onClick={() =>
                                                            setConfirm(e)
                                                        }
                                                    />
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
            {selected.size > 0 && (
                <div
                    style={{
                        position: 'fixed',
                        left: '50%',
                        bottom: 26,
                        transform: 'translateX(-50%)',
                        zIndex: 70,
                        display: 'flex',
                        alignItems: 'center',
                        gap: 14,
                        padding: '10px 12px 10px 18px',
                        background: C.navy,
                        color: '#fff',
                        borderRadius: 12,
                        boxShadow: '0 16px 40px rgba(15,23,42,.35)',
                        animation: 'toastIn .2s ease',
                    }}
                >
                    <span style={{ fontSize: 13.5, fontWeight: 600 }}>
                        {selected.size} dipilih
                    </span>
                    <button
                        onClick={() => setSelected(new Set())}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            height: 34,
                            padding: '0 12px',
                            background: 'rgba(255,255,255,.14)',
                            color: 'rgba(255,255,255,.9)',
                            border: '1px solid rgba(255,255,255,.25)',
                            borderRadius: 8,
                            fontSize: 13,
                            fontWeight: 500,
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon
                            name="x"
                            size={14}
                            color="rgba(255,255,255,.9)"
                        />
                        Batal
                    </button>
                    <button
                        onClick={() => setBulkConfirm(true)}
                        style={{
                            height: 34,
                            padding: '0 14px',
                            background: C.red,
                            color: '#fff',
                            border: 'none',
                            borderRadius: 8,
                            fontSize: 13,
                            fontWeight: 600,
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 7,
                        }}
                    >
                        <AIcon name="trash-2" size={15} color="#fff" />
                        Hapus
                    </button>
                </div>
            )}
            {bulkConfirm && (
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
                        onClick={() => setBulkConfirm(false)}
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
                            Hapus {selected.size} karyawan?
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 8,
                                lineHeight: 1.55,
                            }}
                        >
                            <strong style={{ color: C.text }}>
                                {selected.size} karyawan
                            </strong>{' '}
                            terpilih akan dihapus beserta seluruh riwayatnya.
                            Tindakan ini tidak dapat dibatalkan.
                        </div>
                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                onClick={() => setBulkConfirm(false)}
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
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="x" size={16} />
                                Batal
                            </button>
                            <button
                                onClick={bulkDelete}
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
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="x" size={16} />
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

            {resetTarget && (
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
                        onClick={() => setResetTarget(null)}
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
                                background: 'rgba(37,71,249,.1)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                marginBottom: 16,
                            }}
                        >
                            <AIcon
                                name="smartphone"
                                size={22}
                                color={C.primary}
                            />
                        </div>
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Reset perangkat?
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 8,
                                lineHeight: 1.55,
                            }}
                        >
                            <strong style={{ color: C.text }}>
                                {resetTarget.full_name}
                            </strong>{' '}
                            akan dilepas dari perangkat yang terikat sekarang,
                            lalu bisa masuk lagi dari HP mana pun.
                        </div>

                        <div
                            style={{
                                marginTop: 14,
                                padding: '12px 14px',
                                background: C.surface,
                                borderRadius: 10,
                                fontSize: 12.5,
                                lineHeight: 1.6,
                            }}
                        >
                            <div style={{ color: C.faint }}>
                                Perangkat terikat
                            </div>
                            <div style={{ color: C.text, fontWeight: 600 }}>
                                {resetTarget.device?.label}
                            </div>
                            {resetTarget.device?.last_login && (
                                <div style={{ color: C.faint }}>
                                    Terakhir masuk{' '}
                                    {resetTarget.device.last_login}
                                </div>
                            )}
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                onClick={() => setResetTarget(null)}
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
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="x" size={16} />
                                Batal
                            </button>
                            <button
                                onClick={resetDevice}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: C.primary,
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
                                <AIcon name="smartphone" size={16} />
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Link-account modal: attach an existing orphan login to this
                employee without walking the multi-step edit form. */}
            {linkTarget && (
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
                        onClick={() => setLinkTarget(null)}
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
                                width: 48,
                                height: 48,
                                borderRadius: 12,
                                background: 'rgba(37,71,249,.1)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                marginBottom: 16,
                            }}
                        >
                            <AIcon name="link" size={22} color={C.primary} />
                        </div>
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Tautkan Akun Login
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 8,
                                lineHeight: 1.55,
                            }}
                        >
                            Pilih akun yang akan menjadi login{' '}
                            <strong style={{ color: C.text }}>
                                {linkTarget.full_name}
                            </strong>{' '}
                            di aplikasi mobile. Hanya akun yang belum tertaut ke
                            karyawan lain yang muncul di sini.
                        </div>
                        <select
                            aria-label="Akun"
                            value={linkUserId}
                            onChange={(event) =>
                                setLinkUserId(event.target.value)
                            }
                            style={{
                                ...filterSelectStyle,
                                width: '100%',
                                marginTop: 16,
                                color: linkUserId ? C.text : C.muted,
                            }}
                        >
                            <option value="">— pilih akun —</option>
                            {linkableUsers.map((user) => (
                                <option key={user.id} value={String(user.id)}>
                                    {[user.name, user.email, user.roles || null]
                                        .filter(Boolean)
                                        .join(' — ')}
                                </option>
                            ))}
                        </select>
                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                onClick={() => setLinkTarget(null)}
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
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="x" size={16} />
                                Batal
                            </button>
                            <button
                                onClick={linkAccount}
                                disabled={!linkUserId}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: linkUserId
                                        ? C.primary
                                        : C.border,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 600,
                                    cursor: linkUserId
                                        ? 'pointer'
                                        : 'not-allowed',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="link" size={16} />
                                Tautkan
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
