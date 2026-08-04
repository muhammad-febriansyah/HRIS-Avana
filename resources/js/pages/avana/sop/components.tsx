import type { CSSProperties, ReactNode } from 'react';
import { AIcon, btnOut, btnP, C, hexA } from '@/lib/avana';
import type { SopVisibility } from './types';

/* ---------- shared field styles (mirror dokumen/components.tsx) ---------- */

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
    ...inputStyle,
    height: 'auto',
    minHeight: 84,
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

/** Badge describing who the AI assistant may quote an SOP to. */
export function VisibilityBadge({ visibility }: { visibility: SopVisibility }) {
    const isPublic = visibility === 'public';

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
                color: isPublic ? C.green : C.amber,
                background: isPublic
                    ? 'rgba(22,163,74,.1)'
                    : 'rgba(217,119,6,.1)',
            }}
            title={
                isPublic
                    ? 'Semua karyawan bisa menanyakan SOP ini ke AI Assistant'
                    : 'Hanya pengguna dengan hak akses SOP yang dilayani AI Assistant'
            }
        >
            <AIcon
                name={isPublic ? 'globe' : 'lock'}
                size={11}
                color={isPublic ? C.green : C.amber}
            />
            {isPublic ? 'Public' : 'Private'}
        </span>
    );
}

/** Neutral pill for the active/inactive status column. */
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

/** Marks whether the PDF text was indexed for the AI assistant. */
export function IndexedBadge({ indexed }: { indexed: boolean }) {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 5,
                fontSize: 11.5,
                fontWeight: 600,
                // An unreadable document is a problem to fix, not a neutral
                // state: grey text let it pass for "nothing to see here" while
                // the assistant quietly had nothing to answer from.
                color: indexed ? C.sky : C.amber,
                ...(indexed
                    ? {}
                    : {
                          padding: '3px 9px',
                          borderRadius: 100,
                          background: hexA(C.amber, 0.1),
                          border: `1px solid ${hexA(C.amber, 0.35)}`,
                      }),
            }}
            title={
                indexed
                    ? 'Isi SOP terbaca — AI Assistant dapat menjawab dari dokumen ini'
                    : 'Isi PDF belum terbaca (kemungkinan hasil scan). Isi manual lewat kolom "Isi SOP" agar AI dapat menjawabnya.'
            }
        >
            <AIcon
                name={indexed ? 'sparkles' : 'circle-alert'}
                size={12}
                color={indexed ? C.sky : C.amber}
            />
            {indexed ? 'Terindeks' : 'Belum terbaca'}
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
    submitLabel?: string;
    children: ReactNode;
}

/** Centered form modal shared by the SOP and "Jenis SOP" dialogs. */
export function ModalShell({
    title,
    subtitle,
    width = 560,
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
                    animation: 'toastIn .2s ease',
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
                        aria-label="Konfirmasi hapus"
                        style={{
                            ...btnP,
                            flex: 1,
                            height: 44,
                            justifyContent: 'center',
                            background: C.red,
                        }}
                    >
                        <AIcon name="trash-2" size={16} color="#fff" />
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    );
}
