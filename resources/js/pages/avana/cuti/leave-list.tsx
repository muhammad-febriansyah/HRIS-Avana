import { AIcon, C, card, statusBadge } from '@/lib/avana';
import type { LeaveRequest, PaginationMeta } from './types';

interface LeaveApprovalListProps {
    rows: LeaveRequest[];
    meta: PaginationMeta;
    pendingActive: boolean;
    setStatusFilter: (status: 'pending' | undefined) => void;
    goToPage: (page: number) => void;
    approveRequest: (id: number) => void;
    rejectRequest: (id: number) => void;
}

/** The "Pengajuan Tim" leave list with status filter, approvals and pagination. */
export function LeaveApprovalList({
    rows,
    meta,
    pendingActive,
    setStatusFilter,
    goToPage,
    approveRequest,
    rejectRequest,
}: LeaveApprovalListProps) {
    return (
        <div style={{ ...card, overflow: 'hidden' }}>
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
                    Pengajuan Tim
                </div>
                <div
                    style={{
                        display: 'flex',
                        gap: 4,
                        background: C.surface,
                        padding: 3,
                        borderRadius: 8,
                    }}
                >
                    <span
                        onClick={() => setStatusFilter('pending')}
                        style={{
                            fontSize: 12.5,
                            fontWeight: 600,
                            color: pendingActive ? '#fff' : C.muted,
                            background: pendingActive
                                ? C.primary
                                : 'transparent',
                            padding: '5px 12px',
                            borderRadius: 6,
                            cursor: 'pointer',
                        }}
                    >
                        Menunggu
                    </span>
                    <span
                        onClick={() => setStatusFilter(undefined)}
                        style={{
                            fontSize: 12.5,
                            fontWeight: pendingActive ? 500 : 600,
                            color: pendingActive ? C.muted : '#fff',
                            background: pendingActive
                                ? 'transparent'
                                : C.primary,
                            padding: '5px 12px',
                            borderRadius: 6,
                            cursor: 'pointer',
                        }}
                    >
                        Semua
                    </span>
                </div>
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table
                    style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                        minWidth: 640,
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
                                Jenis
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
                                Tanggal
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
                                Status
                            </th>
                            <th
                                style={{
                                    padding: '11px 18px',
                                    textAlign: 'right',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Aksi
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
                                            name="palmtree"
                                            size={28}
                                            color={C.faint}
                                        />
                                        <div>Tidak ada pengajuan cuti.</div>
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
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {row.durasi}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 13,
                                            color: C.muted,
                                        }}
                                    >
                                        {row.leave_type?.name ?? '—'}
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 12.5,
                                            color: C.text,
                                        }}
                                    >
                                        {row.start_date} – {row.end_date}
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
                                                background: badge.bg,
                                            }}
                                        >
                                            {badge.label}
                                        </span>
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 18px',
                                            textAlign: 'right',
                                        }}
                                    >
                                        {row.status === 'pending' ? (
                                            <div
                                                style={{
                                                    display: 'inline-flex',
                                                    gap: 6,
                                                }}
                                            >
                                                <button
                                                    onClick={() =>
                                                        approveRequest(row.id)
                                                    }
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: 5,
                                                        height: 30,
                                                        padding: '0 11px',
                                                        border: 'none',
                                                        borderRadius: 7,
                                                        background:
                                                            'rgba(22,163,74,.1)',
                                                        color: C.green,
                                                        fontSize: 12,
                                                        fontWeight: 600,
                                                        cursor: 'pointer',
                                                        transition: '.15s',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="check"
                                                        size={14}
                                                        color={C.green}
                                                    />
                                                    Setujui
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        rejectRequest(row.id)
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
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        justifyContent:
                                                            'center',
                                                        transition: '.15s',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="x"
                                                        size={14}
                                                        color={C.red}
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
                    <span style={{ color: C.text, fontWeight: 500 }}>
                        {meta.from ?? 0}–{meta.to ?? 0}
                    </span>{' '}
                    dari{' '}
                    <span style={{ color: C.text, fontWeight: 500 }}>
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
    );
}
