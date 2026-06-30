import { AIcon, C, statusBadge } from '@/lib/avana';
import type { Attendance, PaginationMeta } from './types';

interface RekapTableProps {
    rows: Attendance[];
    meta: PaginationMeta;
    search: string;
    onSearchChange: (value: string) => void;
    onGoToPage: (page: number) => void;
}

/** Daily attendance rekap table with a search header and pagination footer. */
export function RekapTable({
    rows,
    meta,
    search,
    onSearchChange,
    onGoToPage,
}: RekapTableProps) {
    return (
        <div
            style={{
                background: '#fff',
                border: `1px solid ${C.border}`,
                borderRadius: 12,
                boxShadow: '0 1px 2px rgba(15,23,42,.04)',
                overflow: 'hidden',
            }}
        >
            <div
                style={{
                    padding: '16px 18px',
                    borderBottom: `1px solid ${C.border}`,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                }}
            >
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                    }}
                >
                    Rekap Kehadiran Harian
                </div>
                <div style={{ position: 'relative' }}>
                    <span
                        style={{
                            position: 'absolute',
                            left: 11,
                            top: '50%',
                            transform: 'translateY(-50%)',
                            display: 'flex',
                        }}
                    >
                        <AIcon name="search" size={15} color={C.faint} />
                    </span>
                    <input
                        placeholder="Cari karyawan…"
                        value={search}
                        onChange={(event) => onSearchChange(event.target.value)}
                        style={{
                            height: 36,
                            width: 180,
                            padding: '0 12px 0 34px',
                            background: C.surface,
                            border: '1px solid transparent',
                            borderRadius: 8,
                            fontSize: 13,
                            outline: 'none',
                        }}
                    />
                </div>
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table
                    style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                        minWidth: 620,
                    }}
                >
                    <thead>
                        <tr style={{ background: '#FAFBFD' }}>
                            <th
                                style={{
                                    padding: '11px 18px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Karyawan
                            </th>
                            <th
                                style={{
                                    padding: '11px 16px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Shift
                            </th>
                            <th
                                style={{
                                    padding: '11px 16px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Masuk
                            </th>
                            <th
                                style={{
                                    padding: '11px 16px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Keluar
                            </th>
                            <th
                                style={{
                                    padding: '11px 16px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Telat
                            </th>
                            <th
                                style={{
                                    padding: '11px 18px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr
                                style={{
                                    borderTop: `1px solid ${C.line}`,
                                }}
                            >
                                <td
                                    colSpan={6}
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
                                            name="fingerprint"
                                            size={28}
                                            color={C.faint}
                                        />
                                        <div>
                                            Belum ada data kehadiran untuk
                                            tanggal ini.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {rows.map((row) => {
                            const badge = statusBadge(row.status_label);

                            return (
                                <tr
                                    key={row.id}
                                    style={{
                                        borderTop: `1px solid ${C.line}`,
                                    }}
                                >
                                    <td
                                        style={{
                                            padding: '12px 18px',
                                        }}
                                    >
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 10,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    width: 32,
                                                    height: 32,
                                                    borderRadius: '50%',
                                                    flex: 'none',
                                                    background:
                                                        row.employee
                                                            ?.avatar_color ??
                                                        C.faint,
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
                                            <span
                                                style={{
                                                    fontSize: 13,
                                                    fontWeight: 500,
                                                    color: C.text,
                                                }}
                                            >
                                                {row.employee?.name ?? '—'}
                                            </span>
                                        </div>
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 12.5,
                                            color: C.muted,
                                        }}
                                    >
                                        {row.shift?.label ?? '—'}
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 13,
                                            color: C.text,
                                            fontVariantNumeric: 'tabular-nums',
                                        }}
                                    >
                                        {row.clock_in}
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 13,
                                            color: C.text,
                                            fontVariantNumeric: 'tabular-nums',
                                        }}
                                    >
                                        {row.clock_out}
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 13,
                                            color: C.muted,
                                        }}
                                    >
                                        {row.telat}
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 18px',
                                        }}
                                    >
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
                        style={{
                            color: C.text,
                            fontWeight: 500,
                        }}
                    >
                        {meta.from ?? 0}–{meta.to ?? 0}
                    </span>{' '}
                    dari{' '}
                    <span
                        style={{
                            color: C.text,
                            fontWeight: 500,
                        }}
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
                        onClick={() => onGoToPage(meta.current_page - 1)}
                        style={{
                            height: 34,
                            minWidth: 34,
                            padding: '0 10px',
                            border: `1px solid ${C.border}`,
                            background: '#fff',
                            borderRadius: 8,
                            fontSize: 13,
                            color: meta.current_page <= 1 ? C.faint : C.text,
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
                        disabled={meta.current_page >= meta.last_page}
                        onClick={() => onGoToPage(meta.current_page + 1)}
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
    );
}
