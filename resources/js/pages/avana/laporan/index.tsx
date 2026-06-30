import { Head } from '@inertiajs/react';
import { AIcon, C } from '@/lib/avana';
import { ReportCardItem } from './components';
import { buildReports } from './types';
import type { LaporanProps } from './types';

export default function AvanaLaporan({ stats }: LaporanProps) {
    const reports = buildReports(stats);

    return (
        <>
            <Head title="Laporan" />
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
                        <span style={{ color: C.muted }}>Laporan</span>
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
                        Laporan
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Unduh laporan data HR dalam format CSV.
                    </div>
                </div>

                {/* Report cards */}
                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(2,1fr)',
                        gap: 18,
                        alignItems: 'stretch',
                    }}
                >
                    {reports.map((report) => (
                        <ReportCardItem key={report.type} report={report} />
                    ))}
                </div>
            </div>
        </>
    );
}
