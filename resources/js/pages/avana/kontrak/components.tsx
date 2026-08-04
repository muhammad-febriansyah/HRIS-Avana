import type { CSSProperties, ReactNode } from 'react';
import { useRef, useState } from 'react';
import { AIcon, btnOut, C } from '@/lib/avana';
import { statusPill } from './types';

/* ---------- shared field styles (mirror benefit/components.tsx) ---------- */

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

/** Status pill for a contract status. */
export function StatusPill({ status }: { status: string }) {
    const pill = statusPill(status);

    return (
        <span
            style={{
                display: 'inline-block',
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color: pill.color,
                background: pill.bg,
            }}
        >
            {pill.label}
        </span>
    );
}

/** "Akan berakhir" warning chip shown for soon-to-expire contracts. */
export function ExpiringBadge({ days }: { days: number | null }) {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 4,
                padding: '3px 9px',
                borderRadius: 100,
                fontSize: 11,
                fontWeight: 600,
                color: C.red,
                background: 'rgba(220,38,38,.1)',
            }}
        >
            <AIcon name="triangle-alert" size={11} color={C.red} />
            Akan berakhir ({days} hari)
        </span>
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

/** Human-readable file size: 1.4 MB rather than 1468006. */
export function formatBytes(bytes: number | null | undefined): string {
    if (bytes === null || bytes === undefined || bytes <= 0) {
        return '';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kb = bytes / 1024;

    return kb < 1024
        ? `${kb.toFixed(kb < 10 ? 1 : 0)} KB`
        : `${(kb / 1024).toFixed(1)} MB`;
}

/** The icon that matches a file's kind, from its name. */
function fileIcon(name: string): string {
    return /\.(jpe?g|png)$/i.test(name) ? 'image' : 'file-text';
}

/**
 * The contract document control: a drop area that also takes a click, the file
 * already on record with a way to download or detach it, and the picked file
 * before it is saved.
 *
 * A bare `<input type="file">` was doing all of this before — it showed the
 * browser's own button, said nothing about size, and offered no way to remove a
 * document once attached even though the endpoint existed.
 */
export function DocumentField({
    file,
    onPick,
    existing,
    onRemoveExisting,
    error,
}: {
    /** The newly picked file, before the form is submitted. */
    file: File | null;
    onPick: (file: File | null) => void;
    /** What is already stored, when editing. */
    existing?: { name: string; size?: number | null; href: string } | null;
    /** Detach the stored document; omitted on the create form. */
    onRemoveExisting?: () => void;
    error?: string;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);

    const accept = '.pdf,.jpg,.jpeg,.png';

    const take = (picked: FileList | null) => {
        const next = picked?.[0] ?? null;

        if (next !== null) {
            onPick(next);
        }
    };

    return (
        <div>
            <label style={fieldLabelStyle}>Dokumen Kontrak</label>

            {existing && (
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        padding: '10px 12px',
                        borderRadius: 10,
                        border: `1px solid ${C.border}`,
                        background: '#fff',
                        marginBottom: 10,
                    }}
                >
                    <AIcon
                        name={fileIcon(existing.name)}
                        size={18}
                        color={C.primary}
                    />
                    <div style={{ minWidth: 0, flex: 1 }}>
                        <div
                            style={{
                                fontSize: 13,
                                fontWeight: 500,
                                color: C.text,
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {existing.name}
                        </div>
                        <div style={{ fontSize: 11.5, color: C.faint }}>
                            Tersimpan
                            {formatBytes(existing.size)
                                ? ` · ${formatBytes(existing.size)}`
                                : ''}
                        </div>
                    </div>
                    <a
                        href={existing.href}
                        style={{
                            ...iconBtn,
                            color: C.primary,
                            textDecoration: 'none',
                        }}
                        title="Unduh dokumen"
                    >
                        <AIcon name="download" size={15} color={C.primary} />
                    </a>
                    {onRemoveExisting && (
                        <button
                            type="button"
                            onClick={onRemoveExisting}
                            style={{ ...iconBtn, color: C.red }}
                            title="Hapus dokumen"
                        >
                            <AIcon name="trash-2" size={15} color={C.red} />
                        </button>
                    )}
                </div>
            )}

            <div
                onClick={() => inputRef.current?.click()}
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(event) => {
                    event.preventDefault();
                    setDragging(false);
                    take(event.dataTransfer.files);
                }}
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 12,
                    padding: '14px 16px',
                    borderRadius: 10,
                    border: `1.5px dashed ${error ? C.red : dragging ? C.primary : C.border}`,
                    background: dragging ? 'rgba(47,84,201,.04)' : C.surface,
                    cursor: 'pointer',
                    transition: '.15s',
                }}
            >
                <AIcon
                    name={file ? fileIcon(file.name) : 'upload'}
                    size={20}
                    color={file ? C.primary : C.muted}
                />
                <div style={{ minWidth: 0, flex: 1 }}>
                    <div
                        style={{
                            fontSize: 13,
                            fontWeight: 500,
                            color: file ? C.text : C.muted,
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {file
                            ? file.name
                            : existing
                              ? 'Ganti dokumen — seret berkas ke sini atau klik'
                              : 'Seret berkas ke sini atau klik untuk memilih'}
                    </div>
                    <div style={{ fontSize: 11.5, color: C.faint }}>
                        {file
                            ? `Siap diunggah${formatBytes(file.size) ? ` · ${formatBytes(file.size)}` : ''}`
                            : 'PDF atau gambar (JPG/PNG), maksimal 10 MB. Disimpan privat — hanya bisa diunduh lewat aplikasi.'}
                    </div>
                </div>
                {file && (
                    <button
                        type="button"
                        onClick={(event) => {
                            event.stopPropagation();
                            onPick(null);

                            if (inputRef.current) {
                                inputRef.current.value = '';
                            }
                        }}
                        style={{ ...iconBtn, color: C.muted }}
                        title="Batalkan pilihan"
                    >
                        <AIcon name="x" size={15} color={C.muted} />
                    </button>
                )}
            </div>

            <input
                ref={inputRef}
                type="file"
                accept={accept}
                onChange={(event) => take(event.target.files)}
                style={{ display: 'none' }}
            />
            <FieldError message={error} />
        </div>
    );
}
