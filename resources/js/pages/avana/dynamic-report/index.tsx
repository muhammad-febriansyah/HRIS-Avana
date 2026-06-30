import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import DynamicReportController from '@/actions/App/Http/Controllers/Avana/DynamicReportController';
import { AIcon, btnOut, btnP, C, card, thCell } from '@/lib/avana';
import { EmptyState, inputStyle, labelStyle } from './components';
import type {
    BuilderFormData,
    DynamicReportIndexProps,
    FlashProps,
    SavedReportRow,
} from './types';

export default function DynamicReportIndex({
    reports,
    entities,
}: DynamicReportIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const [confirm, setConfirm] = useState<SavedReportRow | null>(null);

    const form = useForm<BuilderFormData>({
        name: '',
        entity: entities[0]?.key ?? '',
        columns: entities[0]?.columns.map((column) => column.key) ?? [],
        filters: {},
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const selectedEntity = useMemo(
        () => entities.find((entity) => entity.key === form.data.entity),
        [entities, form.data.entity],
    );

    const changeEntity = (entityKey: string) => {
        const entity = entities.find((item) => item.key === entityKey);

        form.setData({
            ...form.data,
            entity: entityKey,
            columns: entity?.columns.map((column) => column.key) ?? [],
            filters: {},
        });
    };

    const toggleColumn = (columnKey: string) => {
        const next = form.data.columns.includes(columnKey)
            ? form.data.columns.filter((key) => key !== columnKey)
            : [...form.data.columns, columnKey];

        form.setData('columns', next);
    };

    const setFilter = (filterKey: string, value: string) => {
        form.setData('filters', { ...form.data.filters, [filterKey]: value });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(DynamicReportController.store(), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData('name', '');
            },
        });
    };

    const deleteReport = () => {
        if (!confirm) {
            return;
        }

        router.delete(DynamicReportController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Laporan Dinamis" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ marginBottom: 22 }}>
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
                        <span>Beranda</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Laporan Dinamis</span>
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
                        Laporan Dinamis
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Susun laporan kustom dari data HR &amp; simpan untuk
                        digunakan kembali.
                    </div>
                </div>

                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1.3fr',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    {/* Builder */}
                    <form
                        onSubmit={submit}
                        style={{ ...card, padding: '22px 24px' }}
                    >
                        <div
                            style={{
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 18,
                            }}
                        >
                            Buat Laporan
                        </div>

                        <div style={{ marginBottom: 16 }}>
                            <label style={labelStyle}>Nama Laporan</label>
                            <input
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="cth. Karyawan Aktif Q3"
                                style={inputStyle}
                            />
                            {form.errors.name && (
                                <div style={errorStyle}>{form.errors.name}</div>
                            )}
                        </div>

                        <div style={{ marginBottom: 16 }}>
                            <label style={labelStyle}>Entitas</label>
                            <select
                                value={form.data.entity}
                                onChange={(event) =>
                                    changeEntity(event.target.value)
                                }
                                style={inputStyle}
                            >
                                {entities.map((entity) => (
                                    <option key={entity.key} value={entity.key}>
                                        {entity.label}
                                    </option>
                                ))}
                            </select>
                            {form.errors.entity && (
                                <div style={errorStyle}>
                                    {form.errors.entity}
                                </div>
                            )}
                        </div>

                        <div style={{ marginBottom: 16 }}>
                            <label style={labelStyle}>Kolom</label>
                            <div
                                style={{
                                    display: 'flex',
                                    flexWrap: 'wrap',
                                    gap: 8,
                                }}
                            >
                                {selectedEntity?.columns.map((column) => {
                                    const checked = form.data.columns.includes(
                                        column.key,
                                    );

                                    return (
                                        <button
                                            type="button"
                                            key={column.key}
                                            onClick={() =>
                                                toggleColumn(column.key)
                                            }
                                            style={{
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                gap: 6,
                                                height: 34,
                                                padding: '0 12px',
                                                borderRadius: 8,
                                                fontSize: 13,
                                                fontWeight: 500,
                                                cursor: 'pointer',
                                                border: `1px solid ${checked ? C.primary : C.border}`,
                                                background: checked
                                                    ? 'rgba(47,84,201,.08)'
                                                    : '#fff',
                                                color: checked
                                                    ? C.primary
                                                    : C.text,
                                            }}
                                        >
                                            <AIcon
                                                name={
                                                    checked
                                                        ? 'check'
                                                        : 'plus'
                                                }
                                                size={14}
                                                color={
                                                    checked
                                                        ? C.primary
                                                        : C.faint
                                                }
                                            />
                                            {column.label}
                                        </button>
                                    );
                                })}
                            </div>
                            {form.errors.columns && (
                                <div style={errorStyle}>
                                    {form.errors.columns}
                                </div>
                            )}
                        </div>

                        {selectedEntity && selectedEntity.filters.length > 0 && (
                            <div style={{ marginBottom: 16 }}>
                                <label style={labelStyle}>Filter</label>
                                <div
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 12,
                                    }}
                                >
                                    {selectedEntity.filters.map((filter) => (
                                        <div key={filter.key}>
                                            <div
                                                style={{
                                                    fontSize: 12,
                                                    color: C.faint,
                                                    marginBottom: 5,
                                                }}
                                            >
                                                {filter.label}
                                            </div>
                                            {filter.type === 'equals' ? (
                                                <select
                                                    value={
                                                        form.data.filters[
                                                            filter.key
                                                        ] ?? ''
                                                    }
                                                    onChange={(event) =>
                                                        setFilter(
                                                            filter.key,
                                                            event.target.value,
                                                        )
                                                    }
                                                    style={inputStyle}
                                                >
                                                    <option value="">
                                                        Semua
                                                    </option>
                                                    {filter.options.map(
                                                        (option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            ) : (
                                                <input
                                                    value={
                                                        form.data.filters[
                                                            filter.key
                                                        ] ?? ''
                                                    }
                                                    onChange={(event) =>
                                                        setFilter(
                                                            filter.key,
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder={`Cari ${filter.label.toLowerCase()}`}
                                                    style={inputStyle}
                                                />
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={form.processing}
                            style={{
                                ...btnP,
                                width: '100%',
                                justifyContent: 'center',
                                opacity: form.processing ? 0.6 : 1,
                            }}
                        >
                            <AIcon name="save" size={16} color="#fff" />
                            Simpan Laporan
                        </button>
                    </form>

                    {/* Saved reports */}
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <div
                            style={{
                                padding: '18px 22px',
                                borderBottom: `1px solid ${C.line}`,
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Laporan Tersimpan
                        </div>
                        {reports.length === 0 ? (
                            <EmptyState label="Belum ada laporan tersimpan" />
                        ) : (
                            <div style={{ overflowX: 'auto' }}>
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                        fontSize: 13.5,
                                    }}
                                >
                                    <thead>
                                        <tr
                                            style={{
                                                borderBottom: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <th style={thCell}>Nama</th>
                                            <th style={thCell}>Entitas</th>
                                            <th style={thCell}>Kolom</th>
                                            <th
                                                style={{
                                                    ...thCell,
                                                    textAlign: 'right',
                                                }}
                                            >
                                                Aksi
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {reports.map((report) => (
                                            <tr
                                                key={report.id}
                                                style={{
                                                    borderBottom: `1px solid ${C.line}`,
                                                }}
                                            >
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            fontWeight: 600,
                                                            color: C.navy,
                                                        }}
                                                    >
                                                        {report.name}
                                                    </div>
                                                    {report.created_at && (
                                                        <div
                                                            style={{
                                                                fontSize: 12,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            {report.created_at}
                                                        </div>
                                                    )}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                        color: C.text,
                                                    }}
                                                >
                                                    {report.entity_label}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {report.column_labels.length}{' '}
                                                    kolom
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            gap: 8,
                                                            justifyContent:
                                                                'flex-end',
                                                        }}
                                                    >
                                                        <Link
                                                            href={
                                                                DynamicReportController.run(
                                                                    report.id,
                                                                ).url
                                                            }
                                                            style={{
                                                                ...iconBtn,
                                                                color: C.primary,
                                                            }}
                                                            title="Lihat"
                                                        >
                                                            <AIcon
                                                                name="eye"
                                                                size={16}
                                                                color={C.primary}
                                                            />
                                                        </Link>
                                                        <a
                                                            href={
                                                                DynamicReportController.export(
                                                                    report.id,
                                                                ).url
                                                            }
                                                            style={{
                                                                ...iconBtn,
                                                                color: C.green,
                                                            }}
                                                            title="Export CSV"
                                                        >
                                                            <AIcon
                                                                name="download"
                                                                size={16}
                                                                color={C.green}
                                                            />
                                                        </a>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setConfirm(
                                                                    report,
                                                                )
                                                            }
                                                            style={{
                                                                ...iconBtn,
                                                                color: C.red,
                                                            }}
                                                            title="Hapus"
                                                        >
                                                            <AIcon
                                                                name="trash-2"
                                                                size={16}
                                                                color={C.red}
                                                            />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {confirm && (
                <div style={overlay} onClick={() => setConfirm(null)}>
                    <div
                        style={{ ...card, padding: 24, width: 380 }}
                        onClick={(event) => event.stopPropagation()}
                    >
                        <div
                            style={{
                                fontSize: 16,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 8,
                            }}
                        >
                            Hapus laporan?
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginBottom: 20,
                            }}
                        >
                            Laporan &quot;{confirm.name}&quot; akan dihapus
                            permanen.
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                gap: 10,
                                justifyContent: 'flex-end',
                            }}
                        >
                            <button
                                type="button"
                                onClick={() => setConfirm(null)}
                                style={btnOut}
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                onClick={deleteReport}
                                style={{ ...btnP, background: C.red }}
                            >
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

const errorStyle = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
};

const iconBtn = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: 34,
    height: 34,
    borderRadius: 8,
    border: `1px solid ${C.border}`,
    background: '#fff',
    cursor: 'pointer',
    textDecoration: 'none',
};

const overlay = {
    position: 'fixed' as const,
    inset: 0,
    background: 'rgba(14,26,58,.45)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 50,
};
