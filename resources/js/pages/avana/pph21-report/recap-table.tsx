import { router } from '@inertiajs/react';
import { C } from '@/lib/avana';
import { Panel, StatusPill, btnSmPrimary, num, td, th } from './components';
import type { RecapRow } from './types';

/** Monthly PPh 21 recap — one row per tax period, newest first. */
export function RecapTable({
    recap,
    selectedId,
}: {
    recap: RecapRow[];
    selectedId: number | null;
}) {
    const open = (id: number) =>
        router.get(
            '/avana/payroll/pph21-report',
            { period: id },
            { preserveScroll: true },
        );

    return (
        <Panel id="rekap" title="Rekap PPh 21 Bulanan">
            <div style={{ overflowX: 'auto' }}>
                <table
                    data-testid="rekap-table"
                    style={{ width: '100%', borderCollapse: 'collapse' }}
                >
                    <thead>
                        <tr style={{ background: '#FAFBFE' }}>
                            <th style={th}>Masa Pajak</th>
                            <th style={{ ...th, textAlign: 'right' }}>
                                Karyawan
                            </th>
                            <th style={{ ...th, textAlign: 'right' }}>
                                Penghasilan Bruto
                            </th>
                            <th style={{ ...th, textAlign: 'right' }}>
                                PPh 21
                            </th>
                            <th style={{ ...th, textAlign: 'right' }}>
                                Bukti Potong
                            </th>
                            <th style={th}>Setoran</th>
                            <th style={th}>Pelaporan</th>
                            <th style={th} />
                        </tr>
                    </thead>
                    <tbody>
                        {recap.length === 0 && (
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
                                    Belum ada masa pajak.
                                </td>
                            </tr>
                        )}
                        {recap.map((row) => (
                            <tr
                                key={row.id}
                                style={{
                                    borderTop: `1px solid ${C.line}`,
                                    background:
                                        row.id === selectedId
                                            ? 'rgba(47,84,201,.04)'
                                            : undefined,
                                }}
                            >
                                <td style={{ ...td, fontWeight: 600 }}>
                                    {row.name}
                                </td>
                                <td style={num}>
                                    {row.employees.toLocaleString('id-ID')}
                                </td>
                                <td style={num}>{row.gross}</td>
                                <td style={{ ...num, fontWeight: 600 }}>
                                    {row.tax}
                                </td>
                                <td style={num}>{row.bukti_potong}</td>
                                <td style={td}>
                                    <StatusPill status={row.deposit_status} />
                                </td>
                                <td style={td}>
                                    <StatusPill status={row.report_status} />
                                </td>
                                <td style={{ ...td, textAlign: 'right' }}>
                                    <button
                                        type="button"
                                        onClick={() => open(row.id)}
                                        style={{
                                            ...btnSmPrimary,
                                            height: 30,
                                            padding: '0 12px',
                                        }}
                                    >
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div
                style={{
                    padding: '11px 20px',
                    borderTop: `1px solid ${C.line}`,
                    fontSize: 11.5,
                    color: C.faint,
                }}
            >
                Penghasilan bruto adalah dasar pengenaan pajak (termasuk premi
                BPJS ditanggung perusahaan bila diaktifkan), bukan gaji kotor
                pada slip.
            </div>
        </Panel>
    );
}
