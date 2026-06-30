import type { ChangeEvent, CSSProperties, DragEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { AIcon, C } from '@/lib/avana';

/* ---------- shared field styles ---------- */

export const labelStyle: CSSProperties = { display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7, color: C.text };

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

export const textareaStyle: CSSProperties = { ...inputStyle, height: 'auto', padding: '11px 13px', minHeight: 90, resize: 'vertical', lineHeight: 1.5 };

/** Apply the red error border to a base style when invalid. */
export function withError(base: CSSProperties, hasError: boolean): CSSProperties {
    return hasError ? { ...base, border: `1px solid ${C.red}`, boxShadow: '0 0 0 3px rgba(220,38,38,.08)' } : base;
}

/** Inline error message rendered under a field. */
export function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <div style={{ fontSize: 12, color: C.red, marginTop: 6, display: 'flex', alignItems: 'center', gap: 5 }}>
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

interface FieldProps {
    label: string;
    children: ReactNode;
    error?: string;
    hint?: string;
    full?: boolean;
}

/** Labelled form field wrapper with optional hint and error. */
export function Field({ label, children, error, hint, full }: FieldProps) {
    return (
        <div style={{ gridColumn: full ? '1 / -1' : 'auto' }}>
            <label style={labelStyle}>{label}</label>
            {children}
            {hint && !error && <div style={{ fontSize: 12, color: C.faint, marginTop: 6 }}>{hint}</div>}
            <FieldError message={error} />
        </div>
    );
}

interface ImageUploadProps {
    label: string;
    hint: string;
    accept: string;
    file: File | null;
    currentUrl: string | null;
    error?: string;
    square?: boolean;
    onPick: (file: File) => void;
    onClear: () => void;
}

/** Drag-and-drop image picker with live preview; old image is freed server-side. */
export function ImageUpload({ label, hint, accept, file, currentUrl, error, square, onPick, onClear }: ImageUploadProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);

    useEffect(() => {
        if (!file) {
            setPreview(null);

            return;
        }
        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const shown = preview ?? currentUrl;
    const previewBox = square ? 92 : 132;

    const handleFiles = (files: FileList | null) => {
        if (files && files[0]) {
            onPick(files[0]);
        }
    };

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);
        handleFiles(event.dataTransfer.files);
    };

    return (
        <div>
            <label style={labelStyle}>{label}</label>
            <div style={{ display: 'flex', gap: 16, alignItems: 'flex-start', flexWrap: 'wrap' }}>
                {/* Preview */}
                <div
                    style={{
                        width: square ? previewBox : 180,
                        height: previewBox,
                        flex: 'none',
                        borderRadius: 10,
                        border: `1px solid ${C.border}`,
                        background:
                            'repeating-conic-gradient(#F1F3F9 0% 25%, #fff 0% 50%) 50% / 18px 18px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        overflow: 'hidden',
                        position: 'relative',
                    }}
                >
                    {shown ? (
                        <img src={shown} alt={label} style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }} />
                    ) : (
                        <AIcon name="image" size={26} color={C.faint} />
                    )}
                </div>

                {/* Dropzone + actions */}
                <div style={{ flex: 1, minWidth: 220 }}>
                    <div
                        onClick={() => inputRef.current?.click()}
                        onDragOver={(event) => {
                            event.preventDefault();
                            setDragging(true);
                        }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={onDrop}
                        style={{
                            border: `1.5px dashed ${dragging ? C.primary : C.border}`,
                            background: dragging ? 'rgba(47,84,201,.04)' : C.surface,
                            borderRadius: 10,
                            padding: '18px 16px',
                            textAlign: 'center',
                            cursor: 'pointer',
                            transition: '.15s',
                        }}
                    >
                        <AIcon name="upload-cloud" size={22} color={C.primary} style={{ margin: '0 auto 6px' }} />
                        <div style={{ fontSize: 13, color: C.text, fontWeight: 500 }}>
                            Tarik gambar ke sini atau <span style={{ color: C.primary }}>pilih file</span>
                        </div>
                        <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>{hint}</div>
                    </div>
                    <input
                        ref={inputRef}
                        type="file"
                        accept={accept}
                        onChange={(event: ChangeEvent<HTMLInputElement>) => {
                            handleFiles(event.target.files);
                            event.target.value = '';
                        }}
                        style={{ display: 'none' }}
                    />
                    {(shown || file) && (
                        <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
                            <button
                                type="button"
                                onClick={() => inputRef.current?.click()}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    height: 34,
                                    padding: '0 12px',
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    fontSize: 12.5,
                                    fontWeight: 500,
                                    color: C.text,
                                    cursor: 'pointer',
                                }}
                            >
                                <AIcon name="repeat" size={14} />
                                Ganti
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    onClear();
                                    if (inputRef.current) {
                                        inputRef.current.value = '';
                                    }
                                }}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    height: 34,
                                    padding: '0 12px',
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    fontSize: 12.5,
                                    fontWeight: 500,
                                    color: C.red,
                                    cursor: 'pointer',
                                }}
                            >
                                <AIcon name="trash-2" size={14} color={C.red} />
                                Hapus
                            </button>
                        </div>
                    )}
                </div>
            </div>
            <FieldError message={error} />
        </div>
    );
}
