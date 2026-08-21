import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import KpiIndicatorController from '@/actions/App/Http/Controllers/Avana/KpiIndicatorController';
import PerformanceController from '@/actions/App/Http/Controllers/Avana/PerformanceController';
import { ActionBtn, AIcon, btnP, C, card } from '@/lib/avana';
import {
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import type { FlashProps, KpiIndicatorRow, SelectOption } from './types';

interface KpiIndicatorFormData {
    name: string;
    unit: string;
    direction: string;
    category: string;
    description: string;
    is_active: boolean;
}

const emptyForm: KpiIndicatorFormData = {
    name: '',
    unit: '',
    direction: 'higher_better',
    category: '',
    description: '',
    is_active: true,
};

interface KpiIndicatorsProps {
    indicators: KpiIndicatorRow[];
    directions: SelectOption[];
}

/** Master "Definisi KPI" admin page: CRUD for the tenant's KPI indicator catalogue. */
export default function KpiIndicators({
    indicators,
    directions,
}: KpiIndicatorsProps) {
    const { flash } = usePage<FlashProps>().props;

    const [editing, setEditing] = useState<KpiIndicatorRow | null>(null);
    const [deleting, setDeleting] = useState<KpiIndicatorRow | null>(null);
    const [showForm, setShowForm] = useState(false);
    const form = useForm<KpiIndicatorFormData>({ ...emptyForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const openCreate = () => {
        form.reset();
        form.clearErrors();
        setEditing(null);
        setShowForm(true);
    };

    const openEdit = (indicator: KpiIndicatorRow) => {
        form.clearErrors();
        form.setData({
            name: indicator.name,
            unit: indicator.unit ?? '',
            direction: indicator.direction,
            category: indicator.category ?? '',
            description: indicator.description ?? '',
            is_active: indicator.is_active,
        });
        setEditing(indicator);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editing) {
            form.submit(KpiIndicatorController.update(editing.id), {
                preserveScroll: true,
                onSuccess: () => closeForm(),
            });
        } else {
            form.submit(KpiIndicatorController.store(), {
                preserveScroll: true,
                onSuccess: () => closeForm(),
            });
        }
    };

    const confirmDelete = () => {
        if (!deleting) {
            return;
        }

        router.delete(KpiIndicatorController.destroy(deleting.id).url, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
        });
    };

    return (
        <>
            <Head title="Definisi KPI" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 14,
                    }}
                >
                    <Link
                        href={PerformanceController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Kinerja
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Definisi KPI</span>
                </div>

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        marginBottom: 24,
                    }}
                >
                    <div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: 0,
                                letterSpacing: '-.01em',
                            }}
                        >
                            Definisi KPI
                        </h1>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Katalog indikator KPI tenant — dipakai saat
                            membangun item KPI pada sebuah penilaian.
                        </div>
                    </div>
                    <button onClick={openCreate} style={{ ...btnP, height: 42 }}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Indikator
                    </button>
                </div>

                <div style={{ ...card }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead>
                                <tr
                                    style={{
                                        borderBottom: `1px solid ${C.line}`,
                                        textAlign: 'left',
                                    }}
                                >
                                    {[
                                        'Nama',
                                        'Satuan',
                                        'Arah',
                                        'Kategori',
                                        'Status',
                                        '',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            style={{
                                                padding: '12px 20px',
                                                fontSize: 12,
                                                fontWeight: 600,
                                                color: C.muted,
                                                textTransform: 'uppercase',
                                                letterSpacing: '.03em',
                                            }}
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {indicators.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            style={{
                                                padding: '32px 20px',
                                                textAlign: 'center',
                                                color: C.muted,
                                                fontSize: 13.5,
                                            }}
                                        >
                                            Belum ada indikator KPI.
                                        </td>
                                    </tr>
                                )}
                                {indicators.map((indicator) => (
                                    <tr
                                        key={indicator.id}
                                        style={{
                                            borderBottom: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: '12px 20px',
                                                fontSize: 13.5,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {indicator.name}
                                            {indicator.description && (
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        fontWeight: 400,
                                                        color: C.faint,
                                                        marginTop: 2,
                                                    }}
                                                >
                                                    {indicator.description}
                                                </div>
                                            )}
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 20px',
                                                fontSize: 13,
                                                color: C.muted,
                                            }}
                                        >
                                            {indicator.unit ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 20px',
                                                fontSize: 13,
                                                color: C.muted,
                                            }}
                                        >
                                            {directions.find(
                                                (d) =>
                                                    d.value ===
                                                    indicator.direction,
                                            )?.label ?? indicator.direction}
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 20px',
                                                fontSize: 13,
                                                color: C.muted,
                                            }}
                                        >
                                            {indicator.category ?? '—'}
                                        </td>
                                        <td style={{ padding: '12px 20px' }}>
                                            <span
                                                style={{
                                                    display: 'inline-block',
                                                    padding: '3px 10px',
                                                    borderRadius: 100,
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                    color: indicator.is_active
                                                        ? C.green
                                                        : C.muted,
                                                    background: indicator.is_active
                                                        ? 'rgba(22,163,74,.1)'
                                                        : 'rgba(107,114,128,.12)',
                                                }}
                                            >
                                                {indicator.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </span>
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 20px',
                                                textAlign: 'right',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    gap: 6,
                                                    justifyContent:
                                                        'flex-end',
                                                }}
                                            >
                                                <ActionBtn
                                                    icon="pencil"
                                                    label="Ubah"
                                                    title="Ubah indikator"
                                                    onClick={() =>
                                                        openEdit(indicator)
                                                    }
                                                />
                                                <ActionBtn
                                                    icon="trash-2"
                                                    label="Hapus"
                                                    variant="danger"
                                                    title="Hapus indikator"
                                                    onClick={() =>
                                                        setDeleting(indicator)
                                                    }
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {showForm && (
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
                        onClick={closeForm}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={handleSubmit}
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 480,
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>
                            {editing ? 'Ubah Indikator' : 'Tambah Indikator'}
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>
                                Nama <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="text"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                style={withError(inputStyle, !!form.errors.name)}
                            />
                            <FieldError message={form.errors.name} />
                        </div>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 14,
                            }}
                        >
                            <div>
                                <label style={fieldLabelStyle}>Satuan</label>
                                <input
                                    type="text"
                                    value={form.data.unit}
                                    onChange={(event) =>
                                        form.setData('unit', event.target.value)
                                    }
                                    placeholder="mis. %, hari, unit"
                                    style={withError(inputStyle, !!form.errors.unit)}
                                />
                                <FieldError message={form.errors.unit} />
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Arah <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={form.data.direction}
                                    onChange={(event) =>
                                        form.setData(
                                            'direction',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.direction,
                                    )}
                                >
                                    {directions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.direction} />
                            </div>
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>Kategori</label>
                            <input
                                type="text"
                                value={form.data.category}
                                onChange={(event) =>
                                    form.setData('category', event.target.value)
                                }
                                placeholder="mis. Produktivitas, Kualitas"
                                style={withError(inputStyle, !!form.errors.category)}
                            />
                            <FieldError message={form.errors.category} />
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>Deskripsi</label>
                            <textarea
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                style={withError(
                                    textareaStyle,
                                    !!form.errors.description,
                                )}
                            />
                            <FieldError message={form.errors.description} />
                        </div>

                        <label
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                fontSize: 13.5,
                                color: C.text,
                                cursor: 'pointer',
                            }}
                        >
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(event) =>
                                    form.setData(
                                        'is_active',
                                        event.target.checked,
                                    )
                                }
                            />
                            Aktif (tersedia untuk dipilih)
                        </label>

                        <div
                            style={{
                                display: 'flex',
                                gap: 10,
                                justifyContent: 'flex-end',
                                marginTop: 6,
                            }}
                        >
                            <button
                                type="button"
                                onClick={closeForm}
                                style={{
                                    height: 42,
                                    padding: '0 16px',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 8,
                                    background: '#fff',
                                    cursor: 'pointer',
                                    fontSize: 13.5,
                                }}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    ...btnP,
                                    height: 42,
                                    opacity: form.processing ? 0.7 : 1,
                                }}
                            >
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {deleting && (
                <ConfirmModal
                    title="Hapus Indikator?"
                    body={
                        <>
                            Indikator <strong>{deleting.name}</strong> akan
                            dihapus permanen.
                        </>
                    }
                    onCancel={() => setDeleting(null)}
                    onConfirm={confirmDelete}
                />
            )}
        </>
    );
}
