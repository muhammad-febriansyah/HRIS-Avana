import type { CSSProperties, ReactNode } from 'react';
import { AIcon, btnOut, btnP, C } from '@/lib/avana';
import { COLOR_CHOICES, ICON_CHOICES } from './types';

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
    minHeight: 76,
    padding: '10px 13px',
    lineHeight: 1.55,
    resize: 'vertical',
    fontFamily: 'inherit',
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

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

/** The coloured chip a category renders as, reused by the feed and the table. */
export function CategoryChip({
    name,
    icon,
    color,
}: {
    name: string | null;
    icon: string | null;
    color: string | null;
}) {
    if (!name) {
        return <span style={{ fontSize: 12.5, color: C.faint }}>—</span>;
    }

    const accent = color ?? C.muted;

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
                color: accent,
                background: `${accent}18`,
            }}
        >
            <AIcon name={icon ?? 'sparkles'} size={12} color={accent} />
            {name}
        </span>
    );
}

/** Grid of selectable Lucide icons — typing a name would allow a typo. */
export function IconPicker({
    value,
    onChange,
    accent,
}: {
    value: string;
    onChange: (icon: string) => void;
    accent: string;
}) {
    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(10, 1fr)',
                gap: 6,
            }}
        >
            {ICON_CHOICES.map((icon) => {
                const selected = icon === value;

                return (
                    <button
                        key={icon}
                        type="button"
                        onClick={() => onChange(icon)}
                        title={icon}
                        aria-label={icon}
                        aria-pressed={selected}
                        style={{
                            height: 34,
                            display: 'grid',
                            placeItems: 'center',
                            borderRadius: 8,
                            cursor: 'pointer',
                            background: selected ? `${accent}1a` : '#fff',
                            border: `1px solid ${selected ? accent : C.border}`,
                        }}
                    >
                        <AIcon
                            name={icon}
                            size={16}
                            color={selected ? accent : C.muted}
                        />
                    </button>
                );
            })}
        </div>
    );
}

/** Accent swatches, plus a native picker for anything off-palette. */
export function ColorPicker({
    value,
    onChange,
}: {
    value: string;
    onChange: (color: string) => void;
}) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 7,
                flexWrap: 'wrap',
            }}
        >
            {COLOR_CHOICES.map((color) => (
                <button
                    key={color}
                    type="button"
                    onClick={() => onChange(color)}
                    title={color}
                    aria-label={`Warna ${color}`}
                    aria-pressed={color.toLowerCase() === value.toLowerCase()}
                    style={{
                        width: 28,
                        height: 28,
                        borderRadius: 8,
                        background: color,
                        cursor: 'pointer',
                        border:
                            color.toLowerCase() === value.toLowerCase()
                                ? `2px solid ${C.navy}`
                                : `1px solid ${C.border}`,
                    }}
                />
            ))}
            <input
                type="color"
                value={value}
                aria-label="Warna kustom"
                onChange={(event) => onChange(event.target.value)}
                style={{
                    width: 34,
                    height: 28,
                    padding: 0,
                    border: `1px solid ${C.border}`,
                    borderRadius: 8,
                    background: '#fff',
                    cursor: 'pointer',
                }}
            />
            <span style={{ fontSize: 12, color: C.faint }}>{value}</span>
        </div>
    );
}

export function StatusPill({ status }: { status: string }) {
    const isActive = status === 'active';

    return (
        <span
            style={{
                display: 'inline-block',
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color: isActive ? C.primary : C.muted,
                background: isActive
                    ? 'rgba(47,84,201,.1)'
                    : 'rgba(107,114,128,.12)',
            }}
        >
            {isActive ? 'Aktif' : 'Nonaktif'}
        </span>
    );
}

interface ModalShellProps {
    title: string;
    subtitle?: string;
    width?: number;
    onClose: () => void;
    onSubmit: () => void;
    processing: boolean;
    children: ReactNode;
}

export function ModalShell({
    title,
    subtitle,
    width = 560,
    onClose,
    onSubmit,
    processing,
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
                onSubmit={(event) => {
                    event.preventDefault();
                    onSubmit();
                }}
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: width,
                    maxHeight: '90vh',
                    overflowY: 'auto',
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    padding: 26,
                }}
            >
                <div
                    style={{
                        fontSize: 18,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: subtitle ? 4 : 18,
                    }}
                >
                    {title}
                </div>
                {subtitle ? (
                    <div
                        style={{
                            fontSize: 13,
                            color: C.muted,
                            marginBottom: 18,
                        }}
                    >
                        {subtitle}
                    </div>
                ) : null}

                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 14,
                    }}
                >
                    {children}
                </div>

                <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                    <button
                        type="button"
                        onClick={onClose}
                        style={{
                            ...btnOut,
                            flex: 1,
                            height: 44,
                            justifyContent: 'center',
                        }}
                    >
                        <AIcon name="x" size={16} />
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
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    );
}

export function ConfirmModal({
    title,
    body,
    onCancel,
    onConfirm,
    confirmLabel = 'Hapus',
    icon = 'trash-2',
    tone = C.red,
}: {
    title: string;
    body: ReactNode;
    onCancel: () => void;
    onConfirm: () => void;
    /** Say what the button does — a reopen dialog must not read "Hapus". */
    confirmLabel?: string;
    icon?: string;
    tone?: string;
}) {
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
                }}
            >
                <div
                    style={{
                        width: 48,
                        height: 48,
                        borderRadius: 12,
                        background: `${tone}1a`,
                        display: 'grid',
                        placeItems: 'center',
                        marginBottom: 16,
                    }}
                >
                    <AIcon name={icon} size={22} color={tone} />
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
                        aria-label={`Konfirmasi ${confirmLabel.toLowerCase()}`}
                        style={{
                            ...btnP,
                            flex: 1,
                            height: 44,
                            justifyContent: 'center',
                            background: tone,
                        }}
                    >
                        <AIcon name={icon} size={16} color="#fff" />
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
