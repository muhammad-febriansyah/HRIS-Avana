import { Head } from '@inertiajs/react';
import { C } from '@/lib/avana';
import { Empty, Kpi, KpiRow, RecruitmentHeader, Section } from './shell';

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

function Bars({ data }: { data: Series[] }) {
    if (data.length === 0) {
        return <Empty icon="bar-chart-3" title="Belum ada data" />;
    }
    const max = Math.max(...data.map((d) => d.value), 1);
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            {data.map((row) => (
                <div key={row.label}>
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            fontSize: 13,
                            marginBottom: 6,
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
                            {row.value}
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
                                width: `${Math.round((row.value / max) * 100)}%`,
                                height: '100%',
                                borderRadius: 100,
                                background: C.primary,
                            }}
                        />
                    </div>
                </div>
            ))}
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
                        <Bars data={byStage} />
                    </Section>
                    <Section title="Sumber Pelamar" icon="globe">
                        <Bars data={bySource} />
                    </Section>
                </div>
            </div>
        </>
    );
}
