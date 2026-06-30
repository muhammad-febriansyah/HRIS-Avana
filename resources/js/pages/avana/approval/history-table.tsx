import { AIcon, C, card, statusBadge } from '@/lib/avana';
import { DetailCell, EmployeeCell, headThStyle, TypeBadge } from './components';
import type { ApprovalItem } from './types';

/** The read-only approval history table with status pills. */
export function HistoryTable({ items }: { items: ApprovalItem[] }) {
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
                Riwayat Persetujuan
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 760 }}>
                    <thead>
                        <tr style={{ background: '#FAFBFD' }}>
                            <th style={headThStyle}>Karyawan</th>
                            <th style={headThStyle}>Jenis</th>
                            <th style={headThStyle}>Detail</th>
                            <th style={headThStyle}>Alasan</th>
                            <th style={headThStyle}>Diajukan</th>
                            <th style={headThStyle}>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr style={{ borderTop: `1px solid ${C.line}` }}>
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
                                        <AIcon name="history" size={28} color={C.faint} />
                                        <div>Belum ada riwayat persetujuan.</div>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {items.map((item) => {
                            const badge = statusBadge(item.status_label);

                            return (
                                <tr
                                    key={`${item.type}-${item.id}`}
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td style={{ padding: '12px 18px' }}>
                                        <EmployeeCell employee={item.employee} />
                                    </td>
                                    <td style={{ padding: '12px 16px' }}>
                                        <TypeBadge type={item.type} />
                                    </td>
                                    <td style={{ padding: '12px 16px' }}>
                                        <DetailCell item={item} />
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 12.5,
                                            color: C.muted,
                                            maxWidth: 220,
                                        }}
                                    >
                                        {item.reason ?? '—'}
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 12.5,
                                            color: C.text,
                                            whiteSpace: 'nowrap',
                                        }}
                                    >
                                        {item.requested_at ?? '—'}
                                    </td>
                                    <td style={{ padding: '12px 16px' }}>
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
        </div>
    );
}
