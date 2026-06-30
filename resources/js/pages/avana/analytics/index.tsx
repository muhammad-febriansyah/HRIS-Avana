import { Head } from '@inertiajs/react';
import { AIcon, C } from '@/lib/avana';
import {
    BarList,
    ChartCard,
    DonutChart,
    KpiCard,
    PayrollSummary,
} from './components';
import type { AnalyticsProps } from './types';

export default function AnalyticsIndex({
    period,
    kpis,
    activeStatus,
    byDepartment,
    byEmploymentStatus,
    byGender,
    attendance,
    payroll,
}: AnalyticsProps) {
    return (
        <>
            <Head title="Analitik HR" />
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
                        <span style={{ color: C.muted }}>Analitik HR</span>
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
                        Analitik HR
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Ringkasan metrik tenaga kerja · {period}
                    </div>
                </div>

                {/* KPI cards */}
                <div
                    className="avn-kpi"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4,1fr)',
                        gap: 18,
                        marginBottom: 18,
                    }}
                >
                    {kpis.map((kpi) => (
                        <KpiCard key={kpi.label} kpi={kpi} />
                    ))}
                </div>

                {/* Charts */}
                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(2,1fr)',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    <ChartCard title="Karyawan per Departemen" icon="building-2">
                        <BarList data={byDepartment} />
                    </ChartCard>

                    <ChartCard title="Status Kepegawaian" icon="briefcase">
                        <DonutChart data={byEmploymentStatus} />
                    </ChartCard>

                    <ChartCard title="Aktif vs Nonaktif" icon="user-check">
                        <DonutChart data={activeStatus} />
                    </ChartCard>

                    <ChartCard title="Komposisi Gender" icon="users">
                        <DonutChart data={byGender} />
                    </ChartCard>

                    {attendance !== null && (
                        <ChartCard
                            title="Ringkasan Absensi Bulan Ini"
                            icon="fingerprint"
                        >
                            <BarList data={attendance} />
                        </ChartCard>
                    )}

                    {payroll !== null && (
                        <ChartCard title="Biaya Payroll Terakhir" icon="wallet">
                            <PayrollSummary payroll={payroll} />
                        </ChartCard>
                    )}
                </div>
            </div>
        </>
    );
}
