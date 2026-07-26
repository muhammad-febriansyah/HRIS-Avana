import { Head, useForm, usePage } from '@inertiajs/react';
import type { ChangeEvent, FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { AIcon, btnP, C, thCell } from '@/lib/avana';
import {
    EmptyState,
    Field,
    formatDate,
    inputStyle,
    PageHeader,
    PageShell,
    Panel,
    selectStyle,
    withError,
} from './components';

interface DocumentRow {
    id: number;
    name: string;
    type: string | null;
    size: number;
    uploaded_at: string | null;
    url: string | null;
}

type FlashProps = { flash?: { success?: string } };

const TYPES = [
    { value: 'kontrak', label: 'Kontrak' },
    { value: 'sertifikat', label: 'Sertifikat' },
    { value: 'identitas', label: 'Identitas' },
    { value: 'medis', label: 'Medis' },
    { value: 'lainnya', label: 'Lainnya' },
];

export default function SayaDokumen({
    documents,
}: {
    documents: DocumentRow[];
}) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<{
        name: string;
        type: string;
        file: File | null;
    }>({ name: '', type: 'lainnya', file: null });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const pickFile = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        form.setData('file', file);

        // Prefill the name from the file so the common case is one click.
        if (file && form.data.name === '') {
            form.setData('name', file.name.replace(/\.[^.]+$/, ''));
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/avana/saya/dokumen', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Dokumen Saya" />
            <PageShell>
                <PageHeader
                    title="Dokumen Saya"
                    subtitle="Berkas pribadimu — kontrak, sertifikat, dan dokumen pendukung lain."
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1.6fr)',
                        gap: 16,
                        alignItems: 'start',
                    }}
                >
                    <form onSubmit={submit}>
                        <Panel title="Unggah Dokumen">
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 14,
                                }}
                            >
                                <Field
                                    label="Berkas"
                                    required
                                    error={form.errors.file}
                                    hint="PDF atau gambar, maksimal 10 MB."
                                >
                                    <input
                                        type="file"
                                        accept=".pdf,image/*"
                                        onChange={pickFile}
                                        style={{
                                            ...withError(
                                                inputStyle,
                                                !!form.errors.file,
                                            ),
                                            height: 'auto',
                                            padding: '10px 12px',
                                        }}
                                    />
                                </Field>

                                <Field
                                    label="Nama Dokumen"
                                    required
                                    error={form.errors.name}
                                >
                                    <input
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="mis. Kontrak Kerja 2026"
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.name,
                                        )}
                                    />
                                </Field>

                                <Field
                                    label="Kategori"
                                    error={form.errors.type}
                                >
                                    <select
                                        value={form.data.type}
                                        onChange={(event) =>
                                            form.setData(
                                                'type',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            selectStyle,
                                            !!form.errors.type,
                                        )}
                                    >
                                        {TYPES.map((type) => (
                                            <option
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>

                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    style={{
                                        ...btnP,
                                        height: 44,
                                        justifyContent: 'center',
                                        opacity: form.processing ? 0.7 : 1,
                                    }}
                                >
                                    <AIcon
                                        name="upload-cloud"
                                        size={16}
                                        color="#fff"
                                    />
                                    {form.processing ? 'Mengunggah…' : 'Unggah'}
                                </button>
                            </div>
                        </Panel>
                    </form>

                    <Panel
                        title="Berkas Saya"
                        subtitle={`${documents.length.toLocaleString('id-ID')} dokumen`}
                        padded={false}
                    >
                        {documents.length === 0 ? (
                            <EmptyState
                                icon="folder"
                                message="Belum ada dokumen."
                            />
                        ) : (
                            <div style={{ overflowX: 'auto' }}>
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                        minWidth: 560,
                                    }}
                                >
                                    <thead>
                                        <tr style={{ background: '#FAFBFD' }}>
                                            <th style={thCell}>Nama</th>
                                            <th style={thCell}>Kategori</th>
                                            <th style={thCell}>Ukuran</th>
                                            <th style={thCell}>Diunggah</th>
                                            <th
                                                style={{
                                                    ...thCell,
                                                    textAlign: 'right',
                                                    padding: '12px 18px',
                                                }}
                                            >
                                                Aksi
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {documents.map((row) => (
                                            <tr
                                                key={row.id}
                                                style={{
                                                    borderTop: `1px solid ${C.line}`,
                                                }}
                                            >
                                                <td
                                                    style={{
                                                        ...cell,
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    {row.name}
                                                </td>
                                                <td style={cell}>
                                                    {row.type ?? '—'}
                                                </td>
                                                <td style={cell}>
                                                    {formatSize(row.size)}
                                                </td>
                                                <td style={cell}>
                                                    {formatDate(
                                                        row.uploaded_at,
                                                    )}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '13px 18px',
                                                        textAlign: 'right',
                                                    }}
                                                >
                                                    {row.url && (
                                                        <a
                                                            href={row.url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            style={{
                                                                display:
                                                                    'inline-flex',
                                                                alignItems:
                                                                    'center',
                                                                gap: 6,
                                                                fontSize: 12.5,
                                                                fontWeight: 600,
                                                                color: C.primary,
                                                                textDecoration:
                                                                    'none',
                                                            }}
                                                        >
                                                            <AIcon
                                                                name="download"
                                                                size={14}
                                                                color={
                                                                    C.primary
                                                                }
                                                            />
                                                            Unduh
                                                        </a>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Panel>
                </div>
            </PageShell>
        </>
    );
}

/** Bytes as a short human-readable size. */
function formatSize(bytes: number): string {
    if (bytes <= 0) {
        return '—';
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024).toLocaleString('id-ID')} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`;
}

const cell = {
    padding: '13px 16px',
    fontSize: 13,
    color: C.text,
} as const;
