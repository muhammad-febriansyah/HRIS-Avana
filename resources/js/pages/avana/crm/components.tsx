import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { AIcon, btnOut, btnP, C } from '@/lib/avana';
import { STAGE_COLORS } from './types';

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
    color: C.text,
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
    width: 30,
    height: 30,
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

/** Colored badge describing a deal pipeline stage. */
export function StageBadge({ stage, label }: { stage: string; label: string }) {
    const [color, bg] = STAGE_COLORS[stage] ?? STAGE_COLORS.lead;

    return (
        <span
            style={{
                display: 'inline-block',
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color,
                background: bg,
            }}
        >
            {label}
        </span>
    );
}

interface ModalShellProps {
    title: string;
    subtitle?: string;
    onClose: () => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    processing?: boolean;
    submitLabel?: string;
    children: ReactNode;
}

/** Reusable centered modal with an inline form, cancel + submit buttons. */
export function ModalShell({
    title,
    subtitle,
    onClose,
    onSubmit,
    processing,
    submitLabel = 'Simpan',
    children,
}: ModalShellProps) {
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
                onClick={onClose}
                style={{
                    position: 'absolute',
                    inset: 0,
                    background: 'rgba(14,26,58,.45)',
                }}
            />
            <form
                onSubmit={onSubmit}
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 460,
                    maxHeight: '90vh',
                    overflowY: 'auto',
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    padding: 26,
                    animation: 'toastIn .2s ease',
                }}
            >
                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy, marginBottom: 4 }}>
                    {title}
                </div>
                {subtitle && (
                    <div style={{ fontSize: 13, color: C.muted, marginBottom: 18 }}>{subtitle}</div>
                )}
                <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>{children}</div>
                <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                    <button
                        type="button"
                        onClick={onClose}
                        style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        disabled={processing}
                        style={{
                            ...btnP,
                            flex: 1,
                            height: 44,
                            justifyContent: 'center',
                            opacity: processing ? 0.7 : 1,
                            cursor: processing ? 'not-allowed' : 'pointer',
                        }}
                    >
                        <AIcon name="check" size={16} color="#fff" />
                        {submitLabel}
                    </button>
                </div>
            </form>
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

interface KpiCardProps {
    label: string;
    value: ReactNode;
    icon: string;
    color: string;
}

/** A single KPI summary card. */
export function KpiCard({ label, value, icon, color }: KpiCardProps) {
    return (
        <div
            style={{
                background: '#fff',
                border: `1px solid ${C.border}`,
                borderRadius: 12,
                boxShadow: '0 1px 2px rgba(15,23,42,.04)',
                padding: '18px 20px',
                flex: '1 1 200px',
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                <div
                    style={{
                        width: 34,
                        height: 34,
                        borderRadius: 9,
                        background: `${color}1a`,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <AIcon name={icon} size={17} color={color} />
                </div>
                <span style={{ fontSize: 12.5, color: C.muted, fontWeight: 500 }}>{label}</span>
            </div>
            <div style={{ fontSize: 24, fontWeight: 700, color: C.navy, letterSpacing: '-.02em' }}>
                {value}
            </div>
        </div>
    );
}
