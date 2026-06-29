import { Head, router } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useRef, useState } from 'react';
import { AIcon, C, thCell } from '@/lib/avana';
import type { PaginationMeta } from './employees/types';

/** A single audit log row as serialized by `AuditController@index`. */
interface AuditRow {
    id: number;
    action: 'created' | 'updated' | 'deleted';
    auditable_type: string;
    auditable_id: number;
    label: string;
    user: string | null;
    ip_address: string | null;
    changes: string[];
    created_at: string | null;
}

interface AuditFilters {
    search?: string | null;
    action?: string | null;
    per_page?: string;
}

interface AuditProps {
    logs: {
        data: AuditRow[];
        meta: PaginationMeta;
    };
    filters: AuditFilters;
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

/** Visual treatment for each audit action. */
function actionBadge(action: AuditRow['action']): {
    label: string;
    color: string;
    bg: string;
} {
    switch (action) {
        case 'created':
            return { label: 'Dibuat', color: C.green, bg: 'rgba(22,163,74,.1)' };
        case 'deleted':
            return { label: 'Dihapus', color: C.red, bg: 'rgba(220,38,38,.1)' };
        default:
            return {
                label: 'Diubah',
                color: C.primary,
                bg: 'rgba(47,84,201,.1)',
            };
    }
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

export default function AvanaAudit({ logs, filters }: AuditProps) {
    const meta = logs.meta;
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstSearch = useRef(true);

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

    return (
        <>
            <Head title="Audit Trail" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
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
                        <span>Pengaturan</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Audit Trail</span>
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
                        Audit Trail
                    </h1>
                    <div
                        style={{ fontSize: 14, color: C.muted, marginTop: 4 }}
                    >
                        Catatan perubahan data sensitif: karyawan, payroll, cuti,
                        peran, pengguna &amp; tenant
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
                                placeholder="Cari entitas atau aksi…"
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
                            aria-label="Aksi"
                            value={filters.action ?? ''}
                            onChange={(event) =>
                                applyFilter('action', event.target.value)
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Aksi</option>
                            <option value="created">Dibuat</option>
                            <option value="updated">Diubah</option>
                            <option value="deleted">Dihapus</option>
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
                                    <th style={thCell}>Waktu</th>
                                    <th style={thCell}>Pengguna</th>
                                    <th style={thCell}>Aksi</th>
                                    <th style={thCell}>Entitas</th>
                                    <th style={thCell}>Perubahan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.length === 0 && (
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
                                                    name="history"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Belum ada catatan audit.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {logs.data.map((log) => {
                                    const badge = actionBadge(log.action);

                                    return (
                                        <tr
                                            key={log.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                                transition: 'background .15s',
                                            }}
                                        >
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.muted,
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {log.created_at ?? '—'}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        fontSize: 13.5,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {log.user ?? 'Sistem'}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {log.ip_address ?? '—'}
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
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <div
                                                    style={{
                                                        fontSize: 13.5,
                                                        fontWeight: 500,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {log.auditable_type}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {log.label}
                                                </div>
                                            </td>
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                {log.changes.length === 0 ? (
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
                                                            flexWrap: 'wrap',
                                                            gap: 5,
                                                            maxWidth: 360,
                                                        }}
                                                    >
                                                        {log.changes
                                                            .slice(0, 4)
                                                            .map((field) => (
                                                                <span
                                                                    key={field}
                                                                    style={{
                                                                        display:
                                                                            'inline-block',
                                                                        padding:
                                                                            '2px 8px',
                                                                        borderRadius: 6,
                                                                        fontSize: 11.5,
                                                                        fontWeight: 500,
                                                                        color: C.muted,
                                                                        background:
                                                                            C.surface,
                                                                    }}
                                                                >
                                                                    {field}
                                                                </span>
                                                            ))}
                                                        {log.changes.length >
                                                            4 && (
                                                            <span
                                                                style={{
                                                                    fontSize: 11.5,
                                                                    fontWeight: 500,
                                                                    color: C.faint,
                                                                    alignSelf:
                                                                        'center',
                                                                }}
                                                            >
                                                                +
                                                                {log.changes
                                                                    .length - 4}
                                                            </span>
                                                        )}
                                                    </div>
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
                            </span>{' '}
                            catatan
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
        </>
    );
}
