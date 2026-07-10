import { AIcon, C, card, statusBadge } from '@/lib/avana';
import type { PaginationMeta, Period } from './types';

interface PeriodTableProps {
    periods: Period[];
    meta: PaginationMeta;
    onGoToPage: (page: number) => void;
    onApprove: (periodId: number) => void;
    onLock: (periodId: number) => void;
    onTransfer: (periodId: number) => void;
}

const rowAction: React.CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 6,
    padding: '5px 11px',
    borderRadius: 7,
    fontSize: 12,
    fontWeight: 600,
    cursor: 'pointer',
    whiteSpace: 'nowrap',
};

/** Payroll period history table with a pagination footer. */
export function PeriodTable({
    periods,
    meta,
    onGoToPage,
    onApprove,
    onLock,
    onTransfer,
}: PeriodTableProps) {
    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <div
                style={{
                    padding: '16px 18px',
                    borderBottom: `1px solid ${C.border}`,
                    fontSize: 15,
                    fontWeight: 600,
                    color: C.navy,
                }}
            >
                Riwayat Periode
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table
                    style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                        minWidth: 480,
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
                                Periode
                            </th>
                            <th
                                style={{
                                    padding: '11px 16px',
                                    textAlign: 'right',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Net
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
                            ></th>
                        </tr>
                    </thead>
                    <tbody>
                        {periods.length === 0 && (
                            <tr
                                style={{
                                    borderTop: `1px solid ${C.line}`,
                                }}
                            >
                                <td
                                    colSpan={4}
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
                                        <div>Belum ada periode payroll.</div>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {periods.map((period) => {
                            const badge = statusBadge(period.status_label);

                            return (
                                <tr
                                    key={period.id}
                                    style={{
                                        borderTop: `1px solid ${C.line}`,
                                        transition: '.15s',
                                    }}
                                >
                                    <td
                                        style={{
                                            padding: '13px 18px',
                                        }}
                                    >
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 8,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {period.periode}
                                            </div>
                                            <span
                                                style={{
                                                    padding: '2px 8px',
                                                    borderRadius: 100,
                                                    fontSize: 10.5,
                                                    fontWeight: 600,
                                                    color: C.primary,
                                                    background:
                                                        'rgba(47,84,201,.1)',
                                                }}
                                            >
                                                {period.cycle_label}
                                            </span>
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: C.faint,
                                            }}
                                        >
                                            {period.mulai && period.selesai
                                                ? `${period.mulai} – ${period.selesai} · `
                                                : ''}
                                            {period.karyawan} karyawan
                                        </div>
                                    </td>
                                    <td
                                        style={{
                                            padding: '13px 16px',
                                            textAlign: 'right',
                                            fontSize: 13,
                                            color: C.text,
                                            fontVariantNumeric: 'tabular-nums',
                                        }}
                                    >
                                        {period.netR}
                                    </td>
                                    <td
                                        style={{
                                            padding: '13px 16px',
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
                                            padding: '13px 18px',
                                            textAlign: 'right',
                                        }}
                                    >
                                        {period.run_status === 'calculated' && (
                                            <button
                                                onClick={() =>
                                                    onApprove(period.id)
                                                }
                                                style={{
                                                    ...rowAction,
                                                    border: `1px solid ${C.primary}`,
                                                    background: '#fff',
                                                    color: C.primary,
                                                }}
                                            >
                                                <AIcon
                                                    name="check-check"
                                                    size={13}
                                                    color={C.primary}
                                                />
                                                Setujui
                                            </button>
                                        )}
                                        {period.run_status === 'approved' && (
                                            <button
                                                onClick={() =>
                                                    onLock(period.id)
                                                }
                                                style={{
                                                    ...rowAction,
                                                    border: 'none',
                                                    background: C.primary,
                                                    color: '#fff',
                                                }}
                                            >
                                                <AIcon
                                                    name="lock"
                                                    size={13}
                                                    color="#fff"
                                                />
                                                Kunci
                                            </button>
                                        )}
                                        {period.run_status === 'locked' && (
                                            <button
                                                onClick={() =>
                                                    onTransfer(period.id)
                                                }
                                                style={{
                                                    ...rowAction,
                                                    border: `1px solid ${C.border}`,
                                                    background: '#fff',
                                                    color: C.text,
                                                }}
                                            >
                                                <AIcon
                                                    name="download"
                                                    size={13}
                                                    color={C.muted}
                                                />
                                                Transfer
                                            </button>
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
