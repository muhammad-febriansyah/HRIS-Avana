import { Head, Link } from '@inertiajs/react';
import DynamicReportController from '@/actions/App/Http/Controllers/Avana/DynamicReportController';
import { AIcon, btnOut, btnP, C } from '@/lib/avana';
import { DataTable } from './components';
import type { ReportShowProps } from './types';

export default function ReportShow({ report, headers, rows, count }: ReportShowProps) {
    return (
        <>
            <Head title={report.name} />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div>
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
                            <Link
                                href={DynamicReportController.index().url}
                                style={{
                                    color: C.muted,
                                    textDecoration: 'none',
                                }}
                            >
                                Laporan Dinamis
                            </Link>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>{report.name}</span>
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
                            {report.name}
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            {report.entity_label} ·{' '}
                            {count.toLocaleString('id-ID')} baris
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <Link
                            href={DynamicReportController.index().url}
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="arrow-left" size={16} />
                            Kembali
                        </Link>
                        <a
                            href={DynamicReportController.export(report.id).url}
                            style={{ ...btnP, textDecoration: 'none' }}
                        >
                            <AIcon name="download" size={16} color="#fff" />
                            Export CSV
                        </a>
                    </div>
                </div>

                <DataTable headers={headers} rows={rows} />
            </div>
        </>
    );
}
