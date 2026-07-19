import { useRef, useState } from 'react';
import { AIcon, btnP, C } from '@/lib/avana';

interface FileDropzoneProps {
    files: File[];
    onChange: (files: File[]) => void;
    /** Accept more than one file; the picker and the wording follow. */
    multiple?: boolean;
    /** Overrides the default "Seret berkas ke sini". */
    label?: string;
    /** The formats line under it. */
    hint?: string;
    accept?: string;
    /** Ignored when `multiple` is false. */
    max?: number;
}

/**
 * Drag-and-drop file picker: an area to drop onto, a button for the file
 * dialog, and the staged files listed with their size so the user can see what
 * is about to be uploaded — and drop one before sending.
 */
export function FileDropzone({
    files,
    onChange,
    multiple = false,
    label,
    hint = 'Format didukung: PDF, JPG, PNG (maks 5 MB)',
    accept = 'image/jpeg,image/png,application/pdf',
    max = 10,
}: FileDropzoneProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);

    const isStaged = (file: File): boolean =>
        files.some(
            (staged) =>
                staged.name === file.name &&
                staged.size === file.size &&
                staged.lastModified === file.lastModified,
        );

    const add = (incoming: FileList | null): void => {
        const picked = Array.from(incoming ?? []);

        if (picked.length === 0) {
            return;
        }

        onChange(
            multiple
                ? [...files, ...picked.filter((f) => !isStaged(f))].slice(0, max)
                : picked.slice(0, 1),
        );

        // Let the same file be re-picked after it is removed; a file input
        // holds its value and ignores an identical second pick otherwise.
        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    const remove = (index: number): void => {
        onChange(files.filter((_, i) => i !== index));
    };

    return (
        <>
            <div
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(event) => {
                    event.preventDefault();
                    setDragging(false);
                    add(event.dataTransfer.files);
                }}
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    gap: 9,
                    padding: '24px 20px',
                    border: `1.5px dashed ${dragging ? C.primary : C.border}`,
                    borderRadius: 10,
                    background: dragging ? '#F5F8FF' : '#FCFDFF',
                    transition: 'border-color .15s, background .15s',
                }}
            >
                <AIcon
                    name="file-up"
                    size={26}
                    color={dragging ? C.primary : C.faint}
                />
                <div
                    style={{ fontSize: 13, fontWeight: 600, color: C.navy }}
                >
                    {label ??
                        (multiple
                            ? 'Seret berkas ke sini'
                            : 'Seret bukti ke sini')}
                </div>
                <div style={{ fontSize: 11.5, color: C.faint }}>{hint}</div>
                <input
                    ref={inputRef}
                    type="file"
                    multiple={multiple}
                    accept={accept}
                    onChange={(event) => add(event.target.files)}
                    style={{ display: 'none' }}
                />
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    style={{
                        ...btnP,
                        height: 32,
                        padding: '0 14px',
                        fontSize: 11.5,
                        letterSpacing: '.03em',
                        textTransform: 'uppercase',
                    }}
                >
                    Pilih Berkas
                </button>
            </div>

            {files.length > 0 && (
                <div
                    style={{
                        marginTop: 10,
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 6,
                    }}
                >
                    {files.map((file, index) => (
                        <div
                            key={`${file.name}-${file.lastModified}`}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                fontSize: 12.5,
                                color: C.text,
                            }}
                        >
                            <AIcon
                                name="file-text"
                                size={14}
                                color={C.primary}
                            />
                            <span
                                style={{
                                    flex: 1,
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                    whiteSpace: 'nowrap',
                                }}
                            >
                                {file.name}
                            </span>
                            <span style={{ fontSize: 11, color: C.faint }}>
                                {formatFileSize(file.size)}
                            </span>
                            <button
                                type="button"
                                onClick={() => remove(index)}
                                title="Hapus berkas"
                                style={{
                                    display: 'flex',
                                    border: 'none',
                                    background: 'none',
                                    padding: 2,
                                    cursor: 'pointer',
                                }}
                            >
                                <AIcon name="x" size={14} color={C.red} />
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}

/** Human-readable byte size for a staged file. */
export function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default FileDropzone;
