import type { CSSProperties } from 'react';
import { useState } from 'react';
import { DatePicker } from '@/components/avana/date-picker';
import { AIcon, C, card } from '@/lib/avana';
import type { PeriodOption, ReportCard } from './types';

const btnBase: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 6,
    height: 34,
    padding: '0 12px',
    borderRadius: 8,
    fontSize: 12.5,
    fontWeight: 600,
    cursor: 'pointer',
    textDecoration: 'none',
    transition: 'background .15s,box-shadow .15s',
};

/** Primary Excel download button. */
export const btnExcel: CSSProperties = {
    ...btnBase,
    background: C.green,
    color: '#fff',
    border: 'none',
};

/** Secondary (outline) download button for CSV / PDF. */
const btnGhost: CSSProperties = {
    ...btnBase,
    background: '#fff',
    color: C.navy,
    border: `1px solid ${C.border}`,
};

const filterInput: CSSProperties = {
    height: 34,
    padding: '0 10px',
    fontSize: 12.5,
    color: C.navy,
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    outline: 'none',
};

/** Build the export URL for a report + format, appending any active filters. */
function exportUrl(
    type: string,
    format: string,
    query: Record<string, string>,
): string {
    const params = new URLSearchParams();

    if (format !== 'csv') {
        params.set('format', format);
    }

    for (const [key, value] of Object.entries(query)) {
        if (value !== '') {
            params.set(key, value);
        }
    }

    const qs = params.toString();

    return '/avana/laporan/export/' + type + (qs ? '?' + qs : '');
}

/** A single report card: icon, copy, headline stat, period filter + downloads. */
export function ReportCardItem({
    report,
    periods,
}: {
    report: ReportCard;
    periods: PeriodOption[];
}) {
    const [start, setStart] = useState('');
    const [end, setEnd] = useState('');
    const [periodId, setPeriodId] = useState('');

    const query: Record<string, string> =
        report.periodFilter === 'range'
            ? { start, end }
            : report.periodFilter === 'period'
              ? { period_id: periodId }
              : {};

    return (
        <div
            style={{
                ...card,
                padding: '22px 24px',
                display: 'flex',
                flexDirection: 'column',
                gap: 16,
            }}
        >
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 14 }}>
                <div
                    style={{
                        width: 46,
                        height: 46,
                        borderRadius: 12,
                        flex: 'none',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background: report.color + '1a',
                        color: report.color,
                    }}
                >
                    <AIcon name={report.icon} size={22} color={report.color} />
                </div>
                <div>
                    <div
                        style={{ fontSize: 16, fontWeight: 600, color: C.navy }}
                    >
                        {report.title}
                    </div>
                    <div
                        style={{
                            fontSize: 13,
                            color: C.muted,
                            marginTop: 4,
                            lineHeight: 1.5,
                        }}
                    >
                        {report.desc}
                    </div>
                </div>
            </div>

            {/* Optional period filter */}
            {report.periodFilter === 'range' && (
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <DatePicker
                        value={start}
                        onChange={(nextValue) => setStart(nextValue)}
                        placeholder="Pilih tanggal"
                        width="100%"
                    />
                    <span style={{ fontSize: 12, color: C.faint }}>s.d.</span>
                    <DatePicker
                        value={end}
                        onChange={(nextValue) => setEnd(nextValue)}
                        placeholder="Pilih tanggal"
                        width="100%"
                    />
                </div>
            )}
            {report.periodFilter === 'period' && (
                <select
                    value={periodId}
                    onChange={(e) => setPeriodId(e.target.value)}
                    style={{ ...filterInput, width: '100%' }}
                    aria-label="Periode payroll"
                >
                    <option value="">Semua periode</option>
                    {periods.map((p) => (
                        <option key={p.id} value={String(p.id)}>
                            {p.name}
                        </option>
                    ))}
                </select>
            )}

            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-end',
                    justifyContent: 'space-between',
                    gap: 12,
                    marginTop: 'auto',
                }}
            >
                <div>
                    <div style={{ fontSize: 12, color: C.faint }}>
                        {report.statLabel}
                    </div>
                    <div
                        style={{
                            fontSize: 22,
                            fontWeight: 700,
                            color: C.navy,
                            marginTop: 2,
                            fontVariantNumeric: 'tabular-nums',
                        }}
                    >
                        {report.statValue}
                    </div>
                </div>
                <div
                    style={{
                        display: 'flex',
                        gap: 8,
                        flexWrap: 'wrap',
                        justifyContent: 'flex-end',
                    }}
                >
                    <a
                        href={exportUrl(report.type, 'xlsx', query)}
                        download
                        style={btnExcel}
                    >
                        <AIcon name="sheet" size={15} color="#fff" />
                        Excel
                    </a>
                    <a
                        href={exportUrl(report.type, 'pdf', query)}
                        download
                        style={btnGhost}
                    >
                        <AIcon name="file-text" size={15} color={C.navy} />
                        PDF
                    </a>
                    <a
                        href={exportUrl(report.type, 'csv', query)}
                        download
                        style={btnGhost}
                    >
                        <AIcon name="download" size={15} color={C.navy} />
                        CSV
                    </a>
                </div>
            </div>
        </div>
    );
}
