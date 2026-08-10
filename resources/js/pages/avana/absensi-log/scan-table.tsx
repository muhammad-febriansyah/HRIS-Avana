import { AIcon, C, thCell } from '@/lib/avana';
import type { FaceScanMetrics, FaceScanRow } from './types';

interface ScanTableProps {
    rows: FaceScanRow[];
}

const CONTEXT_LABELS: Record<FaceScanRow['context'], string> = {
    enroll: 'Daftar Wajah',
    verify: 'Scan Absen',
    clock: 'Cocok di Server',
};

/** Colour treatment for the outcome badge. */
function outcomeBadge(outcome: FaceScanRow['outcome']): {
    label: string;
    color: string;
    bg: string;
} {
    switch (outcome) {
        case 'ok':
            return { label: 'Berhasil', color: C.green, bg: 'rgba(22,163,74,.1)' };
        case 'blocked':
            return { label: 'Ditolak', color: C.red, bg: 'rgba(220,38,38,.1)' };
        default:
            return { label: 'Gagal', color: C.amber, bg: 'rgba(217,119,6,.1)' };
    }
}

/**
 * The measurements worth reading at a glance, in the order that answers "why
 * did this scan fail": how many faces, how far the head was turned, whether the
 * eyes read as open, and the match score.
 */
function metricChips(metrics: FaceScanMetrics | null): string[] {
    if (!metrics) {
        return [];
    }

    const chips: string[] = [];
    const push = (label: string, value: number | undefined, digits = 2) => {
        if (value !== undefined && value !== null) {
            chips.push(`${label} ${value.toFixed(digits)}`);
        }
    };

    if (metrics.faces !== undefined) {
        chips.push(`wajah ${metrics.faces}`);
    }
    if (metrics.detector) {
        chips.push(`model ${metrics.detector}`);
    }
    push('yaw', metrics.yaw, 1);
    push('roll', metrics.roll, 1);
    push('mata L', metrics.left_eye_open);
    push('mata R', metrics.right_eye_open);
    push('senyum', metrics.smiling);
    push('lebar', metrics.face_width_ratio);
    push('skor', metrics.score, 3);
    if (metrics.frame_width && metrics.frame_height) {
        chips.push(`frame ${metrics.frame_width}×${metrics.frame_height}`);
    }
    if (metrics.error) {
        chips.push(metrics.error);
    }

    return chips;
}

/** Read-only face scan table: time, employee, flow, outcome, diagnostics. */
export function ScanTable({ rows }: ScanTableProps) {
    return (
        <div style={{ overflowX: 'auto' }}>
            <table
                style={{
                    width: '100%',
                    borderCollapse: 'collapse',
                    minWidth: 980,
                }}
            >
                <thead>
                    <tr style={{ background: '#FAFBFD' }}>
                        <th style={thCell}>Waktu</th>
                        <th style={thCell}>Karyawan</th>
                        <th style={thCell}>Alur</th>
                        <th style={thCell}>Hasil</th>
                        <th style={thCell}>Sebab</th>
                        <th style={thCell}>Perangkat</th>
                        <th style={thCell}>Ukuran</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr style={{ borderTop: `1px solid ${C.line}` }}>
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
                                        name="scan-face"
                                        size={28}
                                        color={C.faint}
                                    />
                                    <div>
                                        Belum ada percobaan scan wajah tercatat.
                                    </div>
                                </div>
                            </td>
                        </tr>
                    )}
                    {rows.map((row) => {
                        const badge = outcomeBadge(row.outcome);
                        const chips = metricChips(row.metrics);

                        return (
                            <tr
                                key={row.id}
                                style={{ borderTop: `1px solid ${C.line}` }}
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
                                <td style={{ padding: '13px 16px', fontSize: 13 }}>
                                    <div style={{ color: C.text, fontWeight: 500 }}>
                                        {row.employee ?? '—'}
                                    </div>
                                    {row.employee_number && (
                                        <div
                                            style={{
                                                color: C.faint,
                                                fontSize: 12,
                                            }}
                                        >
                                            {row.employee_number}
                                        </div>
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
                                    {CONTEXT_LABELS[row.context] ?? row.context}
                                    {row.step !== null && (
                                        <span style={{ color: C.faint }}>
                                            {' '}
                                            · langkah {row.step + 1}
                                        </span>
                                    )}
                                </td>
                                <td style={{ padding: '13px 16px' }}>
                                    <span
                                        style={{
                                            display: 'inline-block',
                                            padding: '3px 9px',
                                            borderRadius: 999,
                                            fontSize: 12,
                                            fontWeight: 500,
                                            color: badge.color,
                                            background: badge.bg,
                                        }}
                                    >
                                        {badge.label}
                                    </span>
                                </td>
                                <td style={{ padding: '13px 16px', fontSize: 13 }}>
                                    <div style={{ color: C.text }}>
                                        {row.reason_label}
                                    </div>
                                    {row.message && (
                                        <div
                                            style={{
                                                color: C.faint,
                                                fontSize: 12,
                                            }}
                                        >
                                            {row.message}
                                        </div>
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
                                    <div>{row.device_model ?? '—'}</div>
                                    <div style={{ color: C.faint, fontSize: 12 }}>
                                        {[row.os_version, row.app_version]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                    </div>
                                </td>
                                <td style={{ padding: '13px 16px' }}>
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexWrap: 'wrap',
                                            gap: 5,
                                            maxWidth: 340,
                                        }}
                                    >
                                        {chips.length === 0 && (
                                            <span
                                                style={{
                                                    fontSize: 12.5,
                                                    color: C.faint,
                                                }}
                                            >
                                                —
                                            </span>
                                        )}
                                        {chips.map((chip) => (
                                            <span
                                                key={chip}
                                                style={{
                                                    padding: '2px 7px',
                                                    borderRadius: 6,
                                                    background: C.surface,
                                                    color: C.muted,
                                                    fontSize: 12,
                                                    fontFamily:
                                                        'ui-monospace, SFMono-Regular, monospace',
                                                }}
                                            >
                                                {chip}
                                            </span>
                                        ))}
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
