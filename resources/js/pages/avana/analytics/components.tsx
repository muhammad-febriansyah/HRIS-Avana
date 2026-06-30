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
                <div style={{ fontSize: 12.5, color: C.muted }}>{kpi.label}</div>
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
export function BarList({ data }: { data: Series[] }) {
    if (data.length === 0) {
        return <EmptyState label="Belum ada data" />;
    }

    const max = Math.max(...data.map((d) => d.value), 1);

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            {data.map((row, index) => {
                const color = CHART_PALETTE[index % CHART_PALETTE.length];
                const pct = Math.round((row.value / max) * 100);

                return (
                    <div key={row.label}>
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
                                    fontWeight: 600,
                                    color: C.navy,
                                    fontVariantNumeric: 'tabular-nums',
                                }}
                            >
                                {row.value.toLocaleString('id-ID')}
                            </span>
                        </div>
                        <div
                            style={{
                                height: 9,
                                borderRadius: 100,
                                background: C.line,
                                overflow: 'hidden',
                            }}
                        >
                            <div
                                style={{
                                    width: `${pct}%`,
                                    height: '100%',
                                    borderRadius: 100,
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
