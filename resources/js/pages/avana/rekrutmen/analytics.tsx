import { Head } from '@inertiajs/react';
import { C } from '@/lib/avana';
import {
    ColumnChart,
    ColumnLabels,
    Empty,
    Kpi,
    KpiRow,
    RecruitmentHeader,
    Section,
} from './shell';

interface Series {
    label: string;
    value: number;
}

interface AnalyticsProps {
    kpis: {
        total_candidates: number;
        hired: number;
        conversion_rate: number;
        open_postings: number;
    };
    bySource: Series[];
    byStage: Series[];
}

/**
 * Ordinal blue ramp (PMK dataviz ordinal steps 250→650) for the ordered funnel
 * stages — later stages read darker.
 */
const STAGE_RAMP = [
    '#86b6ef',
    '#6da7ec',
    '#5598e7',
    '#3987e5',
    '#2a78d6',
    '#1c5cab',
    '#104281',
];

/** Validated categorical slots (CVD-safe as a set) for the source donut. */
const SOURCE_COLORS = [
    '#2a78d6',
    '#1baf7a',
    '#eda100',
    '#4a3aa7',
    '#e34948',
    '#e87ba4',
    '#eb6834',
    '#008300',
];

/** Donut chart for the part-to-whole source composition. */
function SourceDonut({ data }: { data: Series[] }) {
    const total = data.reduce((sum, d) => sum + d.value, 0);

    if (total === 0) {
        return <Empty icon="globe" title="Belum ada data" />;
    }

    const size = 168;
    const stroke = 26;
    const r = (size - stroke) / 2;
    const circumference = 2 * Math.PI * r;
    const gap = 2; // px surface gap between slices

    let offset = 0;
    const segments = data
        .filter((d) => d.value > 0)
        .map((d, i) => {
            const frac = d.value / total;
            const len = frac * circumference;
            const dash = Math.max(len - gap, 0.5);
            const seg = {
                color: SOURCE_COLORS[i] ?? SOURCE_COLORS[0],
                dash,
                dashRest: circumference - dash,
                offset: -offset,
                label: d.label,
                value: d.value,
                pct: Math.round(frac * 100),
            };
            offset += len;
            return seg;
        });

    return (
        <div
            style={{
                display: 'flex',
                flexWrap: 'wrap',
                alignItems: 'center',
                gap: 28,
            }}
        >
            <div style={{ position: 'relative', width: size, height: size }}>
                <svg
                    width={size}
                    height={size}
                    viewBox={`0 0 ${size} ${size}`}
                    role="img"
                    aria-label="Komposisi sumber pelamar"
                >
                    <circle
                        cx={size / 2}
                        cy={size / 2}
                        r={r}
                        fill="none"
                        stroke={C.line}
                        strokeWidth={stroke}
                    />
                    {segments.map((s) => (
                        <circle
                            key={s.label}
                            cx={size / 2}
                            cy={size / 2}
                            r={r}
                            fill="none"
                            stroke={s.color}
                            strokeWidth={stroke}
                            strokeDasharray={`${s.dash} ${s.dashRest}`}
                            strokeDashoffset={s.offset}
                            transform={`rotate(-90 ${size / 2} ${size / 2})`}
                            style={{ transition: 'stroke-dasharray .3s ease' }}
                        >
                            <title>{`${s.label}: ${s.value} (${s.pct}%)`}</title>
                        </circle>
                    ))}
                </svg>
                <div
                    style={{
                        position: 'absolute',
                        inset: 0,
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <span
                        style={{
                            fontSize: 30,
                            fontWeight: 700,
                            color: C.navy,
                            lineHeight: 1,
                            fontVariantNumeric: 'tabular-nums',
                        }}
                    >
                        {total}
                    </span>
                    <span
                        style={{ fontSize: 12, color: C.muted, marginTop: 3 }}
                    >
                        Pelamar
                    </span>
                </div>
            </div>

            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 12,
                    flex: 1,
                    minWidth: 180,
                }}
            >
                {segments.map((s) => (
                    <div
                        key={s.label}
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                        }}
                    >
                        <span
                            style={{
                                width: 12,
                                height: 12,
                                borderRadius: 4,
                                background: s.color,
                                flex: 'none',
                            }}
                        />
                        <span style={{ fontSize: 13, color: C.text, flex: 1 }}>
                            {s.label}
                        </span>
                        <span
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                fontVariantNumeric: 'tabular-nums',
                            }}
                        >
                            {s.pct}%
                        </span>
                        <span
                            style={{
                                fontSize: 13,
                                fontWeight: 700,
                                color: C.navy,
                                minWidth: 18,
                                textAlign: 'right',
                                fontVariantNumeric: 'tabular-nums',
                            }}
                        >
                            {s.value}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function RecruitmentAnalytics({
    kpis,
    bySource,
    byStage,
}: AnalyticsProps) {
    return (
        <>
            <Head title="Analitik Rekrutmen" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="Analitik"
                    subtitle="Wawasan funnel & efisiensi proses rekrutmen."
                />

                <KpiRow>
                    <Kpi
                        label="Total Kandidat"
                        value={kpis.total_candidates}
                        icon="users"
                        color={C.primary}
                    />
                    <Kpi
                        label="Diterima"
                        value={kpis.hired}
                        icon="user-check"
                        color={C.green}
                    />
                    <Kpi
                        label="Konversi"
                        value={`${kpis.conversion_rate}%`}
                        hint="Kandidat → hire"
                        icon="trending-up"
                        color="#7C3AED"
                    />
                    <Kpi
                        label="Lowongan Terbuka"
                        value={kpis.open_postings}
                        icon="briefcase"
                        color={C.amber}
                    />
                </KpiRow>

                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(2,1fr)',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    <Section title="Kandidat per Tahap" icon="bar-chart-3">
                        <ColumnChart data={byStage} colors={STAGE_RAMP} />
                        <ColumnLabels data={byStage} />
                    </Section>
                    <Section title="Sumber Pelamar" icon="globe">
                        <SourceDonut data={bySource} />
                    </Section>
                </div>
            </div>
        </>
    );
}
