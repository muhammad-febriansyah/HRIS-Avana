import type { CSSProperties, ReactNode } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import type { Kpi, PayrollCost, Series } from './types';

/* ============================================================
 * Presentational helpers for the read-only HR analytics dashboard:
 * KPI cards plus inline-styled bar/donut visualizations built from
 * plain divs/CSS (no charting library).
 * ============================================================ */

/** Brand-aligned palette for chart segments. */
export const CHART_PALETTE = [
    '#2F54C9',
    '#6E9BE6',
    '#16A34A',
    '#D97706',
    '#DC2626',
    '#0E1A3A',
    '#9333EA',
    '#0891B2',
];

/** A single headline KPI card. */
export function KpiCard({ kpi }: { kpi: Kpi }) {
    return (
        <div
            style={{
                ...card,
                padding: '20px 22px',
                display: 'flex',
                alignItems: 'center',
                gap: 14,
            }}
        >
            <div
                style={{
                    width: 46,
                    height: 46,
                    borderRadius: 12,
                    flex: 'none',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: kpi.color + '1a',
                    color: kpi.color,
                }}
            >
                <AIcon name={kpi.icon} size={22} color={kpi.color} />
            </div>
            <div>
                <div style={{ fontSize: 12.5, color: C.muted }}>
                    {kpi.label}
                </div>
                <div
                    style={{
                        fontSize: 22,
                        fontWeight: 700,
                        color: C.navy,
                        marginTop: 3,
                        fontVariantNumeric: 'tabular-nums',
                    }}
                >
                    {kpi.value}
                </div>
            </div>
        </div>
    );
}

/** A titled card wrapper for a single chart/section. */
export function ChartCard({
    title,
    icon,
    children,
}: {
    title: string;
    icon: string;
    children: ReactNode;
}) {
    return (
        <div style={{ ...card, padding: '20px 22px' }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 9,
                    marginBottom: 18,
                }}
            >
                <AIcon name={icon} size={18} color={C.primary} />
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                    {title}
                </div>
            </div>
            {children}
        </div>
    );
}

/** Empty-state placeholder shown when a series has no data. */
export function EmptyState({ label }: { label: string }) {
    return (
        <div
            style={{
                padding: '28px 0',
                textAlign: 'center',
                fontSize: 13,
                color: C.faint,
            }}
        >
            {label}
        </div>
    );
}

/** Horizontal bar list driven by a `{ label, value }` series. */
export function BarList({
    data,
    color = C.primary,
}: {
    data: Series[];
    color?: string;
}) {
    if (data.length === 0) {
        return <EmptyState label="Belum ada data" />;
    }

    const max = Math.max(...data.map((d) => d.value), 1);

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            {data.map((row) => {
                const pct = Math.round((row.value / max) * 100);

                return (
                    <div key={row.label} title={`${row.label}: ${row.value}`}>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                marginBottom: 6,
                                fontSize: 13,
                            }}
                        >
                            <span style={{ color: C.text }}>{row.label}</span>
                            <span
                                style={{
                                    fontWeight: 700,
                                    color: C.navy,
                                    fontVariantNumeric: 'tabular-nums',
                                }}
                            >
                                {row.value.toLocaleString('id-ID')}
                            </span>
                        </div>
                        <div
                            style={{
                                height: 11,
                                borderRadius: 6,
                                background: C.line,
                                overflow: 'hidden',
                            }}
                        >
                            <div
                                style={{
                                    width: `${pct}%`,
                                    minWidth: row.value > 0 ? 6 : 0,
                                    height: '100%',
                                    borderRadius: 6,
                                    background: color,
                                    transition: 'width .3s',
                                }}
                            />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

/** Ordinal blue ramp for ordered distribution bins (light → dark). */
const COLUMN_RAMP = ['#86b6ef', '#5598e7', '#2a78d6', '#1c5cab', '#104281'];

/**
 * Vertical column (histogram) chart for an ordered distribution — bins share a
 * baseline so heights compare directly; value above each column, labels beneath.
 */
export function ColumnChart({ data }: { data: Series[] }) {
    if (data.length === 0) {
        return <EmptyState label="Belum ada data" />;
    }

    const max = Math.max(...data.map((d) => d.value), 1);
    const height = 170;

    return (
        <div>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-end',
                    gap: 10,
                    height,
                    borderBottom: `2px solid ${C.border}`,
                    paddingTop: 22,
                }}
            >
                {data.map((row, i) => {
                    const barH = Math.max((row.value / max) * (height - 22), 3);
                    const color = COLUMN_RAMP[i % COLUMN_RAMP.length];

                    return (
                        <div
                            key={row.label}
                            title={`${row.label}: ${row.value}`}
                            style={{
                                flex: 1,
                                height: '100%',
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                justifyContent: 'flex-end',
                                minWidth: 0,
                            }}
                        >
                            <span
                                style={{
                                    fontSize: 13,
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
                                    maxWidth: 48,
                                    height: barH,
                                    background: row.value > 0 ? color : C.line,
                                    borderRadius: '6px 6px 0 0',
                                    transition: 'height .3s ease',
                                }}
                            />
                        </div>
                    );
                })}
            </div>
            <div style={{ display: 'flex', gap: 10, marginTop: 8 }}>
                {data.map((row) => (
                    <div
                        key={row.label}
                        style={{
                            flex: 1,
                            minWidth: 0,
                            textAlign: 'center',
                            fontSize: 11,
                            lineHeight: 1.2,
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

/** Vertical column trend chart for a time-ordered `{ label, value }` series. */
export function TrendChart({ data }: { data: Series[] }) {
    const total = data.reduce((sum, d) => sum + d.value, 0);

    if (total === 0) {
        return <EmptyState label="Belum ada data attrition" />;
    }

    const max = Math.max(...data.map((d) => d.value), 1);

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'flex-end',
                gap: 6,
                height: 150,
            }}
        >
            {data.map((row) => {
                const pct = Math.round((row.value / max) * 100);

                return (
                    <div
                        key={row.label}
                        style={{
                            flex: 1,
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            gap: 6,
                            height: '100%',
                            justifyContent: 'flex-end',
                        }}
                    >
                        <span
                            style={{
                                fontSize: 11,
                                fontWeight: 600,
                                color: C.navy,
                                fontVariantNumeric: 'tabular-nums',
                            }}
                        >
                            {row.value > 0 ? row.value : ''}
                        </span>
                        <div
                            style={{
                                width: '100%',
                                maxWidth: 26,
                                height: `${Math.max(pct, 3)}%`,
                                borderRadius: '5px 5px 0 0',
                                background: row.value > 0 ? C.red : C.line,
                                transition: 'height .3s',
                            }}
                        />
                        <span style={{ fontSize: 10.5, color: C.faint }}>
                            {row.label}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

/** Donut chart (CSS conic-gradient) with a labelled legend. */
export function DonutChart({ data }: { data: Series[] }) {
    const total = data.reduce((sum, d) => sum + d.value, 0);

    if (total === 0) {
        return <EmptyState label="Belum ada data" />;
    }

    let acc = 0;
    const stops = data
        .map((d, index) => {
            const start = (acc / total) * 100;
            acc += d.value;
            const end = (acc / total) * 100;
            const color = CHART_PALETTE[index % CHART_PALETTE.length];

            return `${color} ${start}% ${end}%`;
        })
        .join(', ');

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 24,
                flexWrap: 'wrap',
            }}
        >
            <div
                style={{
                    position: 'relative',
                    width: 140,
                    height: 140,
                    borderRadius: '50%',
                    background: `conic-gradient(${stops})`,
                    flex: 'none',
                }}
            >
                <div
                    style={{
                        position: 'absolute',
                        inset: 20,
                        borderRadius: '50%',
                        background: '#fff',
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <div
                        style={{
                            fontSize: 22,
                            fontWeight: 700,
                            color: C.navy,
                            fontVariantNumeric: 'tabular-nums',
                        }}
                    >
                        {total.toLocaleString('id-ID')}
                    </div>
                    <div style={{ fontSize: 11, color: C.faint }}>Total</div>
                </div>
            </div>
            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 10,
                    minWidth: 140,
                }}
            >
                {data.map((row, index) => {
                    const color = CHART_PALETTE[index % CHART_PALETTE.length];
                    const pct = Math.round((row.value / total) * 100);

                    return (
                        <div
                            key={row.label}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 9,
                                fontSize: 13,
                            }}
                        >
                            <span
                                style={{
                                    width: 11,
                                    height: 11,
                                    borderRadius: 3,
                                    background: color,
                                    flex: 'none',
                                }}
                            />
                            <span style={{ color: C.text, flex: 1 }}>
                                {row.label}
                            </span>
                            <span
                                style={{
                                    fontWeight: 600,
                                    color: C.navy,
                                    fontVariantNumeric: 'tabular-nums',
                                }}
                            >
                                {row.value.toLocaleString('id-ID')} · {pct}%
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

/** Latest payroll-run cost summary block. */
export function PayrollSummary({ payroll }: { payroll: PayrollCost }) {
    const tiles: Array<{ label: string; value: string; color: string }> = [
        { label: 'Total Gross', value: payroll.gross, color: C.navy },
        { label: 'Total Potongan', value: payroll.deduction, color: C.amber },
        { label: 'Total Pajak', value: payroll.tax, color: C.red },
        { label: 'Total Net', value: payroll.net, color: C.green },
    ];

    const tileStyle: CSSProperties = {
        padding: '16px 18px',
        borderRadius: 10,
        background: C.surface,
    };

    return (
        <div>
            <div style={{ fontSize: 13, color: C.muted, marginBottom: 14 }}>
                Periode {payroll.period} ·{' '}
                {payroll.employee_count.toLocaleString('id-ID')} karyawan
            </div>
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(2,1fr)',
                    gap: 12,
                }}
            >
                {tiles.map((tile) => (
                    <div key={tile.label} style={tileStyle}>
                        <div style={{ fontSize: 12, color: C.muted }}>
                            {tile.label}
                        </div>
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 700,
                                color: tile.color,
                                marginTop: 4,
                                fontVariantNumeric: 'tabular-nums',
                            }}
                        >
                            {tile.value}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
