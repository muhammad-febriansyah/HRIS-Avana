import { AIcon, C, thCell } from '@/lib/avana';
import { actionBadge } from './components';
import type { AuditRow } from './types';

interface AuditTableProps {
    rows: AuditRow[];
}

/** Read-only audit log table: time, user, action, entity, changed fields. */
export function AuditTable({ rows }: AuditTableProps) {
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
                        <th style={thCell}>Waktu</th>
                        <th style={thCell}>Pengguna</th>
                        <th style={thCell}>Aksi</th>
                        <th style={thCell}>Entitas</th>
                        <th style={thCell}>Perubahan</th>
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
                                        name="history"
                                        size={28}
                                        color={C.faint}
                                    />
                                    <div>Belum ada catatan audit.</div>
                                </div>
                            </td>
                        </tr>
                    )}
                    {rows.map((log) => {
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
                                <td style={{ padding: '13px 16px' }}>
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
                                <td style={{ padding: '13px 16px' }}>
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
                                <td style={{ padding: '13px 16px' }}>
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
                                                            padding: '2px 8px',
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
                                            {log.changes.length > 4 && (
                                                <span
                                                    style={{
                                                        fontSize: 11.5,
                                                        fontWeight: 500,
                                                        color: C.faint,
                                                        alignSelf: 'center',
                                                    }}
                                                >
                                                    +{log.changes.length - 4}
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
    );
}
