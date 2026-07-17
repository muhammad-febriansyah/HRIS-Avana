import type { CSSProperties, ReactNode } from 'react';
import { AIcon, C, rp, statusBadge } from '@/lib/avana';
import type { SettlementOutcome } from './types';

/* ---------- shared field styles (mirror the kasbon module) ---------- */

export const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

export const selectStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.muted,
    background: '#fff',
    outline: 'none',
    cursor: 'pointer',
};

export const textInputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
};

export const dateInputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 11px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 12.5,
    color: C.muted,
    outline: 'none',
};

export const textareaStyle: CSSProperties = {
    width: '100%',
    padding: '11px 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    outline: 'none',
    resize: 'vertical',
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

/** Apply the red error border to a base input/select style when invalid. */
export function withError(
    base: CSSProperties,
    hasError: boolean,
): CSSProperties {
    return hasError
        ? {
              ...base,
              border: `1px solid ${C.red}`,
              boxShadow: '0 0 0 3px rgba(220,38,38,.08)',
          }
        : base;
}

/** Inline error message rendered under a field. */
export function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <div style={errorTextStyle}>
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

/** A single labelled field wrapper. */
export function Field({
    label,
    required,
    error,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label style={fieldLabelStyle}>
                {label} {required && <span style={{ color: C.red }}>*</span>}
            </label>
            {children}
            <FieldError message={error} />
        </div>
    );
}

/** Rounded status badge for a settlement, driven by its Indonesian label. */
export function StatusPill({ label }: { label: string }) {
    const badge = statusBadge(label);

    return (
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
    );
}

/** Colour per settlement outcome: money owed to us, by us, or neither. */
export function outcomeColor(outcome: SettlementOutcome): string {
    return {
        return: '#D97706',
        topup: '#DC2626',
        balanced: '#16A34A',
    }[outcome];
}

/** Badge naming which way the money still has to move. */
export function OutcomePill({
    outcome,
    label,
}: {
    outcome: SettlementOutcome;
    label: string;
}) {
    const color = outcomeColor(outcome);

    return (
        <span
            style={{
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color,
                background: `${color}1a`,
            }}
        >
            {label}
        </span>
    );
}

/** A single KPI tile shown in the row above the list. */
export function KpiCard({
    icon,
    label,
    value,
    accent = C.primary,
}: {
    icon: string;
    label: string;
    value: string;
    accent?: string;
}) {
    return (
        <div
            style={{
                background: '#fff',
                border: `1px solid ${C.border}`,
                borderRadius: 12,
                padding: '16px 18px',
                display: 'flex',
                alignItems: 'center',
                gap: 13,
            }}
        >
            <div
                style={{
                    width: 40,
                    height: 40,
                    borderRadius: 10,
                    flex: 'none',
                    background: `${accent}1a`,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                }}
            >
                <AIcon name={icon} size={19} color={accent} />
            </div>
            <div style={{ minWidth: 0 }}>
                <div style={{ fontSize: 12, color: C.faint }}>{label}</div>
                <div
                    style={{
                        fontSize: 18,
                        fontWeight: 600,
                        color: C.navy,
                        letterSpacing: '-.01em',
                    }}
                >
                    {value}
                </div>
            </div>
        </div>
    );
}

/** One `label: value` line in the settlement summary panel. */
export function SummaryRow({
    label,
    value,
    accent,
    strong = false,
}: {
    label: string;
    value: number;
    accent?: string;
    strong?: boolean;
}) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 12,
                fontSize: 13,
            }}
        >
            <span style={{ color: C.muted }}>{label}</span>
            <span
                style={{
                    color: accent ?? C.text,
                    fontWeight: strong ? 700 : 600,
                    fontSize: strong ? 15 : 13,
                }}
            >
                {rp(value)}
            </span>
        </div>
    );
}
