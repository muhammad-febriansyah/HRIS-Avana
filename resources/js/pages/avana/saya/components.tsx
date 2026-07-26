import type { CSSProperties, ReactNode } from 'react';
import { AIcon, C, card } from '@/lib/avana';

/* ---------- shared field styles (mirror jenis-cuti/components.tsx) ---------- */

export const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

export const inputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

export const selectStyle: CSSProperties = {
    ...inputStyle,
    cursor: 'pointer',
};

export const textareaStyle: CSSProperties = {
    ...inputStyle,
    height: 'auto',
    minHeight: 88,
    padding: '11px 13px',
    resize: 'vertical',
    fontFamily: 'inherit',
};

/** Apply the red error border to a base style when invalid. */
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
        <div
            style={{
                fontSize: 12,
                color: C.red,
                marginTop: 6,
                display: 'flex',
                alignItems: 'center',
                gap: 5,
            }}
        >
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

/** Labelled form field wrapper. */
export function Field({
    label,
    required = false,
    error,
    hint,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label style={fieldLabelStyle}>
                {label} {required && <span style={{ color: C.red }}>*</span>}
            </label>
            {children}
            {hint && !error && (
                <div style={{ fontSize: 11.5, color: C.faint, marginTop: 5 }}>
                    {hint}
                </div>
            )}
            <FieldError message={error} />
        </div>
    );
}

/** Page title block shared by every Layanan Saya screen. */
export function PageHeader({
    title,
    subtitle,
    action,
}: {
    title: string;
    subtitle?: string;
    action?: ReactNode;
}) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'flex-start',
                justifyContent: 'space-between',
                flexWrap: 'wrap',
                gap: 16,
                marginBottom: 22,
            }}
        >
            <div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 7,
                    }}
                >
                    <span>Layanan Saya</span>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{title}</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: 0,
                        letterSpacing: '-.01em',
                    }}
                >
                    {title}
                </h1>
                {subtitle && (
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        {subtitle}
                    </div>
                )}
            </div>
            {action}
        </div>
    );
}

/** Card wrapper with an optional heading row. */
export function Panel({
    title,
    subtitle,
    action,
    children,
    padded = true,
}: {
    title?: string;
    subtitle?: string;
    action?: ReactNode;
    children: ReactNode;
    padded?: boolean;
}) {
    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            {title && (
                <div
                    style={{
                        padding: '16px 18px',
                        borderBottom: `1px solid ${C.border}`,
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        gap: 12,
                    }}
                >
                    <div>
                        <div
                            style={{
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            {title}
                        </div>
                        {subtitle && (
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    marginTop: 2,
                                }}
                            >
                                {subtitle}
                            </div>
                        )}
                    </div>
                    {action}
                </div>
            )}
            <div style={padded ? { padding: '18px' } : undefined}>
                {children}
            </div>
        </div>
    );
}

/** Single figure tile used across the self-service dashboard and lists. */
export function StatCard({
    label,
    value,
    unit,
    icon,
    tone = C.primary,
}: {
    label: string;
    value: string | number;
    unit?: string;
    icon: string;
    tone?: string;
}) {
    return (
        <div style={{ ...card, padding: '18px 20px' }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    marginBottom: 12,
                }}
            >
                <div
                    style={{
                        fontSize: 11.5,
                        fontWeight: 600,
                        letterSpacing: '.05em',
                        textTransform: 'uppercase',
                        color: C.faint,
                    }}
                >
                    {label}
                </div>
                <AIcon name={icon} size={17} color={tone} />
            </div>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'baseline',
                    gap: 6,
                }}
            >
                <div
                    style={{
                        fontSize: 26,
                        fontWeight: 700,
                        color: C.navy,
                        letterSpacing: '-.02em',
                    }}
                >
                    {value}
                </div>
                {unit && (
                    <div style={{ fontSize: 13, color: C.muted }}>{unit}</div>
                )}
            </div>
        </div>
    );
}

/** Indonesian labels + colours for the request status enum. */
const REQUEST_STATUS: Record<string, [string, string]> = {
    pending: ['Menunggu', C.amber],
    approved: ['Disetujui', C.green],
    rejected: ['Ditolak', C.red],
    cancelled: ['Dibatalkan', C.muted],
    canceled: ['Dibatalkan', C.muted],
};

/** Pill for an approval status coming off a request model. */
export function StatusPill({ status }: { status: string }) {
    const [label, color] = REQUEST_STATUS[status] ?? [status, C.muted];

    return <Pill label={label} color={color} />;
}

/** Generic coloured pill. */
export function Pill({ label, color }: { label: string; color: string }) {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                padding: '3px 10px',
                borderRadius: 999,
                fontSize: 11.5,
                fontWeight: 600,
                color,
                background: `${color}1a`,
                whiteSpace: 'nowrap',
            }}
        >
            {label}
        </span>
    );
}

/** Placeholder shown when a list has nothing in it yet. */
export function EmptyState({
    icon,
    message,
}: {
    icon: string;
    message: string;
}) {
    return (
        <div
            style={{
                padding: '44px 18px',
                textAlign: 'center',
                fontSize: 13.5,
                color: C.muted,
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                gap: 10,
            }}
        >
            <AIcon name={icon} size={28} color={C.faint} />
            <div>{message}</div>
        </div>
    );
}

/** d MMM yyyy in Indonesian, tolerant of a null date. */
export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/** Page shell: consistent padding for every Layanan Saya screen. */
export function PageShell({ children }: { children: ReactNode }) {
    return <div style={{ padding: '28px 32px' }}>{children}</div>;
}
