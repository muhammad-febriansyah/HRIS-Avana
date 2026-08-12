import type { CSSProperties } from 'react';
import { AIcon, C } from '@/lib/avana';
import type { ApprovalEmployee, ApprovalItem, ApprovalType } from './types';
import { typeMeta } from './types';

/* ---------- shared presentational helpers for the approval page ---------- */

export const headThStyle: CSSProperties = {
    padding: '11px 18px',
    textAlign: 'left',
    fontSize: 11,
    fontWeight: 600,
    color: C.muted,
    letterSpacing: '.06em',
    textTransform: 'uppercase',
    whiteSpace: 'nowrap',
    background: '#F7F9FC',
    position: 'sticky',
    top: 0,
    zIndex: 1,
};

/** Row background: zebra striping so a wide row stays easy to track across. */
export function rowStyle(index: number, hovered: boolean): CSSProperties {
    return {
        borderTop: `1px solid ${C.line}`,
        background: hovered
            ? 'rgba(47,84,201,.045)'
            : index % 2 === 1
              ? '#FCFDFF'
              : '#fff',
        transition: 'background .12s',
    };
}

/** Panel heading shared by both tables, with an optional right-hand slot. */
export function TableHeading({
    title,
    subtitle,
    action,
}: {
    title: string;
    subtitle?: string;
    action?: React.ReactNode;
}) {
    return (
        <div
            style={{
                padding: '16px 18px',
                borderBottom: `1px solid ${C.border}`,
                display: 'flex',
                alignItems: 'flex-start',
                justifyContent: 'space-between',
                gap: 12,
                flexWrap: 'wrap',
            }}
        >
            <div>
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                    {title}
                </div>
                {subtitle && (
                    <div
                        style={{ fontSize: 12.5, color: C.muted, marginTop: 2 }}
                    >
                        {subtitle}
                    </div>
                )}
            </div>
            {action}
        </div>
    );
}

/** Centred empty state used inside a table body. */
export function EmptyRow({
    icon,
    message,
    colSpan,
}: {
    icon: string;
    message: string;
    colSpan: number;
}) {
    return (
        <tr style={{ borderTop: `1px solid ${C.line}` }}>
            <td
                colSpan={colSpan}
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
                    <AIcon name={icon} size={28} color={C.faint} />
                    <div>{message}</div>
                </div>
            </td>
        </tr>
    );
}

/** Coloured pill badge identifying the request type (with its icon). */
export function TypeBadge({ type }: { type: ApprovalType }) {
    const meta = typeMeta[type];

    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 5,
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color: meta.color,
                background: meta.color + '1a',
                whiteSpace: 'nowrap',
            }}
        >
            <AIcon name={meta.icon} size={12} color={meta.color} />
            {meta.label}
        </span>
    );
}

/** The avatar + name + employee number cell shared by both tables. */
export function EmployeeCell({
    employee,
}: {
    employee: ApprovalEmployee | null;
}) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <div
                style={{
                    width: 32,
                    height: 32,
                    borderRadius: '50%',
                    flex: 'none',
                    background: employee?.avatar_color ?? C.faint,
                    color: '#fff',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: 11.5,
                    fontWeight: 600,
                }}
            >
                {employee?.initials ?? '?'}
            </div>
            <div style={{ minWidth: 0 }}>
                <div
                    style={{
                        fontSize: 13,
                        fontWeight: 600,
                        color: C.navy,
                        whiteSpace: 'nowrap',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                    }}
                >
                    {employee?.name ?? '—'}
                </div>
                <div
                    style={{
                        fontSize: 11.5,
                        color: C.faint,
                        fontVariantNumeric: 'tabular-nums',
                    }}
                >
                    {employee?.employee_number ?? ''}
                </div>
            </div>
        </div>
    );
}

/** The title + detail stacked cell for a request. */
export function DetailCell({ item }: { item: ApprovalItem }) {
    return (
        <div style={{ maxWidth: 280 }}>
            <div
                style={{
                    fontSize: 13,
                    fontWeight: 500,
                    color: C.text,
                    lineHeight: 1.45,
                }}
            >
                {item.title}
            </div>
            <div
                style={{
                    fontSize: 11.5,
                    color: C.muted,
                    marginTop: 2,
                    lineHeight: 1.5,
                }}
            >
                {item.detail}
            </div>
        </div>
    );
}

/** Free-text reason, clamped to two lines with the full text on hover. */
export function ReasonCell({ reason }: { reason: string | null }) {
    if (!reason) {
        return <span style={{ color: C.faint }}>—</span>;
    }

    return (
        <div
            title={reason}
            style={{
                fontSize: 12.5,
                color: C.muted,
                lineHeight: 1.5,
                maxWidth: 240,
                display: '-webkit-box',
                WebkitLineClamp: 2,
                WebkitBoxOrient: 'vertical',
                overflow: 'hidden',
            }}
        >
            {reason}
        </div>
    );
}

/** Submission timestamp plus how long ago it was. */
export function RequestedCell({ item }: { item: ApprovalItem }) {
    return (
        <div style={{ whiteSpace: 'nowrap' }}>
            <div
                style={{
                    fontSize: 12.5,
                    color: C.text,
                    fontVariantNumeric: 'tabular-nums',
                }}
            >
                {item.requested_at ?? '—'}
            </div>
            {item.requested_ago && (
                <div style={{ fontSize: 11.5, color: C.faint, marginTop: 2 }}>
                    {item.requested_ago}
                </div>
            )}
        </div>
    );
}
