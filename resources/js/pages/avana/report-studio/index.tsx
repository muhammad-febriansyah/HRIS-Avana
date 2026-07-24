import {
    DndContext,
    type DragEndEvent,
    PointerSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { Head, router } from '@inertiajs/react';
import type { ApexOptions } from 'apexcharts';
import { useCallback, useEffect, useRef, useState } from 'react';
import { AIcon, C } from '@/lib/avana';

const CHART_COLORS = [
    '#2547F9',
    '#7c3aed',
    '#0891b2',
    '#16a34a',
    '#d97706',
    '#db2777',
    '#0d9488',
    '#dc2626',
];

type ZoneId = 'rows' | 'columns' | 'values';

interface Dimension {
    key: string;
    label: string;
    group: string;
}

interface Measure {
    key: string;
    label: string;
    group: string;
    format: string;
    aggs: string[];
    default_agg: string;
}

interface ValueField {
    field: string;
    agg: string;
}

interface Template {
    key: string;
    category: string;
    title: string;
    subtitle: string;
    config: { rows: string[]; columns: string[]; values: ValueField[] };
}

interface ResultColumn {
    label: string;
    format: string;
    col_key: string;
    value_index: number;
}

interface ResultRow {
    label: string;
    cells: (number | null)[];
}

interface PivotResult {
    columns: ResultColumn[];
    rows: ResultRow[];
    chart: { label: string; value: number }[];
    meta: { empty?: boolean; truncated?: boolean; row_fields?: string[] };
}

interface SavedReport {
    id: number;
    name: string;
    config: { rows: string[]; columns: string[]; values: ValueField[] };
}

interface Props {
    dimensions: Dimension[];
    measures: Measure[];
    templates: Template[];
    savedReports: SavedReport[];
    canManageFields: boolean;
}

const GROUP_DOT: Record<string, string> = {
    'Data Karyawan': '#d97706',
    Ringkasan: '#2547F9',
    'Absensi & Cuti': '#16a34a',
    Payroll: '#7c3aed',
    'Field Kustom': '#0d9488',
};

const AGG_LABEL: Record<string, string> = { avg: 'Rata-rata', sum: 'Total' };

/** Read a cookie value by name (for the CSRF token on POST requests). */
function cookie(name: string): string {
    const match = document.cookie.match(
        new RegExp('(?:^|; )' + name + '=([^;]*)'),
    );

    return match ? decodeURIComponent(match[1]) : '';
}

function formatCell(value: number | null, format: string): string {
    if (value === null || value === undefined) {
        return '–';
    }
    if (format === 'currency') {
        return 'Rp ' + Math.round(value).toLocaleString('id-ID');
    }
    if (format === 'integer') {
        return Math.round(value).toLocaleString('id-ID');
    }

    return value.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 1,
    });
}

export default function ReportStudio({
    dimensions,
    measures,
    templates,
    savedReports,
    canManageFields,
}: Props) {
    const [rows, setRows] = useState<string[]>([]);
    const [columns, setColumns] = useState<string[]>([]);
    const [values, setValues] = useState<ValueField[]>([]);
    const [view, setView] = useState<'table' | 'chart'>('table');
    const [result, setResult] = useState<PivotResult | null>(null);
    const [loading, setLoading] = useState(false);
    const [saveOpen, setSaveOpen] = useState(false);
    const [reportName, setReportName] = useState('');
    const [saving, setSaving] = useState(false);
    const [fieldOpen, setFieldOpen] = useState(false);
    const [fieldLabel, setFieldLabel] = useState('');
    const [fieldType, setFieldType] = useState<
        'text' | 'number' | 'date' | 'select'
    >('number');
    const [fieldOptions, setFieldOptions] = useState('');
    const [savingField, setSavingField] = useState(false);

    const dimById = Object.fromEntries(dimensions.map((d) => [d.key, d]));
    const measureById = Object.fromEntries(measures.map((m) => [m.key, m]));

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    );

    const abortRef = useRef<AbortController | null>(null);

    const configEmpty = rows.length === 0 || values.length === 0;

    // Debounced live preview whenever the pivot config changes.
    useEffect(() => {
        if (configEmpty) {
            setResult(null);

            return;
        }

        const handle = setTimeout(() => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;
            setLoading(true);

            fetch('/avana/report-studio/run', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': cookie('XSRF-TOKEN'),
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                signal: controller.signal,
                body: JSON.stringify({
                    payload: JSON.stringify({ rows, columns, values }),
                }),
            })
                .then((res) => res.json())
                .then((data: PivotResult) => setResult(data))
                .catch(() => {})
                .finally(() => setLoading(false));
        }, 320);

        return () => clearTimeout(handle);
    }, [rows, columns, values, configEmpty]);

    const addField = useCallback(
        (zone: ZoneId, kind: 'dim' | 'measure', key: string) => {
            if (zone === 'values') {
                if (kind !== 'measure') {
                    return;
                }
                setValues((prev) =>
                    prev.some((v) => v.field === key)
                        ? prev
                        : [
                              ...prev,
                              { field: key, agg: measureById[key].default_agg },
                          ],
                );

                return;
            }

            if (kind !== 'dim') {
                return;
            }
            const setter = zone === 'rows' ? setRows : setColumns;
            const other = zone === 'rows' ? columns : rows;
            if (other.includes(key)) {
                return;
            }
            setter((prev) => (prev.includes(key) ? prev : [...prev, key]));
        },
        [columns, rows, measureById],
    );

    const onDragEnd = (event: DragEndEvent) => {
        const zone = event.over?.data.current?.zone as ZoneId | undefined;
        const active = event.active.data.current as
            | { kind: 'dim' | 'measure'; key: string }
            | undefined;
        if (!zone || !active) {
            return;
        }
        addField(zone, active.kind, active.key);
    };

    const removeRow = (key: string) =>
        setRows((p) => p.filter((k) => k !== key));
    const removeColumn = (key: string) =>
        setColumns((p) => p.filter((k) => k !== key));
    const removeValue = (field: string) =>
        setValues((p) => p.filter((v) => v.field !== field));
    const changeAgg = (field: string, agg: string) =>
        setValues((p) => p.map((v) => (v.field === field ? { ...v, agg } : v)));

    const applyConfig = (config: {
        rows: string[];
        columns: string[];
        values: ValueField[];
    }) => {
        setRows(config.rows);
        setColumns(config.columns);
        setValues(config.values);
    };

    const reset = () => {
        setRows([]);
        setColumns([]);
        setValues([]);
        setResult(null);
    };

    const saveReport = () => {
        const name = reportName.trim();
        if (!name || configEmpty || saving) {
            return;
        }
        setSaving(true);
        router.post(
            '/avana/report-studio/reports',
            { name, payload: JSON.stringify({ rows, columns, values }) },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['savedReports'],
                onSuccess: () => {
                    setSaveOpen(false);
                    setReportName('');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const deleteReport = (id: number) => {
        router.delete(`/avana/report-studio/reports/${id}`, {
            preserveScroll: true,
            preserveState: true,
            only: ['savedReports'],
        });
    };

    // Create a tenant custom field (master data) inline; the palette refreshes
    // via a partial reload of the dimensions/measures props.
    const createField = () => {
        const label = fieldLabel.trim();
        if (!label || savingField) {
            return;
        }
        setSavingField(true);
        router.post(
            '/avana/custom-fields',
            {
                label,
                type: fieldType,
                options: fieldType === 'select' ? fieldOptions : null,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['dimensions', 'measures'],
                onSuccess: () => {
                    setFieldOpen(false);
                    setFieldLabel('');
                    setFieldOptions('');
                    setFieldType('number');
                },
                onFinish: () => setSavingField(false),
            },
        );
    };

    const exportExcel = () => {
        // Navigate to the download endpoint so the server's Content-Disposition
        // names the .xlsx file — a blob download loses the filename/extension.
        const payload = encodeURIComponent(
            JSON.stringify({ rows, columns, values }),
        );
        window.location.href = `/avana/report-studio/export?payload=${payload}`;
    };

    const dimensionGroups = groupBy(dimensions, (d) => d.group);
    const measureGroups = groupBy(measures, (m) => m.group);

    // Highlight the template whose config matches the current builder state, so
    // it stays visibly "active" until the user edits a field away from it.
    const activeTemplate =
        templates.find((template) =>
            sameConfig(template.config, { rows, columns, values }),
        )?.key ?? null;

    return (
        <>
            <Head title="Report Studio" />
            <div style={{ padding: '24px 28px', maxWidth: 1280 }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        gap: 16,
                        marginBottom: 20,
                    }}
                >
                    <div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 700,
                                color: C.navy,
                                margin: 0,
                            }}
                        >
                            Report Studio
                        </h1>
                        <p
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                margin: '6px 0 0',
                            }}
                        >
                            Susun laporan dengan drag and drop, atau mulai dari
                            template.
                        </p>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexShrink: 0 }}>
                        <button onClick={reset} style={btnGhost}>
                            Reset
                        </button>
                        <button
                            onClick={() => setSaveOpen(true)}
                            disabled={configEmpty}
                            style={{
                                ...btnGhost,
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 7,
                                opacity: configEmpty ? 0.5 : 1,
                                cursor: configEmpty ? 'not-allowed' : 'pointer',
                            }}
                        >
                            <AIcon
                                name="bookmark"
                                size={15}
                                color={C.text}
                            />
                            Simpan
                        </button>
                        <button
                            onClick={exportExcel}
                            disabled={configEmpty}
                            style={{
                                ...btnPrimary,
                                opacity: configEmpty ? 0.5 : 1,
                                cursor: configEmpty ? 'not-allowed' : 'pointer',
                            }}
                        >
                            <AIcon name="download" size={15} color="#fff" />
                            Export Excel
                        </button>
                    </div>
                </div>

                {/* templates */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4, 1fr)',
                        gap: 12,
                        marginBottom: 18,
                    }}
                >
                    {templates.map((template) => {
                        const active = template.key === activeTemplate;

                        return (
                            <button
                                key={template.key}
                                onClick={() => applyConfig(template.config)}
                                style={{
                                    ...templateCard,
                                    ...(active
                                        ? {
                                              border: `1.5px solid ${C.primary}`,
                                              background: 'rgba(47,84,201,.05)',
                                              boxShadow:
                                                  '0 0 0 3px rgba(47,84,201,.08)',
                                          }
                                        : {}),
                                }}
                            >
                                <span
                                    style={{
                                        ...categoryTag,
                                        ...(active
                                            ? {
                                                  color: C.primary,
                                                  background:
                                                      'rgba(47,84,201,.12)',
                                              }
                                            : {}),
                                    }}
                                >
                                    {template.category}
                                    {active ? ' · aktif' : ''}
                                </span>
                                <span
                                    style={{
                                        fontSize: 14.5,
                                        fontWeight: 700,
                                        color: active ? C.primary : C.navy,
                                        lineHeight: 1.3,
                                    }}
                                >
                                    {template.title}
                                </span>
                                <span style={{ fontSize: 12, color: C.muted }}>
                                    {template.subtitle}
                                </span>
                            </button>
                        );
                    })}
                </div>

                {savedReports.length > 0 ? (
                    <div style={{ marginBottom: 18 }}>
                        <div
                            style={{
                                fontSize: 11.5,
                                fontWeight: 700,
                                color: C.faint,
                                textTransform: 'uppercase',
                                letterSpacing: '.05em',
                                marginBottom: 10,
                            }}
                        >
                            Laporan Tersimpan
                        </div>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: 'repeat(4, 1fr)',
                                gap: 12,
                            }}
                        >
                            {savedReports.map((report) => {
                                const active = sameConfig(report.config, {
                                    rows,
                                    columns,
                                    values,
                                });

                                return (
                                    <div
                                        key={report.id}
                                        style={{
                                            ...templateCard,
                                            gap: 6,
                                            position: 'relative',
                                            ...(active
                                                ? {
                                                      border: `1.5px solid ${C.primary}`,
                                                      background:
                                                          'rgba(47,84,201,.05)',
                                                  }
                                                : {}),
                                        }}
                                        onClick={() =>
                                            applyConfig(report.config)
                                        }
                                    >
                                        <span
                                            style={{
                                                ...categoryTag,
                                                color: '#0891b2',
                                                background:
                                                    'rgba(8,145,178,.1)',
                                            }}
                                        >
                                            Tersimpan
                                        </span>
                                        <span
                                            style={{
                                                fontSize: 14,
                                                fontWeight: 700,
                                                color: active
                                                    ? C.primary
                                                    : C.navy,
                                                lineHeight: 1.3,
                                                paddingRight: 18,
                                            }}
                                        >
                                            {report.name}
                                        </span>
                                        <span
                                            style={{
                                                fontSize: 11.5,
                                                color: C.muted,
                                            }}
                                        >
                                            {summariseConfig(
                                                report.config,
                                                dimensions,
                                                measures,
                                            )}
                                        </span>
                                        <button
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                deleteReport(report.id);
                                            }}
                                            title="Hapus"
                                            style={{
                                                position: 'absolute',
                                                top: 12,
                                                right: 12,
                                                border: 'none',
                                                background: 'transparent',
                                                cursor: 'pointer',
                                                padding: 2,
                                                display: 'inline-flex',
                                            }}
                                        >
                                            <AIcon
                                                name="trash-2"
                                                size={14}
                                                color={C.faint}
                                            />
                                        </button>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ) : null}

                <DndContext sensors={sensors} onDragEnd={onDragEnd}>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '250px 1fr',
                            gap: 18,
                            background: '#fff',
                            border: `1px solid ${C.border}`,
                            borderRadius: 16,
                            padding: 18,
                        }}
                    >
                        {/* palette */}
                        <div>
                            {canManageFields ? (
                            <button
                                onClick={() => setFieldOpen(true)}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 6,
                                    width: '100%',
                                    padding: '9px 12px',
                                    marginBottom: 16,
                                    border: `1px dashed ${C.primary}`,
                                    borderRadius: 9,
                                    background: 'rgba(47,84,201,.04)',
                                    color: C.primary,
                                    fontSize: 12.5,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                }}
                            >
                                <AIcon name="plus" size={14} color={C.primary} />
                                Tambah Field
                            </button>
                            ) : null}
                            {Object.entries(dimensionGroups).map(
                                ([group, items]) => (
                                    <PaletteGroup key={group} title={group}>
                                        {items.map((dim) => (
                                            <PaletteField
                                                key={dim.key}
                                                id={`dim:${dim.key}`}
                                                kind="dim"
                                                fieldKey={dim.key}
                                                label={dim.label}
                                                dot={
                                                    GROUP_DOT[dim.group] ??
                                                    C.muted
                                                }
                                                onAdd={() =>
                                                    addField(
                                                        'rows',
                                                        'dim',
                                                        dim.key,
                                                    )
                                                }
                                            />
                                        ))}
                                    </PaletteGroup>
                                ),
                            )}
                            {Object.entries(measureGroups).map(
                                ([group, items]) => (
                                    <PaletteGroup key={group} title={group}>
                                        {items.map((measure) => (
                                            <PaletteField
                                                key={measure.key}
                                                id={`measure:${measure.key}`}
                                                kind="measure"
                                                fieldKey={measure.key}
                                                label={measure.label}
                                                badge={badgeFor(measure)}
                                                dot={
                                                    GROUP_DOT[measure.group] ??
                                                    C.muted
                                                }
                                                onAdd={() =>
                                                    addField(
                                                        'values',
                                                        'measure',
                                                        measure.key,
                                                    )
                                                }
                                            />
                                        ))}
                                    </PaletteGroup>
                                ),
                            )}
                        </div>

                        {/* builder + preview */}
                        <div>
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: 'repeat(3, 1fr)',
                                    gap: 12,
                                    marginBottom: 18,
                                }}
                            >
                                <DropZone
                                    zone="rows"
                                    title="ROWS"
                                    hint="Drop field"
                                >
                                    {rows.map((key) => (
                                        <Chip
                                            key={key}
                                            label={dimById[key]?.label ?? key}
                                            onRemove={() => removeRow(key)}
                                        />
                                    ))}
                                </DropZone>
                                <DropZone
                                    zone="columns"
                                    title="COLUMNS"
                                    hint="Drop field (opsional)"
                                >
                                    {columns.map((key) => (
                                        <Chip
                                            key={key}
                                            label={dimById[key]?.label ?? key}
                                            onRemove={() => removeColumn(key)}
                                        />
                                    ))}
                                </DropZone>
                                <DropZone
                                    zone="values"
                                    title="VALUES"
                                    hint="Drop field angka"
                                >
                                    {values.map((value) => (
                                        <ValueChip
                                            key={value.field}
                                            label={
                                                measureById[value.field]
                                                    ?.label ?? value.field
                                            }
                                            value={value}
                                            aggs={
                                                measureById[value.field]?.aggs ??
                                                []
                                            }
                                            onAgg={(agg) =>
                                                changeAgg(value.field, agg)
                                            }
                                            onRemove={() =>
                                                removeValue(value.field)
                                            }
                                        />
                                    ))}
                                </DropZone>
                            </div>

                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    marginBottom: 12,
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 7,
                                        fontSize: 12.5,
                                        color: C.muted,
                                    }}
                                >
                                    <span
                                        style={{
                                            width: 7,
                                            height: 7,
                                            borderRadius: 99,
                                            background: loading
                                                ? '#d97706'
                                                : '#16a34a',
                                        }}
                                    />
                                    {loading ? 'Menghitung…' : 'Preview langsung'}
                                </div>
                                <div style={toggleWrap}>
                                    <button
                                        onClick={() => setView('table')}
                                        style={toggleBtn(view === 'table')}
                                    >
                                        Tabel
                                    </button>
                                    <button
                                        onClick={() => setView('chart')}
                                        style={toggleBtn(view === 'chart')}
                                    >
                                        Chart
                                    </button>
                                </div>
                            </div>

                            <Preview
                                view={view}
                                result={result}
                                configEmpty={configEmpty}
                            />
                        </div>
                    </div>
                </DndContext>

                <p
                    style={{
                        textAlign: 'center',
                        fontSize: 11.5,
                        color: C.faint,
                        marginTop: 16,
                    }}
                >
                    Data dihitung langsung dari data karyawan tenant Anda.
                </p>
            </div>

            {saveOpen ? (
                <div
                    onClick={() => setSaveOpen(false)}
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(15,23,42,.35)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 50,
                    }}
                >
                    <div
                        onClick={(e) => e.stopPropagation()}
                        style={{
                            background: '#fff',
                            borderRadius: 16,
                            padding: 22,
                            width: 400,
                            maxWidth: '90vw',
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                        }}
                    >
                        <div
                            style={{
                                fontSize: 16,
                                fontWeight: 700,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Simpan Laporan
                        </div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                                marginBottom: 16,
                            }}
                        >
                            Simpan susunan ini agar bisa dibuka lagi kapan saja.
                        </div>
                        <input
                            autoFocus
                            value={reportName}
                            onChange={(e) => setReportName(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    saveReport();
                                }
                            }}
                            placeholder="Nama laporan, mis. Headcount Divisi"
                            style={{
                                width: '100%',
                                border: `1px solid ${C.border}`,
                                borderRadius: 10,
                                padding: '11px 14px',
                                fontSize: 14,
                                color: C.text,
                                outline: 'none',
                                boxSizing: 'border-box',
                            }}
                        />
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                gap: 10,
                                marginTop: 18,
                            }}
                        >
                            <button
                                onClick={() => setSaveOpen(false)}
                                style={btnGhost}
                            >
                                Batal
                            </button>
                            <button
                                onClick={saveReport}
                                disabled={reportName.trim() === '' || saving}
                                style={{
                                    ...btnPrimary,
                                    opacity:
                                        reportName.trim() === '' || saving
                                            ? 0.5
                                            : 1,
                                    cursor:
                                        reportName.trim() === '' || saving
                                            ? 'not-allowed'
                                            : 'pointer',
                                }}
                            >
                                {saving ? 'Menyimpan…' : 'Simpan'}
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}

            {fieldOpen ? (
                <div
                    onClick={() => setFieldOpen(false)}
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(15,23,42,.35)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 50,
                    }}
                >
                    <div
                        onClick={(e) => e.stopPropagation()}
                        style={{
                            background: '#fff',
                            borderRadius: 16,
                            padding: 22,
                            width: 420,
                            maxWidth: '90vw',
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                        }}
                    >
                        <div
                            style={{
                                fontSize: 16,
                                fontWeight: 700,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Tambah Field
                        </div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                                marginBottom: 16,
                            }}
                        >
                            Buat field data karyawan baru. Field langsung tersedia
                            di palette laporan.
                        </div>
                        <label style={fieldFormLabel}>Nama Field</label>
                        <input
                            autoFocus
                            value={fieldLabel}
                            onChange={(e) => setFieldLabel(e.target.value)}
                            placeholder="mis. Skor Loyalitas"
                            style={fieldFormInput}
                        />
                        <label style={{ ...fieldFormLabel, marginTop: 14 }}>
                            Tipe
                        </label>
                        <select
                            value={fieldType}
                            onChange={(e) =>
                                setFieldType(
                                    e.target.value as
                                        | 'text'
                                        | 'number'
                                        | 'date'
                                        | 'select',
                                )
                            }
                            style={fieldFormInput}
                        >
                            <option value="number">Angka (bisa dihitung)</option>
                            <option value="text">Teks</option>
                            <option value="date">Tanggal</option>
                            <option value="select">Pilihan</option>
                        </select>
                        {fieldType === 'select' ? (
                            <>
                                <label
                                    style={{ ...fieldFormLabel, marginTop: 14 }}
                                >
                                    Opsi (pisah dengan koma)
                                </label>
                                <input
                                    value={fieldOptions}
                                    onChange={(e) =>
                                        setFieldOptions(e.target.value)
                                    }
                                    placeholder="mis. Rendah, Sedang, Tinggi"
                                    style={fieldFormInput}
                                />
                            </>
                        ) : null}
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                gap: 10,
                                marginTop: 20,
                            }}
                        >
                            <button
                                onClick={() => setFieldOpen(false)}
                                style={btnGhost}
                            >
                                Batal
                            </button>
                            <button
                                onClick={createField}
                                disabled={fieldLabel.trim() === '' || savingField}
                                style={{
                                    ...btnPrimary,
                                    opacity:
                                        fieldLabel.trim() === '' || savingField
                                            ? 0.5
                                            : 1,
                                    cursor:
                                        fieldLabel.trim() === '' || savingField
                                            ? 'not-allowed'
                                            : 'pointer',
                                }}
                            >
                                {savingField ? 'Menyimpan…' : 'Tambah Field'}
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}
        </>
    );
}

function badgeFor(measure: Measure): string {
    if (measure.key === 'masa_kerja') {
        return 'tahun';
    }
    if (measure.format === 'currency') {
        return 'Rp';
    }

    return 'angka';
}

/** True when two pivot configs describe the same report. */
function sameConfig(
    a: { rows: string[]; columns: string[]; values: ValueField[] },
    b: { rows: string[]; columns: string[]; values: ValueField[] },
): boolean {
    const key = (c: {
        rows: string[];
        columns: string[];
        values: ValueField[];
    }) =>
        JSON.stringify([
            c.rows,
            c.columns,
            c.values.map((v) => [v.field, v.agg]),
        ]);

    return key(a) === key(b);
}

/** A one-line "Departemen × Status → Rata-rata Gaji" summary of a config. */
function summariseConfig(
    config: { rows: string[]; columns: string[]; values: ValueField[] },
    dimensions: Dimension[],
    measures: Measure[],
): string {
    const dimLabel = (key: string) =>
        dimensions.find((d) => d.key === key)?.label ?? key;
    const valueLabel = (value: ValueField) => {
        const measure = measures.find((m) => m.key === value.field);
        const name = measure?.label ?? value.field;
        if (value.field === 'count' || value.field === 'resign') {
            return name;
        }

        return (value.agg === 'avg' ? 'Rata-rata ' : 'Total ') + name;
    };

    const left = config.rows.map(dimLabel).join(', ');
    const cols = config.columns.length
        ? ' × ' + config.columns.map(dimLabel).join(', ')
        : '';
    const vals = config.values.map(valueLabel).join(', ');

    return `${left}${cols} → ${vals}`;
}

function groupBy<T>(items: T[], key: (item: T) => string): Record<string, T[]> {
    return items.reduce<Record<string, T[]>>((acc, item) => {
        const k = key(item);
        (acc[k] ??= []).push(item);

        return acc;
    }, {});
}

function PaletteGroup({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div style={{ marginBottom: 16 }}>
            <div
                style={{
                    fontSize: 10.5,
                    fontWeight: 700,
                    color: C.faint,
                    textTransform: 'uppercase',
                    letterSpacing: '.05em',
                    marginBottom: 8,
                }}
            >
                {title}
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 7 }}>
                {children}
            </div>
        </div>
    );
}

function PaletteField({
    id,
    kind,
    fieldKey,
    label,
    dot,
    badge,
    onAdd,
}: {
    id: string;
    kind: 'dim' | 'measure';
    fieldKey: string;
    label: string;
    dot: string;
    badge?: string;
    onAdd: () => void;
}) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id,
        data: { kind, key: fieldKey },
    });

    return (
        <div
            ref={setNodeRef}
            {...listeners}
            {...attributes}
            onClick={onAdd}
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 9,
                padding: '9px 12px',
                border: `1px solid ${C.border}`,
                borderRadius: 9,
                background: '#fff',
                cursor: 'grab',
                opacity: isDragging ? 0.4 : 1,
                userSelect: 'none',
            }}
        >
            <span
                style={{
                    width: 8,
                    height: 8,
                    borderRadius: 99,
                    background: dot,
                    flexShrink: 0,
                }}
            />
            <span
                style={{
                    fontSize: 13,
                    color: C.text,
                    fontWeight: 500,
                    flex: 1,
                }}
            >
                {label}
            </span>
            {badge ? (
                <span
                    style={{
                        fontSize: 10.5,
                        color: C.faint,
                        fontWeight: 500,
                    }}
                >
                    {badge}
                </span>
            ) : null}
        </div>
    );
}

function DropZone({
    zone,
    title,
    hint,
    children,
}: {
    zone: ZoneId;
    title: string;
    hint: string;
    children: React.ReactNode;
}) {
    const { setNodeRef, isOver } = useDroppable({
        id: `zone:${zone}`,
        data: { zone },
    });
    const hasChildren = Array.isArray(children)
        ? children.length > 0
        : Boolean(children);

    return (
        <div
            ref={setNodeRef}
            style={{
                border: `1.5px dashed ${isOver ? C.primary : C.border}`,
                borderRadius: 12,
                background: isOver ? 'rgba(47,84,201,.05)' : '#fafbfe',
                padding: 12,
                minHeight: 96,
            }}
        >
            <div
                style={{
                    fontSize: 11,
                    fontWeight: 700,
                    color: C.muted,
                    letterSpacing: '.04em',
                    marginBottom: 8,
                }}
            >
                {title}
            </div>
            {hasChildren ? (
                <div
                    style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}
                >
                    {children}
                </div>
            ) : (
                <div style={{ fontSize: 12.5, color: C.faint }}>{hint}</div>
            )}
        </div>
    );
}

function Chip({ label, onRemove }: { label: string; onRemove: () => void }) {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 6,
                padding: '5px 8px 5px 10px',
                background: 'rgba(47,84,201,.08)',
                color: C.primary,
                borderRadius: 8,
                fontSize: 12.5,
                fontWeight: 600,
            }}
        >
            {label}
            <button
                onClick={onRemove}
                style={{
                    border: 'none',
                    background: 'transparent',
                    cursor: 'pointer',
                    display: 'inline-flex',
                    padding: 0,
                    color: C.primary,
                }}
            >
                <AIcon name="x" size={13} color={C.primary} />
            </button>
        </span>
    );
}

function ValueChip({
    label,
    value,
    aggs,
    onAgg,
    onRemove,
}: {
    label: string;
    value: ValueField;
    aggs: string[];
    onAgg: (agg: string) => void;
    onRemove: () => void;
}) {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 6,
                padding: '4px 6px 4px 10px',
                background: 'rgba(124,58,237,.09)',
                color: '#6d28d9',
                borderRadius: 8,
                fontSize: 12.5,
                fontWeight: 600,
            }}
        >
            {label}
            {aggs.length > 1 ? (
                <select
                    value={value.agg}
                    onChange={(e) => onAgg(e.target.value)}
                    style={{
                        border: `1px solid rgba(124,58,237,.3)`,
                        borderRadius: 6,
                        background: '#fff',
                        color: '#6d28d9',
                        fontSize: 11,
                        fontWeight: 600,
                        padding: '2px 4px',
                        cursor: 'pointer',
                    }}
                >
                    {aggs.map((agg) => (
                        <option key={agg} value={agg}>
                            {AGG_LABEL[agg] ?? agg}
                        </option>
                    ))}
                </select>
            ) : null}
            <button
                onClick={onRemove}
                style={{
                    border: 'none',
                    background: 'transparent',
                    cursor: 'pointer',
                    display: 'inline-flex',
                    padding: 0,
                    color: '#6d28d9',
                }}
            >
                <AIcon name="x" size={13} color="#6d28d9" />
            </button>
        </span>
    );
}

function Preview({
    view,
    result,
    configEmpty,
}: {
    view: 'table' | 'chart';
    result: PivotResult | null;
    configEmpty: boolean;
}) {
    if (configEmpty || !result || result.rows.length === 0) {
        return (
            <div
                style={{
                    padding: '48px 20px',
                    textAlign: 'center',
                    fontSize: 13,
                    color: C.faint,
                    border: `1px solid ${C.line}`,
                    borderRadius: 12,
                }}
            >
                Drop field ke Rows dan Values, atau klik salah satu template di
                atas.
            </div>
        );
    }

    if (view === 'chart') {
        return <PreviewChart result={result} />;
    }

    return <PreviewTable result={result} />;
}

function PreviewTable({ result }: { result: PivotResult }) {
    const rowHeader = (result.meta.row_fields ?? []).join(' / ') || 'Baris';

    return (
        <div style={{ overflowX: 'auto', border: `1px solid ${C.border}`, borderRadius: 12 }}>
            <table
                style={{
                    width: '100%',
                    borderCollapse: 'collapse',
                    fontSize: 13,
                }}
            >
                <thead>
                    <tr style={{ background: '#f8fafc' }}>
                        <th style={thStyle}>{rowHeader}</th>
                        {result.columns.map((column, i) => (
                            <th
                                key={i}
                                style={{ ...thStyle, textAlign: 'right' }}
                            >
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {result.rows.map((row, ri) => (
                        <tr key={ri}>
                            <td style={{ ...tdStyle, fontWeight: 600 }}>
                                {row.label}
                            </td>
                            {row.cells.map((cell, ci) => (
                                <td
                                    key={ci}
                                    style={{
                                        ...tdStyle,
                                        textAlign: 'right',
                                        fontVariantNumeric: 'tabular-nums',
                                    }}
                                >
                                    {formatCell(
                                        cell,
                                        result.columns[ci]?.format ?? 'integer',
                                    )}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** Compact axis label (e.g. "Rp 8,5jt", "1,2rb", "12"). */
function formatAxis(value: number, format: string): string {
    const abs = Math.abs(value);
    if (format === 'currency') {
        if (abs >= 1_000_000) {
            return (
                'Rp ' +
                (value / 1_000_000).toLocaleString('id-ID', {
                    maximumFractionDigits: 1,
                }) +
                'jt'
            );
        }
        if (abs >= 1000) {
            return (
                'Rp ' +
                (value / 1000).toLocaleString('id-ID', {
                    maximumFractionDigits: 0,
                }) +
                'rb'
            );
        }

        return 'Rp ' + value.toLocaleString('id-ID');
    }

    return value.toLocaleString('id-ID', {
        maximumFractionDigits: format === 'decimal' ? 1 : 0,
    });
}

/**
 * ApexCharts grouped bar chart built from the pivot's Rows × (Columns × Values).
 * Loaded via a client-only dynamic import so it never runs during Inertia SSR.
 */
function PreviewChart({ result }: { result: PivotResult }) {
    const [ApexChart, setApexChart] =
        useState<React.ComponentType<Record<string, unknown>> | null>(null);

    useEffect(() => {
        let active = true;
        import('react-apexcharts').then((module) => {
            if (active) {
                setApexChart(
                    () =>
                        module.default as React.ComponentType<
                            Record<string, unknown>
                        >,
                );
            }
        });

        return () => {
            active = false;
        };
    }, []);

    const categories = result.rows.map((row) => row.label);
    const series = result.columns.map((column, ci) => ({
        name: column.label,
        data: result.rows.map((row) => row.cells[ci] ?? 0),
    }));
    const format = result.columns[0]?.format ?? 'integer';
    const horizontal = categories.length > 7;
    const height = horizontal ? Math.max(260, categories.length * 34) : 320;

    const options: ApexOptions = {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            fontFamily: 'inherit',
            animations: { speed: 300 },
        },
        colors: CHART_COLORS,
        plotOptions: {
            bar: {
                horizontal,
                borderRadius: 5,
                borderRadiusApplication: 'end',
                columnWidth: '58%',
                barHeight: '62%',
            },
        },
        dataLabels: { enabled: false },
        stroke: { show: false },
        grid: { borderColor: C.line, strokeDashArray: 4 },
        xaxis: {
            categories,
            labels: {
                style: { colors: C.muted, fontSize: '11px' },
                formatter: horizontal
                    ? (value: string) => formatAxis(Number(value), format)
                    : undefined,
            },
            axisBorder: { color: C.border },
            axisTicks: { color: C.border },
        },
        yaxis: {
            labels: {
                style: { colors: C.muted, fontSize: '11px' },
                formatter: horizontal
                    ? undefined
                    : (value: number) => formatAxis(value, format),
            },
        },
        legend: {
            show: series.length > 1,
            position: 'top',
            horizontalAlign: 'left',
            fontSize: '12px',
            markers: { size: 6 },
        },
        tooltip: {
            y: { formatter: (value: number) => formatCell(value, format) },
        },
        fill: { opacity: 1 },
    };

    return (
        <div
            style={{
                border: `1px solid ${C.border}`,
                borderRadius: 12,
                padding: '16px 12px 8px',
                minHeight: 340,
            }}
        >
            {ApexChart ? (
                <ApexChart
                    type="bar"
                    height={height}
                    options={options}
                    series={series}
                />
            ) : (
                <div
                    style={{
                        height: 320,
                        borderRadius: 10,
                        background:
                            'linear-gradient(90deg,#f1f5f9,#f8fafc,#f1f5f9)',
                        animation: 'pulse 1.5s ease-in-out infinite',
                    }}
                />
            )}
        </div>
    );
}

const btnPrimary: React.CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 7,
    background: C.primary,
    color: '#fff',
    border: 'none',
    borderRadius: 10,
    padding: '9px 16px',
    fontSize: 13.5,
    fontWeight: 600,
    cursor: 'pointer',
};

const btnGhost: React.CSSProperties = {
    background: '#fff',
    color: C.text,
    border: `1px solid ${C.border}`,
    borderRadius: 10,
    padding: '9px 16px',
    fontSize: 13.5,
    fontWeight: 600,
    cursor: 'pointer',
};

const fieldFormLabel: React.CSSProperties = {
    display: 'block',
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    marginBottom: 6,
};

const fieldFormInput: React.CSSProperties = {
    width: '100%',
    border: `1px solid ${C.border}`,
    borderRadius: 10,
    padding: '10px 13px',
    fontSize: 14,
    color: C.text,
    outline: 'none',
    boxSizing: 'border-box',
    background: '#fff',
};

const templateCard: React.CSSProperties = {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'flex-start',
    gap: 8,
    textAlign: 'left',
    padding: '16px 16px 18px',
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 14,
    cursor: 'pointer',
};

const categoryTag: React.CSSProperties = {
    fontSize: 10.5,
    fontWeight: 700,
    letterSpacing: '.05em',
    color: C.muted,
    background: C.line,
    borderRadius: 6,
    padding: '3px 8px',
    textTransform: 'uppercase',
};

const toggleWrap: React.CSSProperties = {
    display: 'inline-flex',
    background: C.line,
    borderRadius: 9,
    padding: 3,
};

const toggleBtn = (active: boolean): React.CSSProperties => ({
    border: 'none',
    background: active ? '#fff' : 'transparent',
    color: active ? C.navy : C.muted,
    borderRadius: 7,
    padding: '5px 16px',
    fontSize: 12.5,
    fontWeight: 600,
    cursor: 'pointer',
    boxShadow: active ? '0 1px 2px rgba(15,23,42,.08)' : 'none',
});

const thStyle: React.CSSProperties = {
    textAlign: 'left',
    padding: '10px 14px',
    fontSize: 12,
    fontWeight: 700,
    color: C.muted,
    borderBottom: `1px solid ${C.border}`,
    whiteSpace: 'nowrap',
};

const tdStyle: React.CSSProperties = {
    padding: '10px 14px',
    color: C.text,
    borderBottom: `1px solid ${C.line}`,
    whiteSpace: 'nowrap',
};
