import { router } from '@inertiajs/react';
import { useState } from 'react';
import { AIcon, C } from '@/lib/avana';
import { Panel, btnSmExport, btnSmNeutral, num, td, th } from './components';
import { SCHEME_OPTIONS } from './types';
import type { PaginationMeta, Pph21EmployeeRow, Pph21Filters } from './types';

/**
 * Per-employee PPh 21 for the selected period: the base, the TER bracket it
 * landed in, the tax, and a link to that employee's 1721-A1.
 */
export function EmployeeTable({
    employees,
    meta,
    filters,
    year,
}: {
    employees: Pph21EmployeeRow[];
    meta: PaginationMeta | null;
    filters: Pph21Filters;
    year: number | null;
}) {
    const [q, setQ] = useState(filters.search);

    const push = (patch: Record<string, string | number | null>) =>
        router.get(
            '/avana/payroll/pph21-report',
            { ...filters, ...patch, page: 1 },
            { preserveState: true, preserveScroll: true },
        );

    const goToPage = (page: number) =>
        router.get(
            '/avana/payroll/pph21-report',
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );

    return (
        <Panel
            title="Rincian PPh 21 per Karyawan"
            right={
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    <input
                        data-testid="cari-karyawan"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                push({ search: q });
                            }
                        }}
                        placeholder="Cari nama / NIP…"
                        style={{
                            padding: '7px 11px',
                            border: `1px solid ${C.border}`,
                            borderRadius: 8,
                            fontSize: 12.5,
                            outline: 'none',
                            width: 190,
                        }}
                    />
                    <select
                        data-testid="filter-skema"
                        value={filters.scheme}
                        onChange={(e) => push({ scheme: e.target.value })}
                        style={{
                            padding: '7px 11px',
                            border: `1px solid ${C.border}`,
                            borderRadius: 8,
                            fontSize: 12.5,
                            background: '#fff',
                            color: C.text,
                        }}
                    >
                        {SCHEME_OPTIONS.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
            }
        >
            <div style={{ overflowX: 'auto' }}>
                <table
                    data-testid="employee-table"
                    style={{ width: '100%', borderCollapse: 'collapse' }}
                >
                    <thead>
                        <tr style={{ background: '#FAFBFE' }}>
                            <th style={th}>Karyawan</th>
                            <th style={th}>NPWP</th>
                            <th style={th}>PTKP</th>
                            <th style={th}>TER</th>
                            <th style={{ ...th, textAlign: 'right' }}>
                                Bruto Pajak
                            </th>
                            <th style={{ ...th, textAlign: 'right' }}>
                                PPh 21
                            </th>
                            <th style={th}>Skema</th>
                            <th style={th} />
                        </tr>
                    </thead>
                    <tbody>
                        {employees.length === 0 && (
                            <tr>
                                <td
                                    colSpan={8}
                                    style={{
                                        ...td,
                                        textAlign: 'center',
                                        color: C.faint,
                                        padding: '28px 16px',
                                    }}
                                >
                                    Tidak ada data pada masa pajak ini.
                                </td>
                            </tr>
                        )}
                        {employees.map((row) => (
                            <tr
                                key={row.id}
                                style={{ borderTop: `1px solid ${C.line}` }}
                            >
                                <td style={td}>
                                    <div style={{ fontWeight: 600 }}>
                                        {row.name}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: C.faint,
                                        }}
                                    >
                                        {row.employee_number ?? '—'}
                                    </div>
                                </td>
                                <td
                                    style={{
                                        ...td,
                                        color: row.npwp ? C.text : C.amber,
                                    }}
                                >
                                    {row.npwp ?? 'Belum diisi'}
                                </td>
                                <td
                                    style={{
                                        ...td,
                                        color: row.ptkp_valid
                                            ? C.text
                                            : C.amber,
                                    }}
                                >
                                    {row.ptkp_status ?? 'Belum diisi'}
                                </td>
                                <td style={td}>
                                    {/* A THR row lands in a bracket but carries
                                        no rate of its own — the tax is the
                                        difference between two TER runs — so a
                                        "0%" there would read as a real rate. */}
                                    {row.ter_category
                                        ? row.ter_rate !== null
                                            ? `${row.ter_category} · ${row.ter_rate}%`
                                            : row.ter_category
                                        : '—'}
                                </td>
                                <td style={num}>{row.gross}</td>
                                <td style={{ ...num, fontWeight: 600 }}>
                                    {row.tax}
                                </td>
                                <td style={{ ...td, fontSize: 12 }}>
                                    {row.method_label ?? '—'}
                                </td>
                                <td style={{ ...td, textAlign: 'right' }}>
                                    {year !== null &&
                                        row.employee_route_key !== null && (
                                            <a
                                                data-testid="unduh-bukti-potong"
                                                href={`/avana/payroll/1721/${row.employee_route_key}?year=${year}`}
                                                style={{
                                                    ...btnSmExport,
                                                    height: 30,
                                                    padding: '0 11px',
                                                }}
                                            >
                                                <AIcon
                                                    name="download"
                                                    size={13}
                                                    color="#fff"
                                                />
                                                Bukti Potong
                                            </a>
                                        )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {meta !== null && meta.last_page > 1 && (
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        padding: '11px 20px',
                        borderTop: `1px solid ${C.line}`,
                        fontSize: 12.5,
                        color: C.muted,
                    }}
                >
                    <span>
                        {meta.from ?? 0}–{meta.to ?? 0} dari {meta.total}
                    </span>
                    <div style={{ display: 'flex', gap: 6 }}>
                        <button
                            type="button"
                            disabled={meta.current_page <= 1}
                            onClick={() => goToPage(meta.current_page - 1)}
                            style={pageBtn(meta.current_page <= 1)}
                        >
                            Sebelumnya
                        </button>
                        <button
                            type="button"
                            disabled={meta.current_page >= meta.last_page}
                            onClick={() => goToPage(meta.current_page + 1)}
                            style={pageBtn(meta.current_page >= meta.last_page)}
                        >
                            Berikutnya
                        </button>
                    </div>
                </div>
            )}
        </Panel>
    );
}

function pageBtn(disabled: boolean): React.CSSProperties {
    return {
        ...btnSmNeutral,
        height: 30,
        padding: '0 12px',
        // Dimmed rather than emptied: a white disabled button would be the only
        // unfilled control left on the screen.
        background: disabled ? '#F1F4FA' : btnSmNeutral.background,
        color: disabled ? C.faint : C.navy,
        cursor: disabled ? 'not-allowed' : 'pointer',
    };
}
