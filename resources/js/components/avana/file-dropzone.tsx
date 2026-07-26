import { useRef, useState } from 'react';
import type { DragEvent } from 'react';
import { AIcon, C, hexA } from '@/lib/avana';

/** Bytes as a short human-readable size. */
export function formatFileSize(bytes: number): string {
    if (bytes <= 0) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024).toLocaleString('id-ID')} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`;
}

/** Icon per file kind, so a PDF and a photo do not look identical. */
function iconFor(file: File): string {
    if (file.type.startsWith('image/')) {
        return 'image';
    }

    if (file.type === 'application/pdf') {
        return 'file-text';
    }

    return 'file';
}

/**
 * A drop area that doubles as a file picker: drag a file onto it, or click to
 * browse. Once chosen, the file is shown as a card with its size and a clear
 * button — a bare <input type="file"> gives no room for either.
 *
 * `value`/`onChange` speak File | null, so it drops straight into an Inertia
 * form field.
 */
export function FileDropzone({
    value,
    onChange,
    accept,
    hint,
    hasError = false,
    disabled = false,
}: {
    value: File | null;
    onChange: (file: File | null) => void;
    /** Same syntax as the input's accept attribute, e.g. ".pdf,image/*". */
    accept?: string;
    hint?: string;
    hasError?: boolean;
    disabled?: boolean;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);

    const pick = (file: File | null) => {
        if (disabled) {
            return;
        }

        onChange(file);
    };

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setIsDragging(false);
        pick(event.dataTransfer.files?.[0] ?? null);
    };

    const clear = () => {
        pick(null);

        // Reset the native input too, so re-picking the same file still fires.
        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    if (value !== null) {
        return (
            <>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 12,
                        padding: '12px 14px',
                        border: `1px solid ${hasError ? C.red : C.border}`,
                        borderRadius: 10,
                        background: '#fff',
                    }}
                >
                    <div
                        style={{
                            width: 38,
                            height: 38,
                            flex: 'none',
                            borderRadius: 9,
                            display: 'grid',
                            placeItems: 'center',
                            background: hexA(C.primary, 0.1),
                        }}
                    >
                        <AIcon
                            name={iconFor(value)}
                            size={18}
                            color={C.primary}
                        />
                    </div>

                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div
                            style={{
                                fontSize: 13,
                                fontWeight: 600,
                                color: C.text,
                                whiteSpace: 'nowrap',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                            }}
                        >
                            {value.name}
                        </div>
                        <div style={{ fontSize: 11.5, color: C.faint }}>
                            {formatFileSize(value.size)}
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={clear}
                        disabled={disabled}
                        title="Hapus berkas"
                        aria-label="Hapus berkas"
                        style={{
                            width: 30,
                            height: 30,
                            flex: 'none',
                            display: 'grid',
                            placeItems: 'center',
                            borderRadius: 8,
                            border: `1px solid ${C.border}`,
                            background: '#fff',
                            cursor: disabled ? 'not-allowed' : 'pointer',
                        }}
                    >
                        <AIcon name="x" size={15} color={C.muted} />
                    </button>
                </div>

                <input
                    ref={inputRef}
                    type="file"
                    accept={accept}
                    onChange={(event) => pick(event.target.files?.[0] ?? null)}
                    style={{ display: 'none' }}
                />
            </>
        );
    }

    return (
        <>
            <div
                onClick={() => !disabled && inputRef.current?.click()}
                onDragOver={(event) => {
                    event.preventDefault();
                    setIsDragging(true);
                }}
                onDragLeave={() => setIsDragging(false)}
                onDrop={onDrop}
                role="button"
                tabIndex={0}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        inputRef.current?.click();
                    }
                }}
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: 6,
                    padding: '26px 16px',
                    border: `1.5px dashed ${
                        hasError ? C.red : isDragging ? C.primary : C.border
                    }`,
                    borderRadius: 10,
                    background: isDragging
                        ? hexA(C.primary, 0.05)
                        : hasError
                          ? hexA(C.red, 0.03)
                          : '#FBFCFE',
                    cursor: disabled ? 'not-allowed' : 'pointer',
                    opacity: disabled ? 0.65 : 1,
                    textAlign: 'center',
                    transition: 'border-color .15s, background .15s',
                    outline: 'none',
                }}
            >
                <AIcon
                    name="upload-cloud"
                    size={24}
                    color={isDragging ? C.primary : C.faint}
                />
                <div style={{ fontSize: 13, color: C.text }}>
                    <span style={{ fontWeight: 600, color: C.primary }}>
                        Pilih berkas
                    </span>{' '}
                    atau seret ke sini
                </div>
                {hint && (
                    <div style={{ fontSize: 11.5, color: C.faint }}>{hint}</div>
                )}
            </div>

            <input
                ref={inputRef}
                type="file"
                accept={accept}
                onChange={(event) => pick(event.target.files?.[0] ?? null)}
                style={{ display: 'none' }}
            />
        </>
    );
}
