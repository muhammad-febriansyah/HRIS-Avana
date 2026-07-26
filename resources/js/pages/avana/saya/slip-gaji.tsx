import { Head, Link } from '@inertiajs/react';
import { AIcon, C, rp, thCell } from '@/lib/avana';
import {
    EmptyState,
    formatDate,
    PageHeader,
    PageShell,
    Panel,
} from './components';

interface Payslip {
    id: number;
    period: string | null;
    gross: number;
    deductions: number;
    tax: number;
    bpjs_employee: number;
    net: number;
    issued_at: string | null;
}

export default function SayaSlipGaji({ payslips }: { payslips: Payslip[] }) {
    return (
        <>
            <Head title="Slip Gaji Saya" />
            <PageShell>
                <PageHeader
                    title="Slip Gaji Saya"
                    subtitle="Riwayat slip gaji yang diterbitkan untukmu."
                />

                <Panel
                    title="Slip Gaji"
                    subtitle={`${payslips.length.toLocaleString('id-ID')} slip`}
                    padded={false}
                >
                    {payslips.length === 0 ? (
                        <EmptyState
                            icon="receipt"
                            message="Belum ada slip gaji yang diterbitkan."
                        />
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    minWidth: 760,
                                }}
                            >
                                <thead>
                                    <tr style={{ background: '#FAFBFD' }}>
                                        <th style={thCell}>Periode</th>
                                        <th style={thCell}>Bruto</th>
                                        <th style={thCell}>Potongan</th>
                                        <th style={thCell}>PPh 21</th>
                                        <th style={thCell}>Diterima</th>
                                        <th
                                            style={{
                                                ...thCell,
                                                textAlign: 'right',
                                                padding: '12px 18px',
                                            }}
                                        >
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payslips.map((slip) => (
                                        <tr
                                            key={slip.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td style={cell}>
                                                <div
                                                    style={{ fontWeight: 600 }}
                                                >
                                                    {slip.period ?? '—'}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    Terbit{' '}
                                                    {formatDate(slip.issued_at)}
                                                </div>
                                            </td>
                                            <td style={cell}>
                                                {rp(slip.gross)}
                                            </td>
                                            <td style={cell}>
                                                {rp(slip.deductions)}
                                            </td>
                                            <td style={cell}>{rp(slip.tax)}</td>
                                            <td
                                                style={{
                                                    ...cell,
                                                    fontWeight: 700,
                                                    color: C.navy,
                                                }}
                                            >
                                                {rp(slip.net)}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 18px',
                                                    textAlign: 'right',
                                                }}
                                            >
                                                <Link
                                                    href={`/avana/saya/slip-gaji/${slip.id}`}
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: 6,
                                                        fontSize: 12.5,
                                                        fontWeight: 600,
                                                        color: C.primary,
                                                        textDecoration: 'none',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="eye"
                                                        size={14}
                                                        color={C.primary}
                                                    />
                                                    Lihat Rincian
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            </PageShell>
        </>
    );
}

const cell = {
    padding: '13px 16px',
    fontSize: 13,
    color: C.text,
} as const;
