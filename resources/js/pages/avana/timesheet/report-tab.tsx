import { C, card, thCell } from '@/lib/avana';
import { cellStyle, EmptyRow, SectionTitle } from './components';
import { hoursLabel, rupiah } from './types';
import type { TimesheetReport } from './types';

/**
 * Project profitability over the filtered window: what the approved hours were
 * sold for, what they cost, and how much of the budget they have eaten.
 */
export function ReportTab({
    report,
    from,
    to,
}: {
    report: TimesheetReport;
    from: string;
    to: string;
}) {
    const marginColor = (margin: number) =>
        margin > 0 ? C.green : margin < 0 ? C.red : C.muted;

    return (
        <>
            <SectionTitle
                actions={
                    <div style={{ fontSize: 12.5, color: C.muted }}>
                        Periode {from} – {to} · hanya entri disetujui
                    </div>
                }
            >
                Profitabilitas Proyek
            </SectionTitle>

            <div
                style={{ ...card, overflow: 'hidden', marginBottom: 28 }}
            >
                <div style={{ overflowX: 'auto' }}>
                    <table
                        style={{
                            width: '100%',
                            borderCollapse: 'collapse',
                            minWidth: 1040,
                        }}
                    >
                        <thead>
                            <tr style={{ background: '#FAFBFD' }}>
                                <th style={thCell}>Proyek</th>
                                <th style={thCell}>Klien</th>
                                <th style={thCell}>Jam</th>
                                <th style={thCell}>Jam Billable</th>
                                <th style={thCell}>Nilai Jual</th>
                                <th style={thCell}>Biaya</th>
                                <th style={thCell}>Margin</th>
                                <th style={thCell}>Serapan Budget</th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.projects.length === 0 && (
                                <EmptyRow
                                    colSpan={8}
                                    icon="chart-column"
                                    message="Belum ada jam disetujui pada periode ini."
                                />
                            )}
                            {report.projects.map((row) => (
                                <tr
                                    key={row.project_id}
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td
                                        style={{
                                            ...cellStyle,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {row.project}
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                fontWeight: 500,
                                                color: C.muted,
                                                marginTop: 2,
                                            }}
                                        >
                                            {row.code ?? '—'} · {row.entries}{' '}
                                            entri
                                        </div>
                                    </td>
                                    <td style={cellStyle}>
                                        {row.client_name ?? '—'}
                                    </td>
                                    <td style={cellStyle}>
                                        {hoursLabel(row.hours)}
                                    </td>
                                    <td style={cellStyle}>
                                        {hoursLabel(row.billable_hours)}
                                    </td>
                                    <td style={cellStyle}>
                                        {rupiah(row.bill_amount)}
                                    </td>
                                    <td style={cellStyle}>
                                        {rupiah(row.cost_amount)}
                                    </td>
                                    <td
                                        style={{
                                            ...cellStyle,
                                            fontWeight: 700,
                                            color: marginColor(row.margin),
                                        }}
                                    >
                                        {rupiah(row.margin)}
                                        {row.margin_pct !== null && (
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    fontWeight: 500,
                                                    color: C.muted,
                                                    marginTop: 2,
                                                }}
                                            >
                                                {row.margin_pct}%
                                            </div>
                                        )}
                                    </td>
                                    <td style={cellStyle}>
                                        <BudgetBar
                                            pct={row.budget_used_pct}
                                            caption={
                                                row.budget_amount
                                                    ? rupiah(row.cost_amount) +
                                                      ' / ' +
                                                      rupiah(row.budget_amount)
                                                    : row.budget_hours
                                                      ? hoursLabel(row.hours) +
                                                        ' / ' +
                                                        hoursLabel(
                                                            row.budget_hours,
                                                        )
                                                      : 'Tanpa budget'
                                            }
                                            fallbackPct={
                                                row.budget_hours_used_pct
                                            }
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        {report.projects.length > 0 && (
                            <tfoot>
                                <tr
                                    style={{
                                        borderTop: `1px solid ${C.border}`,
                                        background: '#FAFBFD',
                                    }}
                                >
                                    <td
                                        colSpan={2}
                                        style={{
                                            ...cellStyle,
                                            fontWeight: 600,
                                            color: C.muted,
                                            textAlign: 'right',
                                        }}
                                    >
                                        Total
                                    </td>
                                    <td
                                        colSpan={2}
                                        style={{
                                            ...cellStyle,
                                            fontWeight: 700,
                                            color: C.navy,
                                        }}
                                    >
                                        {hoursLabel(report.totals.hours)}
                                    </td>
                                    <td
                                        style={{
                                            ...cellStyle,
                                            fontWeight: 700,
                                            color: C.navy,
                                        }}
                                    >
                                        {rupiah(report.totals.bill_amount)}
                                    </td>
                                    <td
                                        style={{
                                            ...cellStyle,
                                            fontWeight: 700,
                                            color: C.navy,
                                        }}
                                    >
                                        {rupiah(report.totals.cost_amount)}
                                    </td>
                                    <td
                                        style={{
                                            ...cellStyle,
                                            fontWeight: 700,
                                            color: marginColor(
                                                report.totals.margin,
                                            ),
                                        }}
                                    >
                                        {rupiah(report.totals.margin)}
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>

            <SectionTitle>Kontribusi Karyawan</SectionTitle>
            <div style={{ ...card, overflow: 'hidden' }}>
                <div style={{ overflowX: 'auto' }}>
                    <table
                        style={{
                            width: '100%',
                            borderCollapse: 'collapse',
                            minWidth: 620,
                        }}
                    >
                        <thead>
                            <tr style={{ background: '#FAFBFD' }}>
                                <th style={thCell}>Karyawan</th>
                                <th style={thCell}>Entri</th>
                                <th style={thCell}>Jam</th>
                                <th style={thCell}>Nilai Jual</th>
                                <th style={thCell}>Biaya</th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.employees.length === 0 && (
                                <EmptyRow
                                    colSpan={5}
                                    icon="users"
                                    message="Belum ada jam disetujui pada periode ini."
                                />
                            )}
                            {report.employees.map((row) => (
                                <tr
                                    key={row.employee_id}
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td
                                        style={{
                                            ...cellStyle,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {row.employee}
                                    </td>
                                    <td style={cellStyle}>{row.entries}</td>
                                    <td style={cellStyle}>
                                        {hoursLabel(row.hours)}
                                    </td>
                                    <td style={cellStyle}>
                                        {rupiah(row.bill_amount)}
                                    </td>
                                    <td style={cellStyle}>
                                        {rupiah(row.cost_amount)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

/**
 * Budget usage as a bar. Falls back to the hours budget when the project is
 * budgeted in time rather than in money, and turns red past 100%.
 */
function BudgetBar({
    pct,
    fallbackPct,
    caption,
}: {
    pct: number | null;
    fallbackPct: number | null;
    caption: string;
}) {
    const value = pct ?? fallbackPct;

    if (value === null) {
        return <span style={{ color: C.muted }}>{caption}</span>;
    }

    const over = value > 100;

    return (
        <div style={{ minWidth: 150 }}>
            <div
                style={{
                    height: 6,
                    borderRadius: 100,
                    background: C.line,
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        width: Math.min(value, 100) + '%',
                        height: '100%',
                        background: over ? C.red : C.primary,
                    }}
                />
            </div>
            <div
                style={{
                    fontSize: 11.5,
                    color: over ? C.red : C.muted,
                    marginTop: 4,
                }}
            >
                {value}% · {caption}
            </div>
        </div>
    );
}
