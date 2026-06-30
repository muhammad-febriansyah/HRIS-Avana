import type { CSSProperties } from 'react';
import { AIcon, C } from '@/lib/avana';
import type { ApprovalEmployee, ApprovalItem, ApprovalType } from './types';
import { typeMeta } from './types';

/* ---------- shared presentational helpers for the approval page ---------- */

export const headThStyle: CSSProperties = {
    padding: '11px 18px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
};

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
            }}
        >
            <AIcon name={meta.icon} size={12} color={meta.color} />
            {meta.label}
        </span>
    );
}

/** The avatar + name + employee number cell shared by both tables. */
export function EmployeeCell({ employee }: { employee: ApprovalEmployee | null }) {
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
            <div>
                <div style={{ fontSize: 13, fontWeight: 500, color: C.text }}>
                    {employee?.name ?? '—'}
                </div>
                <div style={{ fontSize: 11.5, color: C.faint }}>
                    {employee?.employee_number ?? ''}
                </div>
            </div>
        </div>
    );
}

/** The title + detail stacked cell for a request. */
export function DetailCell({ item }: { item: ApprovalItem }) {
    return (
        <div>
            <div style={{ fontSize: 13, fontWeight: 500, color: C.text }}>
                {item.title}
            </div>
            <div style={{ fontSize: 11.5, color: C.faint, marginTop: 2 }}>
                {item.detail}
            </div>
        </div>
    );
}
