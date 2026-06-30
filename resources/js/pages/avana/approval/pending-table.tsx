import { AIcon, C, card } from '@/lib/avana';
import { DetailCell, EmployeeCell, headThStyle, TypeBadge } from './components';
import type { ApprovalItem } from './types';

interface PendingTableProps {
    items: ApprovalItem[];
    onApprove: (item: ApprovalItem) => void;
    onReject: (item: ApprovalItem) => void;
}

/** The pending-approval table with per-row approve / reject actions. */
export function PendingTable({ items, onApprove, onReject }: PendingTableProps) {
    return (
        <div style={{ ...card, overflow: 'hidden', marginBottom: 24 }}>
            <div
                style={{
                    padding: '16px 18px',
                    borderBottom: `1px solid ${C.border}`,
                    fontSize: 15,
                    fontWeight: 600,
                    color: C.navy,
                }}
            >
                Menunggu Persetujuan
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
                            <th style={{ ...headThStyle, textAlign: 'right' }}>Aksi</th>
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
                                        <AIcon name="check-check" size={28} color={C.faint} />
                                        <div>Tidak ada pengajuan yang menunggu persetujuan.</div>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {items.map((item) => (
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
                                <td style={{ padding: '12px 18px', textAlign: 'right' }}>
                                    <div style={{ display: 'inline-flex', gap: 6 }}>
                                        <button
                                            onClick={() => onApprove(item)}
                                            style={{
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                gap: 5,
                                                height: 30,
                                                padding: '0 11px',
                                                border: 'none',
                                                borderRadius: 7,
                                                background: 'rgba(22,163,74,.1)',
                                                color: C.green,
                                                fontSize: 12,
                                                fontWeight: 600,
                                                cursor: 'pointer',
                                                transition: '.15s',
                                            }}
                                        >
                                            <AIcon name="check" size={14} color={C.green} />
                                            Setujui
                                        </button>
                                        <button
                                            onClick={() => onReject(item)}
                                            style={{
                                                width: 30,
                                                height: 30,
                                                border: 'none',
                                                borderRadius: 7,
                                                background: 'rgba(220,38,38,.08)',
                                                color: C.red,
                                                cursor: 'pointer',
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                transition: '.15s',
                                            }}
                                        >
                                            <AIcon name="x" size={14} color={C.red} />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
