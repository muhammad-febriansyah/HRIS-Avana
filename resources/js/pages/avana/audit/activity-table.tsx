import { AIcon, C } from '@/lib/avana';
import { activityBadge } from './components';
import type { ActivityRow } from './types';

interface ActivityTableProps {
    rows: ActivityRow[];
}

/** Read-only activity log table: time, user, event, description, IP. */
export function ActivityTable({ rows }: ActivityTableProps) {
    return (
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
                        <th
                            style={{
                                textAlign: 'left',
                                padding: '11px 16px',
                                fontSize: 11.5,
                                fontWeight: 600,
                                color: C.faint,
                                textTransform: 'uppercase',
                                letterSpacing: '.03em',
                            }}
                        >
                            Waktu
                        </th>
                        <th
                            style={{
                                textAlign: 'left',
                                padding: '11px 16px',
                                fontSize: 11.5,
                                fontWeight: 600,
                                color: C.faint,
                                textTransform: 'uppercase',
                                letterSpacing: '.03em',
                            }}
                        >
                            Pengguna
                        </th>
                        <th
                            style={{
                                textAlign: 'left',
                                padding: '11px 16px',
                                fontSize: 11.5,
                                fontWeight: 600,
                                color: C.faint,
                                textTransform: 'uppercase',
                                letterSpacing: '.03em',
                            }}
                        >
                            Aktivitas
                        </th>
                        <th
                            style={{
                                textAlign: 'left',
                                padding: '11px 16px',
                                fontSize: 11.5,
                                fontWeight: 600,
                                color: C.faint,
                                textTransform: 'uppercase',
                                letterSpacing: '.03em',
                            }}
                        >
                            Keterangan
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr style={{ borderTop: `1px solid ${C.line}` }}>
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
                                    <AIcon name="activity" size={28} color={C.faint} />
                                    <div>Belum ada aktivitas tercatat.</div>
                                </div>
                            </td>
                        </tr>
                    )}
                    {rows.map((row) => {
                        const badge = activityBadge(row.event);

                        return (
                            <tr
                                key={row.id}
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
                                    {row.created_at ?? '—'}
                                </td>
                                <td style={{ padding: '13px 16px' }}>
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {row.user ?? 'Sistem'}
                                    </div>
                                    <div style={{ fontSize: 12, color: C.faint }}>
                                        {row.ip_address ?? '—'}
                                    </div>
                                </td>
                                <td style={{ padding: '13px 16px' }}>
                                    <span
                                        style={{
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            gap: 5,
                                            padding: '3px 10px',
                                            borderRadius: 100,
                                            fontSize: 11.5,
                                            fontWeight: 600,
                                            color: badge.color,
                                            background: badge.bg,
                                        }}
                                    >
                                        <AIcon name={badge.icon} size={12} />
                                        {badge.label}
                                    </span>
                                </td>
                                <td
                                    style={{
                                        padding: '13px 16px',
                                        fontSize: 13,
                                        color: C.text,
                                        maxWidth: 380,
                                    }}
                                >
                                    {row.description ?? row.path ?? '—'}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
