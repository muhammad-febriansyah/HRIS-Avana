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

/** Active/inactive template status pill. */
export function ActivePill({ active }: { active: boolean }) {
    const color = active ? C.green : C.muted;
    const bg = active ? 'rgba(22,163,74,.1)' : 'rgba(107,114,128,.12)';

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
            {active ? 'Aktif' : 'Nonaktif'}
        </span>
    );
}

interface PlaceholderLegendProps {
    placeholders: { token: string; label: string }[];
    /** When provided, pills become buttons that insert the token. */
    onInsert?: (token: string) => void;
}

/** Legend listing the supported `{{token}}` placeholders for a template. */
export function PlaceholderLegend({
    placeholders,
    onInsert,
}: PlaceholderLegendProps) {
    return (
        <div
            style={{
                background: C.surface,
                border: `1px solid ${C.border}`,
                borderRadius: 8,
                padding: '12px 14px',
            }}
        >
            <div
                style={{
                    fontSize: 12,
                    fontWeight: 600,
                    color: C.muted,
                    marginBottom: 8,
                    display: 'flex',
                    alignItems: 'center',
                    gap: 6,
                }}
            >
                <AIcon name="braces" size={13} color={C.muted} />
                Placeholder tersedia
                {onInsert && (
                    <span style={{ color: C.faint, fontWeight: 500 }}>
                        — klik untuk menyisipkan
                    </span>
                )}
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                {placeholders.map((placeholder) => {
                    const pillStyle: CSSProperties = {
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 5,
                        padding: '4px 9px',
                        background: '#fff',
                        border: `1px solid ${C.border}`,
                        borderRadius: 6,
                        fontSize: 12,
                        color: C.text,
                    };

                    const content = (
                        <>
                            <code
                                style={{ color: C.primary, fontWeight: 600 }}
                            >
                                {placeholder.token}
                            </code>
                            <span style={{ color: C.faint }}>
                                {placeholder.label}
                            </span>
                        </>
                    );

                    if (onInsert) {
                        return (
                            <button
                                key={placeholder.token}
                                type="button"
                                title={`Sisipkan ${placeholder.token}`}
                                onClick={() => onInsert(placeholder.token)}
                                style={{
                                    ...pillStyle,
                                    cursor: 'pointer',
                                    font: 'inherit',
                                }}
                            >
                                {content}
                            </button>
                        );
                    }

                    return (
                        <span
                            key={placeholder.token}
                            title={placeholder.label}
                            style={pillStyle}
                        >
                            {content}
                        </span>
                    );
                })}
            </div>
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
export function ConfirmModal({
    title,
    body,
    onCancel,
    onConfirm,
}: ConfirmModalProps) {
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
                style={{
                    position: 'absolute',
                    inset: 0,
                    background: 'rgba(14,26,58,.45)',
                }}
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
                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>
                    {title}
                </div>
                <div
                    style={{
                        fontSize: 13.5,
                        color: C.muted,
                        marginTop: 8,
                        lineHeight: 1.55,
                    }}
                >
                    {body}
                </div>
                <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                    <button
                        onClick={onCancel}
                        style={{
                            ...btnOut,
                            flex: 1,
                            height: 44,
                            justifyContent: 'center',
                        }}
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
