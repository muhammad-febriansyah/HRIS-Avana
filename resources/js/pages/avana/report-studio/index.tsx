import {
    DndContext,
    type DragEndEvent,
    PointerSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { AIcon, C } from '@/lib/avana';

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

interface Props {
    dimensions: Dimension[];
    measures: Measure[];
    templates: Template[];
}

const GROUP_DOT: Record<string, string> = {
    'Data Karyawan': '#d97706',
    Ringkasan: '#2547F9',
    'Absensi & Cuti': '#16a34a',
    Payroll: '#7c3aed',
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
}: Props) {
    const [rows, setRows] = useState<string[]>([]);
    const [columns, setColumns] = useState<string[]>([]);
    const [values, setValues] = useState<ValueField[]>([]);
    const [view, setView] = useState<'table' | 'chart'>('table');
    const [result, setResult] = useState<PivotResult | null>(null);
    const [loading, setLoading] = useState(false);

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

    const applyTemplate = (template: Template) => {
        setRows(template.config.rows);
        setColumns(template.config.columns);
        setValues(template.config.values);
    };

    const reset = () => {
        setRows([]);
        setColumns([]);
        setValues([]);
        setResult(null);
    };

    const exportCsv = async () => {
        const res = await fetch('/avana/report-studio/export', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': cookie('XSRF-TOKEN'),
                Accept: 'text/csv',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                payload: JSON.stringify({ rows, columns, values }),
            }),
        });
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'report-studio.csv';
        link.click();
        URL.revokeObjectURL(url);
    };

    const dimensionGroups = groupBy(dimensions, (d) => d.group);
    const measureGroups = groupBy(measures, (m) => m.group);

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
                            onClick={exportCsv}
                            disabled={configEmpty}
                            style={{
                                ...btnPrimary,
                                opacity: configEmpty ? 0.5 : 1,
                                cursor: configEmpty ? 'not-allowed' : 'pointer',
                            }}
                        >
                            <AIcon name="download" size={15} color="#fff" />
                            Export CSV
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
                    {templates.map((template) => (
                        <button
                            key={template.key}
                            onClick={() => applyTemplate(template)}
                            style={templateCard}
                        >
                            <span style={categoryTag}>{template.category}</span>
                            <span
                                style={{
                                    fontSize: 14.5,
                                    fontWeight: 700,
                                    color: C.navy,
                                    lineHeight: 1.3,
                                }}
                            >
                                {template.title}
                            </span>
                            <span style={{ fontSize: 12, color: C.muted }}>
                                {template.subtitle}
                            </span>
                        </button>
                    ))}
                </div>

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
        return <PreviewChart data={result.chart} />;
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

function PreviewChart({ data }: { data: { label: string; value: number }[] }) {
    if (data.length === 0) {
        return null;
    }
    const max = Math.max(...data.map((d) => d.value), 1);
    const height = 200;

    return (
        <div
            style={{
                border: `1px solid ${C.border}`,
                borderRadius: 12,
                padding: '22px 18px 14px',
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-end',
                    gap: 10,
                    height,
                    borderBottom: `2px solid ${C.border}`,
                }}
            >
                {data.map((row, i) => {
                    const barH = Math.max((row.value / max) * (height - 24), 3);

                    return (
                        <div
                            key={i}
                            title={`${row.label}: ${row.value.toLocaleString('id-ID')}`}
                            style={{
                                flex: 1,
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                justifyContent: 'flex-end',
                                minWidth: 0,
                            }}
                        >
                            <span
                                style={{
                                    fontSize: 12,
                                    fontWeight: 700,
                                    color: C.navy,
                                    marginBottom: 5,
                                    fontVariantNumeric: 'tabular-nums',
                                }}
                            >
                                {row.value.toLocaleString('id-ID')}
                            </span>
                            <div
                                style={{
                                    width: '100%',
                                    maxWidth: 54,
                                    height: barH,
                                    background: C.primary,
                                    borderRadius: '6px 6px 0 0',
                                }}
                            />
                        </div>
                    );
                })}
            </div>
            <div style={{ display: 'flex', gap: 10, marginTop: 8 }}>
                {data.map((row, i) => (
                    <div
                        key={i}
                        style={{
                            flex: 1,
                            minWidth: 0,
                            textAlign: 'center',
                            fontSize: 11,
                            color: C.muted,
                            wordBreak: 'break-word',
                        }}
                    >
                        {row.label}
                    </div>
                ))}
            </div>
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
