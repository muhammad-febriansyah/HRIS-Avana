import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { AIcon, btnOut, btnSave, C } from '@/lib/avana';

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
                        <AIcon name="x" size={16} color={C.text} />
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
export function PageHeader({
    crumb,
    title,
    subtitle,
    actions,
}: PageHeaderProps) {
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
                <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                    {subtitle}
                </div>
            </div>
            {actions && (
                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                    {actions}
                </div>
            )}
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
        <div
            style={{
                display: 'flex',
                flexWrap: 'wrap',
                gap: 14,
                marginBottom: 22,
            }}
        >
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
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                            marginBottom: 10,
                        }}
                    >
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
                            <AIcon
                                name={item.icon}
                                size={17}
                                color={item.color}
                            />
                        </div>
                        <span
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                                fontWeight: 500,
                            }}
                        >
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

/* ---------- modal shell shared by the project and entry forms ---------- */

interface ModalShellProps {
    title: string;
    subtitle?: string;
    width?: number;
    onClose: () => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    children: ReactNode;
}

/** Centered form modal with a scrollable body. */
export function ModalShell({
    title,
    subtitle,
    width = 460,
    onClose,
    onSubmit,
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
                    maxWidth: width,
                    maxHeight: '90vh',
                    overflowY: 'auto',
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    padding: 26,
                    animation: 'toastIn .2s ease',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 14,
                }}
            >
                <div>
                    <div
                        style={{ fontSize: 18, fontWeight: 600, color: C.navy }}
                    >
                        {title}
                    </div>
                    {subtitle && (
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            {subtitle}
                        </div>
                    )}
                </div>
                {children}
            </form>
        </div>
    );
}

/** Cancel / submit footer for {@see ModalShell}. */
export function ModalActions({
    processing,
    onCancel,
    submitLabel = 'Simpan',
}: {
    processing: boolean;
    onCancel: () => void;
    submitLabel?: string;
}) {
    return (
        <div style={{ display: 'flex', gap: 10, marginTop: 8 }}>
            <button
                type="button"
                onClick={onCancel}
                style={{
                    ...btnOut,
                    flex: 1,
                    height: 44,
                    justifyContent: 'center',
                }}
            >
                <AIcon name="x" size={16} color={C.text} />
                Batal
            </button>
            <button
                type="submit"
                disabled={processing}
                style={{
                    ...btnSave,
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
    );
}

/* ---------- table & status presentation ---------- */

/** Standard body-cell padding for the module's tables. */
export const cellStyle: CSSProperties = {
    padding: '13px 16px',
    fontSize: 13,
    color: C.text,
};

/** A pill for an entry's approval state. */
export function StatusBadge({ status, label }: { status: string; label: string }) {
    const tone =
        status === 'approved'
            ? { color: C.green, bg: 'rgba(22,163,74,.1)' }
            : status === 'rejected'
              ? { color: C.red, bg: 'rgba(220,38,38,.1)' }
              : { color: C.amber, bg: 'rgba(217,119,6,.12)' };

    return (
        <span
            style={{
                display: 'inline-block',
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                whiteSpace: 'nowrap',
                color: tone.color,
                background: tone.bg,
            }}
        >
            {label}
        </span>
    );
}

/** The panel switcher above the page content. */
export function TabBar<T extends string>({
    tabs,
    active,
    onChange,
}: {
    tabs: { key: T; label: string; badge?: number }[];
    active: T;
    onChange: (key: T) => void;
}) {
    return (
        <div
            style={{
                display: 'flex',
                gap: 4,
                borderBottom: `1px solid ${C.border}`,
                marginBottom: 20,
                flexWrap: 'wrap',
            }}
        >
            {tabs.map((tab) => {
                const isActive = tab.key === active;

                return (
                    <button
                        key={tab.key}
                        type="button"
                        onClick={() => onChange(tab.key)}
                        style={{
                            border: 'none',
                            background: 'transparent',
                            padding: '10px 16px',
                            fontSize: 13.5,
                            fontWeight: isActive ? 600 : 500,
                            color: isActive ? C.primary : C.muted,
                            borderBottom: `2px solid ${isActive ? C.primary : 'transparent'}`,
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 7,
                        }}
                    >
                        {tab.label}
                        {tab.badge !== undefined && tab.badge > 0 && (
                            <span
                                style={{
                                    minWidth: 20,
                                    height: 20,
                                    padding: '0 6px',
                                    borderRadius: 100,
                                    background: C.amber,
                                    color: '#fff',
                                    fontSize: 11,
                                    fontWeight: 700,
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                {tab.badge}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}

/** Section heading used above each table. */
export function SectionTitle({
    children,
    actions,
}: {
    children: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                flexWrap: 'wrap',
                gap: 12,
                marginBottom: 12,
            }}
        >
            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                {children}
            </div>
            {actions}
        </div>
    );
}

/** Centered "nothing here yet" row for an empty table body. */
export function EmptyRow({
    colSpan,
    icon,
    message,
}: {
    colSpan: number;
    icon: string;
    message: string;
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
