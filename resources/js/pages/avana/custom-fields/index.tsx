import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import CustomFieldController from '@/actions/App/Http/Controllers/Avana/CustomFieldController';
import { AIcon, btnOut, btnP, C, card, statusBadge, thCell } from '@/lib/avana';

interface CustomFieldRow {
    id: number;
    key: string;
    label: string;
    type: string;
    options: string[];
    is_required: boolean;
    sort_order: number;
    status: string;
}

interface CustomFieldsProps {
    fields: CustomFieldRow[];
    types: string[];
}

interface FlashProps {
    flash?: { success?: string };
    [key: string]: unknown;
}

const TYPE_LABELS: Record<string, string> = {
    text: 'Teks',
    number: 'Angka',
    date: 'Tanggal',
    select: 'Pilihan',
};

export default function CustomFields({ fields, types }: CustomFieldsProps) {
    const { flash } = usePage<FlashProps>().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const form = useForm({
        label: '',
        type: 'text',
        options: '',
        is_required: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(CustomFieldController.store().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const remove = (id: number) => {
        if (confirm('Hapus field kustom ini?')) {
            router.delete(CustomFieldController.destroy(id).url, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Field Kustom Karyawan" />
            <div style={{ padding: '22px 26px' }}>
                <h1
                    style={{
                        fontSize: 20,
                        fontWeight: 700,
                        color: C.navy,
                        marginBottom: 4,
                    }}
                >
                    Field Kustom Karyawan
                </h1>
                <p style={{ fontSize: 13, color: C.faint, marginBottom: 18 }}>
                    Tambah field data karyawan sesuai kebutuhan perusahaan Anda.
                </p>

                <form
                    onSubmit={submit}
                    style={{
                        ...card,
                        padding: 18,
                        marginBottom: 20,
                        display: 'flex',
                        gap: 12,
                        flexWrap: 'wrap',
                        alignItems: 'flex-end',
                    }}
                >
                    <label style={{ flex: '1 1 200px', fontSize: 12.5 }}>
                        <span style={{ color: C.muted }}>Nama Field</span>
                        <input
                            value={form.data.label}
                            onChange={(e) =>
                                form.setData('label', e.target.value)
                            }
                            placeholder="mis. Nomor BPJS"
                            style={inputStyle}
                        />
                        {form.errors.label && (
                            <span style={errStyle}>{form.errors.label}</span>
                        )}
                    </label>
                    <label style={{ flex: '0 1 150px', fontSize: 12.5 }}>
                        <span style={{ color: C.muted }}>Tipe</span>
                        <select
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData('type', e.target.value)
                            }
                            style={inputStyle}
                        >
                            {types.map((t) => (
                                <option key={t} value={t}>
                                    {TYPE_LABELS[t] ?? t}
                                </option>
                            ))}
                        </select>
                    </label>
                    {form.data.type === 'select' && (
                        <label style={{ flex: '1 1 200px', fontSize: 12.5 }}>
                            <span style={{ color: C.muted }}>
                                Pilihan (pisah koma)
                            </span>
                            <input
                                value={form.data.options}
                                onChange={(e) =>
                                    form.setData('options', e.target.value)
                                }
                                placeholder="S, M, L, XL"
                                style={inputStyle}
                            />
                        </label>
                    )}
                    <label
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                            fontSize: 12.5,
                            color: C.muted,
                            paddingBottom: 8,
                        }}
                    >
                        <input
                            type="checkbox"
                            checked={form.data.is_required}
                            onChange={(e) =>
                                form.setData('is_required', e.target.checked)
                            }
                        />
                        Wajib diisi
                    </label>
                    <button
                        type="submit"
                        disabled={form.processing}
                        style={{ ...btnP, marginBottom: 2 }}
                    >
                        <AIcon name="plus" size={16} />
                        Tambah
                    </button>
                </form>

                <div style={{ ...card, overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr>
                                <th style={thCell}>Nama</th>
                                <th style={thCell}>Kunci</th>
                                <th style={thCell}>Tipe</th>
                                <th style={thCell}>Wajib</th>
                                <th style={thCell}>Status</th>
                                <th style={thCell}></th>
                            </tr>
                        </thead>
                        <tbody>
                            {fields.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        style={{
                                            padding: 24,
                                            textAlign: 'center',
                                            color: C.faint,
                                            fontSize: 13,
                                        }}
                                    >
                                        Belum ada field kustom.
                                    </td>
                                </tr>
                            ) : (
                                fields.map((f) => {
                                    const badge = statusBadge(f.status);
                                    return (
                                        <tr
                                            key={f.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td style={tdStyle}>{f.label}</td>
                                            <td
                                                style={{
                                                    ...tdStyle,
                                                    color: C.faint,
                                                    fontFamily: 'monospace',
                                                }}
                                            >
                                                {f.key}
                                            </td>
                                            <td style={tdStyle}>
                                                {TYPE_LABELS[f.type] ?? f.type}
                                                {f.type === 'select' &&
                                                    f.options.length > 0 && (
                                                        <span
                                                            style={{
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            {' '}
                                                            ({f.options.join(', ')})
                                                        </span>
                                                    )}
                                            </td>
                                            <td style={tdStyle}>
                                                {f.is_required ? 'Ya' : 'Tidak'}
                                            </td>
                                            <td style={tdStyle}>
                                                <span
                                                    style={{
                                                        color: badge.color,
                                                        background: badge.bg,
                                                        padding: '2px 8px',
                                                        borderRadius: 6,
                                                        fontSize: 11.5,
                                                    }}
                                                >
                                                    {badge.label}
                                                </span>
                                            </td>
                                            <td style={tdStyle}>
                                                <button
                                                    onClick={() => remove(f.id)}
                                                    style={{
                                                        ...btnOut,
                                                        color: C.red,
                                                        padding: '4px 10px',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="trash-2"
                                                        size={14}
                                                    />
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

const inputStyle: React.CSSProperties = {
    width: '100%',
    height: 38,
    padding: '0 12px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13,
    marginTop: 4,
};

const tdStyle: React.CSSProperties = {
    padding: '11px 14px',
    fontSize: 13,
    color: C.navy,
};

const errStyle: React.CSSProperties = {
    color: C.red,
    fontSize: 11.5,
    marginTop: 3,
    display: 'block',
};
