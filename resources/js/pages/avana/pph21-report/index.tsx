import { Head, router } from '@inertiajs/react';
import { AIcon, C, card } from '@/lib/avana';
import { CompletenessCard } from './completeness-card';
import { ComplianceCard } from './compliance-card';
import { btnSmExport } from './components';
import { EmployeeTable } from './employee-table';
import { RecapTable } from './recap-table';
import type { Pph21ReportProps, Pph21Summary } from './types';

export default function AvanaPph21Report({
    periods,
    summary,
    compliance,
    completeness,
    recap,
    employees,
    employee_meta,
    filters,
    can,
}: Pph21ReportProps) {
    // Bukti potong 1721-A1 is an annual form: it belongs to the year the
    // selected period falls in, not to the period itself.
    const year = summary.start_date
        ? Number(summary.start_date.slice(0, 4))
        : null;

    const exportUrl = (format: string) => {
        const params = new URLSearchParams();

        if (summary.period_id !== null) {
            params.set('period', String(summary.period_id));
        }

        if (format !== 'csv') {
            params.set('format', format);
        }

        const qs = params.toString();

        return '/avana/payroll/pph21-report/export' + (qs ? '?' + qs : '');
    };

    return (
        <>
            <Head title="Laporan PPh 21" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        gap: 16,
                        flexWrap: 'wrap',
                        marginBottom: 20,
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
                            <span>Payroll</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                Laporan PPh 21
                            </span>
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
                            Laporan PPh 21
                        </h1>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Ringkasan kewajiban dan status administrasi PPh 21
                            {summary.period
                                ? ` · Masa Pajak ${summary.period}`
                                : ''}
                        </div>
                    </div>

                    <div
                        style={{
                            display: 'flex',
                            gap: 8,
                            flexWrap: 'wrap',
                            alignItems: 'center',
                        }}
                    >
                        <select
                            data-testid="filter-periode"
                            value={summary.period_id ?? ''}
                            onChange={(e) =>
                                router.get(
                                    '/avana/payroll/pph21-report',
                                    { period: e.target.value },
                                    { preserveScroll: true },
                                )
                            }
                            style={{
                                padding: '9px 12px',
                                border: `1px solid ${C.border}`,
                                borderRadius: 8,
                                fontSize: 13,
                                background: '#fff',
                                color: C.text,
                                minWidth: 170,
                            }}
                        >
                            {periods.length === 0 && (
                                <option value="">Belum ada periode</option>
                            )}
                            {periods.map((period) => (
                                <option key={period.id} value={period.id}>
                                    {period.name}
                                </option>
                            ))}
                        </select>
                        <a
                            data-testid="export-excel"
                            href={exportUrl('xlsx')}
                            style={btnSmExport}
                        >
                            <AIcon name="sheet" size={15} color="#fff" />
                            Excel
                        </a>
                        <a
                            data-testid="export-pdf"
                            href={exportUrl('pdf')}
                            style={btnSmExport}
                        >
                            <AIcon name="file-text" size={15} color="#fff" />
                            PDF
                        </a>
                        <a
                            data-testid="export-csv"
                            href={exportUrl('csv')}
                            style={btnSmExport}
                        >
                            <AIcon name="download" size={15} color="#fff" />
                            CSV
                        </a>
                    </div>
                </div>

                <KpiRow summary={summary} />

                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1.35fr 1fr',
                        gap: 16,
                        marginTop: 16,
                        alignItems: 'start',
                    }}
                >
                    <ComplianceCard
                        compliance={compliance}
                        canUpdate={can.update_compliance}
                    />
                    <CompletenessCard completeness={completeness} />
                </div>

                <div style={{ marginTop: 16 }}>
                    <RecapTable recap={recap} selectedId={summary.period_id} />
                </div>

                <div style={{ marginTop: 16 }}>
                    <EmployeeTable
                        employees={employees}
                        meta={employee_meta}
                        filters={filters}
                        year={year}
                    />
                </div>
            </div>
        </>
    );
}

/** The four headline figures for the selected tax period. */
function KpiRow({ summary }: { summary: Pph21Summary }) {
    const notWithheld = summary.tax_due_raw - summary.tax_withheld_raw;

    return (
        <div
            className="avn-kpi"
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(4,1fr)',
                gap: 0,
                ...card,
                overflow: 'hidden',
            }}
        >
            <Kpi
                label="Total Karyawan"
                value={summary.employee_count.toLocaleString('id-ID')}
                foot={
                    summary.employee_delta === null
                        ? 'Tidak ada masa pajak pembanding'
                        : `${summary.employee_delta >= 0 ? '▲' : '▼'} ${Math.abs(summary.employee_delta)} dari ${summary.previous_period}`
                }
                footColor={
                    summary.employee_delta && summary.employee_delta < 0
                        ? C.red
                        : C.muted
                }
                testid="kpi-karyawan"
            />
            <Kpi
                label="Penghasilan Bruto (Dasar Pajak)"
                value={summary.gross}
                foot={
                    summary.gross_delta_pct === null
                        ? 'Tidak ada masa pajak pembanding'
                        : `${summary.gross_delta_pct >= 0 ? '▲' : '▼'} ${Math.abs(summary.gross_delta_pct)}% dari ${summary.previous_period}`
                }
                footColor={
                    summary.gross_delta_pct !== null &&
                    summary.gross_delta_pct < 0
                        ? C.red
                        : C.green
                }
                testid="kpi-bruto"
            />
            <Kpi
                label="PPh 21 Terutang"
                value={summary.tax_due}
                valueColor={C.navy}
                foot="Seluruh run aktif pada masa pajak ini"
                testid="kpi-terutang"
            />
            <Kpi
                label="PPh 21 Sudah Dipotong"
                value={summary.tax_withheld}
                valueColor={summary.withheld_pct >= 100 ? C.green : C.amber}
                foot={
                    notWithheld > 0.5
                        ? `${summary.withheld_pct}% — sisanya menunggu persetujuan payroll`
                        : `${summary.withheld_pct}% dari kewajiban masa pajak`
                }
                footColor={notWithheld > 0.5 ? C.amber : C.green}
                last
                testid="kpi-dipotong"
            />
        </div>
    );
}

function Kpi({
    label,
    value,
    foot,
    footColor = C.muted,
    valueColor = C.navy,
    last = false,
    testid,
}: {
    label: string;
    value: string;
    foot: string;
    footColor?: string;
    valueColor?: string;
    last?: boolean;
    testid: string;
}) {
    return (
        <div
            data-testid={testid}
            style={{
                padding: '18px 20px',
                borderRight: last ? undefined : `1px solid ${C.line}`,
                background: last ? '#FAFBFE' : undefined,
            }}
        >
            <div style={{ fontSize: 12.5, color: C.muted }}>{label}</div>
            <div
                style={{
                    fontSize: 21,
                    fontWeight: 700,
                    color: valueColor,
                    marginTop: 5,
                    fontVariantNumeric: 'tabular-nums',
                }}
            >
                {value}
            </div>
            <div style={{ fontSize: 11.5, color: footColor, marginTop: 6 }}>
                {foot}
            </div>
        </div>
    );
}
