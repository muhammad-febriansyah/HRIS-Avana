import { Head, Link } from '@inertiajs/react';
import { AIcon, btnOut, C, card, rp } from '@/lib/avana';
import { formatDate, PageShell, Panel } from './components';

interface Line {
    name: string;
    amount: number;
}

interface Payslip {
    id: number;
    period: string | null;
    gross: number;
    /** Numeric total, not the line list — see earning_lines/deduction_lines. */
    deductions: number;
    tax: number;
    bpjs_employee: number;
    net: number;
    issued_at: string | null;
    earning_lines: Line[];
    deduction_lines: Line[];
}

export default function SayaSlipGajiDetail({ payslip }: { payslip: Payslip }) {
    const earnings = payslip.earning_lines ?? [];
    const deductionLines = payslip.deduction_lines ?? [];

    return (
        <>
            <Head title={`Slip Gaji ${payslip.period ?? ''}`} />
            <PageShell>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 14,
                    }}
                >
                    <Link
                        href="/avana/saya/slip-gaji"
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        Slip Gaji Saya
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>
                        {payslip.period ?? 'Rincian'}
                    </span>
                </div>

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
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: 0,
                                letterSpacing: '-.01em',
                            }}
                        >
                            {payslip.period ?? 'Slip Gaji'}
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Diterbitkan {formatDate(payslip.issued_at)}
                        </div>
                    </div>
                    <a
                        href={`/api/v1/me/payslips/${payslip.id}/pdf`}
                        target="_blank"
                        rel="noreferrer"
                        style={{ ...btnOut, textDecoration: 'none' }}
                    >
                        <AIcon name="download" size={16} color={C.text} />
                        Unduh PDF
                    </a>
                </div>

                {/* Net pay highlight */}
                <div
                    style={{
                        ...card,
                        padding: '22px 24px',
                        marginBottom: 16,
                        background: `${C.primary}0a`,
                        border: `1px solid ${C.primary}33`,
                    }}
                >
                    <div style={{ fontSize: 12.5, color: C.muted }}>
                        Gaji Diterima
                    </div>
                    <div
                        style={{
                            fontSize: 30,
                            fontWeight: 700,
                            color: C.navy,
                            marginTop: 4,
                            letterSpacing: '-.02em',
                        }}
                    >
                        {rp(payslip.net)}
                    </div>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                        alignItems: 'start',
                    }}
                >
                    <Panel title="Pendapatan" padded={false}>
                        <LineList
                            lines={earnings}
                            emptyText="Tidak ada komponen pendapatan."
                            total={payslip.gross}
                            totalLabel="Total Bruto"
                            tone={C.green}
                        />
                    </Panel>

                    <Panel title="Potongan" padded={false}>
                        <LineList
                            lines={deductionLines}
                            emptyText="Tidak ada komponen potongan."
                            total={payslip.deductions}
                            totalLabel="Total Potongan"
                            tone={C.red}
                        />
                    </Panel>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                        marginTop: 16,
                    }}
                >
                    <SummaryRow
                        label="BPJS (karyawan)"
                        value={rp(payslip.bpjs_employee)}
                    />
                    <SummaryRow label="PPh 21" value={rp(payslip.tax)} />
                </div>
            </PageShell>
        </>
    );
}

function LineList({
    lines,
    emptyText,
    total,
    totalLabel,
    tone,
}: {
    lines: Line[];
    emptyText: string;
    total: number;
    totalLabel: string;
    tone: string;
}) {
    return (
        <div>
            {lines.length === 0 ? (
                <div
                    style={{
                        padding: '22px 18px',
                        fontSize: 13,
                        color: C.muted,
                        textAlign: 'center',
                    }}
                >
                    {emptyText}
                </div>
            ) : (
                lines.map((line, index) => (
                    <div
                        key={`${line.name}-${index}`}
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            gap: 12,
                            padding: '12px 18px',
                            borderTop:
                                index === 0 ? 'none' : `1px solid ${C.line}`,
                            fontSize: 13,
                        }}
                    >
                        <span style={{ color: C.text }}>{line.name}</span>
                        <span style={{ color: C.text, fontWeight: 500 }}>
                            {rp(line.amount)}
                        </span>
                    </div>
                ))
            )}
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    gap: 12,
                    padding: '13px 18px',
                    borderTop: `1px solid ${C.border}`,
                    background: '#FAFBFD',
                    fontSize: 13,
                    fontWeight: 700,
                }}
            >
                <span style={{ color: C.navy }}>{totalLabel}</span>
                <span style={{ color: tone }}>{rp(total)}</span>
            </div>
        </div>
    );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
    return (
        <div
            style={{
                ...card,
                padding: '16px 18px',
                display: 'flex',
                justifyContent: 'space-between',
                fontSize: 13,
            }}
        >
            <span style={{ color: C.muted }}>{label}</span>
            <span style={{ color: C.navy, fontWeight: 600 }}>{value}</span>
        </div>
    );
}
