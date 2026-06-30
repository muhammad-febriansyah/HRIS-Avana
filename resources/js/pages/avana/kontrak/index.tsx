import { Head, Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import ContractController from '@/actions/App/Http/Controllers/Avana/ContractController';
import { AIcon, btnP, C, rp, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    ExpiringBadge,
    iconBtn,
    StatusPill,
} from './components';
import { contractTypeLabel, formatDate, statusOptions } from './types';
import type {
    ContractRow,
    FlashProps,
    KontrakIndexProps,
} from './types';

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

export default function KontrakIndex({
    contracts,
    stats,
    filters,
}: KontrakIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = contracts.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const [confirm, setConfirm] = useState<ContractRow | null>(null);
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

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
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
                    <Link
                        href={ContractController.create()}
                        style={{ ...btnP, textDecoration: 'none' }}
                    >
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Kontrak
                    </Link>
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
                                {contracts.data.map((contract) => (
                                    <tr
                                        key={contract.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                            transition: 'background .15s',
                                        }}
                                    >
                                        <td style={{ padding: '13px 16px' }}>
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {contract.employee?.name ?? '—'}
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
                                            {formatDate(contract.start_date)} –{' '}
                                            {contract.end_date
                                                ? formatDate(contract.end_date)
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
                                        <td style={{ padding: '13px 16px' }}>
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    flexDirection: 'column',
                                                    gap: 5,
                                                    alignItems: 'flex-start',
                                                }}
                                            >
                                                <StatusPill
                                                    status={contract.status}
                                                />
                                                {contract.expiring_soon && (
                                                    <ExpiringBadge
                                                        days={
                                                            contract.days_to_expiry
                                                        }
                                                    />
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
                                                <Link
                                                    title="Ubah"
                                                    href={ContractController.edit(
                                                        contract.id,
                                                    )}
                                                    style={iconBtn}
                                                >
                                                    <AIcon
                                                        name="pencil"
                                                        size={15}
                                                        color={C.muted}
                                                    />
                                                </Link>
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

            {/* Confirm delete modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus kontrak?"
                    body={
                        <>
                            Kontrak{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.contract_number}
                            </strong>{' '}
                            akan dihapus. Tindakan ini tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteContract}
                />
            )}
        </>
    );
}
