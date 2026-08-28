import type { CSSProperties } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import type { ComplianceStatus, CompletenessBar, StepState } from './types';

/* ---------- solid buttons ----------
 * Every button on this screen carries a filled background: a white or
 * transparent control here sat on a white card and read as static text.
 * Colours follow the app's semantic palette in `@/lib/avana` — amber for
 * unduh/export, primary for the action a panel exists to perform — with a
 * solid neutral standing in for the documented white `btnOut` on secondary
 * actions (Batal, paging, disclosure).
 */
const btnSmBase: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 6,
    height: 34,
    padding: '0 13px',
    border: 'none',
    borderRadius: 8,
    fontSize: 12.5,
    fontWeight: 600,
    cursor: 'pointer',
    textDecoration: 'none',
    whiteSpace: 'nowrap',
    transition: 'filter .15s',
};

/** Unduh / export, in every format. */
export const btnSmExport: CSSProperties = {
    ...btnSmBase,
    background: C.amber,
    color: '#fff',
};

/** The primary action of a table row or panel header. */
export const btnSmPrimary: CSSProperties = {
    ...btnSmBase,
    background: C.primary,
    color: '#fff',
};

/** Secondary: disclosure, paging, Batal. Solid, not white. */
export const btnSmNeutral: CSSProperties = {
    ...btnSmBase,
    background: '#E6EAF3',
    color: C.navy,
};

/** Full-height Batal, replacing the palette's white `btnOut`. */
export const btnCancel: CSSProperties = {
    ...btnSmNeutral,
    height: 40,
    padding: '0 15px',
    fontSize: 13.5,
};

export const th: CSSProperties = {
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    padding: '11px 16px',
    textTransform: 'uppercase',
    letterSpacing: '.03em',
    whiteSpace: 'nowrap',
};

export const td: CSSProperties = {
    fontSize: 13,
    color: C.text,
    padding: '12px 16px',
};

export const num: CSSProperties = {
    ...td,
    textAlign: 'right',
    fontVariantNumeric: 'tabular-nums',
};

/** Colour a checklist step or a compliance status resolves to. */
export function stateColor(state: StepState): string {
    return state === 'done' ? C.green : state === 'warn' ? C.amber : C.red;
}

/** Card shell with a title row and an optional right-hand slot. */
export function Panel({
    title,
    right,
    children,
    id,
}: {
    title: string;
    right?: React.ReactNode;
    children: React.ReactNode;
    id?: string;
}) {
    return (
        <div id={id} style={{ ...card, overflow: 'hidden' }}>
            <div
                style={{
                    padding: '16px 20px',
                    borderBottom: `1px solid ${C.line}`,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 12,
                    flexWrap: 'wrap',
                }}
            >
                <div style={{ fontSize: 14.5, fontWeight: 600, color: C.navy }}>
                    {title}
                </div>
                {right}
            </div>
            {children}
        </div>
    );
}

/** Pill badge for a deposit/report status. */
export function StatusPill({ status }: { status: ComplianceStatus }) {
    const done = status === 'done';

    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 5,
                padding: '4px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color: done ? C.green : C.red,
                background: done ? 'rgba(22,163,74,.1)' : 'rgba(220,38,38,.09)',
            }}
        >
            <AIcon
                name={done ? 'circle-check' : 'circle-alert'}
                size={12}
                color={done ? C.green : C.red}
            />
            {done ? 'Selesai' : 'Belum'}
        </span>
    );
}

/** One labelled progress bar in the data-completeness panel. */
export function ProgressRow({ bar }: { bar: CompletenessBar }) {
    const pct = bar.total > 0 ? (bar.done / bar.total) * 100 : 0;
    const complete = bar.total > 0 && bar.done >= bar.total;

    return (
        <div style={{ marginBottom: 14 }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    fontSize: 12.5,
                    color: C.muted,
                    marginBottom: 6,
                }}
            >
                <span>{bar.label}</span>
                <span
                    style={{
                        fontWeight: 600,
                        color: complete ? C.green : C.amber,
                        fontVariantNumeric: 'tabular-nums',
                    }}
                >
                    {bar.done} / {bar.total}
                </span>
            </div>
            <div
                style={{
                    height: 7,
                    background: C.line,
                    borderRadius: 20,
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        height: '100%',
                        width: `${Math.min(100, pct)}%`,
                        background: complete ? C.green : C.amber,
                        borderRadius: 20,
                        transition: 'width .3s',
                    }}
                />
            </div>
        </div>
    );
}
