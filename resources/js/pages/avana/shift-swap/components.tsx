import type { CSSProperties, ReactNode } from 'react';
import { AIcon, btnOut, C } from '@/lib/avana';

/* ---------- shared field styles (mirror rekrutmen/components.tsx) ---------- */

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
    color: C.muted,
    cursor: 'pointer',
};

export const textareaStyle: CSSProperties = {
    width: '100%',
    padding: '11px 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
    resize: 'vertical',
    minHeight: 72,
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

export const iconBtn: CSSProperties = {
    width: 32,
    height: 32,
    border: `1px solid ${C.border}`,
    background: '#fff',
    borderRadius: 8,
    cursor: 'pointer',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: '.15s',
    textDecoration: 'none',
};

/** Apply the red error border to a base style when invalid. */
export function withError(base: CSSProperties, hasError: boolean): CSSProperties {
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

interface ConfirmModalProps {
    title: string;
    body: ReactNode;
    onCancel: () => void;
    onConfirm: () => void;
}

/** Centered destructive-action confirmation modal. */
export function ConfirmModal({ title, body, onCancel, onConfirm }: ConfirmModalProps) {
    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 80,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 20,
            }}
        >
            <div
                onClick={onCancel}
                style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }}
            />
            <div
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 400,
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    padding: 26,
                    animation: 'toastIn .2s ease',
                }}
            >
                <div
                    style={{
                        width: 48,
                        height: 48,
                        borderRadius: 12,
                        background: 'rgba(220,38,38,.1)',
                        color: C.red,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        marginBottom: 16,
                    }}
                >
                    <AIcon name="trash-2" size={22} color={C.red} />
                </div>
                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>{title}</div>
                <div style={{ fontSize: 13.5, color: C.muted, marginTop: 8, lineHeight: 1.55 }}>
                    {body}
                </div>
                <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                    <button
                        onClick={onCancel}
                        style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}
                    >
                        Batal
                    </button>
                    <button
                        onClick={onConfirm}
                        style={{
                            flex: 1,
                            height: 44,
                            background: C.red,
                            color: '#fff',
                            border: 'none',
                            borderRadius: 9,
                            fontSize: 14,
                            fontWeight: 600,
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: 8,
                            transition: '.15s',
                        }}
                    >
                        <AIcon name="trash-2" size={16} />
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    );
}

interface PageHeaderProps {
    crumb: string;
    title: string;
    subtitle: string;
    actions?: ReactNode;
}

/** Standard AvanaHR page header with breadcrumb, title and right-side actions. */
export function PageHeader({ crumb, title, subtitle, actions }: PageHeaderProps) {
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
                    <span>{crumb}</span>
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
                <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>{subtitle}</div>
            </div>
            {actions && <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>{actions}</div>}
        </div>
    );
}

interface KpiItem {
    label: string;
    value: string | number;
    icon: string;
    color: string;
}

/** Row of KPI summary cards. */
export function KpiRow({ items }: { items: KpiItem[] }) {
    return (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginBottom: 22 }}>
            {items.map((item) => (
                <div
                    key={item.label}
                    style={{
                        background: '#fff',
                        border: `1px solid ${C.border}`,
                        borderRadius: 12,
                        boxShadow: '0 1px 2px rgba(15,23,42,.04)',
                        padding: '18px 20px',
                        flex: '1 1 180px',
                    }}
                >
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                        <div
                            style={{
                                width: 34,
                                height: 34,
                                borderRadius: 9,
                                background: `${item.color}1a`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            <AIcon name={item.icon} size={17} color={item.color} />
                        </div>
                        <span style={{ fontSize: 12.5, color: C.muted, fontWeight: 500 }}>
                            {item.label}
                        </span>
                    </div>
                    <div
                        style={{
                            fontSize: 26,
                            fontWeight: 700,
                            color: C.navy,
                            letterSpacing: '-.02em',
                        }}
                    >
                        {item.value}
                    </div>
                </div>
            ))}
        </div>
    );
}
