import { AIcon, C, card, statusBadge } from '@/lib/avana';
import type { PayrollSummary } from './types';

/** Latest-run summary card: period header + four KPI tiles. */
export function SummaryCard({ summary }: { summary: PayrollSummary }) {
    const summaryBadge = statusBadge(summary.status_label);

    return (
        <div style={{ ...card, marginBottom: 18, overflow: 'hidden' }}>
            <div
                style={{
                    padding: '18px 22px',
                    borderBottom: `1px solid ${C.line}`,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    flexWrap: 'wrap',
                    gap: 10,
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 11,
                    }}
                >
                    <div
                        style={{
                            width: 40,
                            height: 40,
                            borderRadius: 10,
                            background: 'rgba(47,84,201,.1)',
                            color: C.primary,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                        }}
                    >
                        <AIcon name="calendar" size={20} color={C.primary} />
                    </div>
                    <div>
                        <div
                            style={{
                                fontSize: 16,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Periode {summary.period ?? '—'}
                        </div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                            }}
                        >
                            {summary.employee_count.toLocaleString('id-ID')}{' '}
                            karyawan
                        </div>
                    </div>
                </div>
                <span
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 6,
                        padding: '5px 12px',
                        borderRadius: 100,
                        fontSize: 12,
                        fontWeight: 600,
                        color: summaryBadge.color,
                        background: summaryBadge.bg,
                    }}
                >
                    <AIcon
                        name="circle-dot"
                        size={13}
                        color={summaryBadge.color}
                    />
                    {summaryBadge.label}
                </span>
            </div>
            <div
                className="avn-kpi"
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(4,1fr)',
                    gap: 0,
                }}
            >
                <div
                    style={{
                        padding: '20px 22px',
                        borderRight: `1px solid ${C.line}`,
                    }}
                >
                    <div style={{ fontSize: 12.5, color: C.muted }}>
                        Total Gross
                    </div>
                    <div
                        style={{
                            fontSize: 21,
                            fontWeight: 700,
                            color: C.navy,
                            marginTop: 5,
                            fontVariantNumeric: 'tabular-nums',
                        }}
                    >
                        {summary.total_gross}
                    </div>
                </div>
                <div
                    style={{
                        padding: '20px 22px',
                        borderRight: `1px solid ${C.line}`,
                    }}
                >
                    <div style={{ fontSize: 12.5, color: C.muted }}>
                        Total Potongan
                    </div>
                    <div
                        style={{
                            fontSize: 21,
                            fontWeight: 700,
                            color: C.amber,
                            marginTop: 5,
                            fontVariantNumeric: 'tabular-nums',
                        }}
                    >
                        {summary.total_deduction}
                    </div>
                </div>
                <div
                    style={{
                        padding: '20px 22px',
                        borderRight: `1px solid ${C.line}`,
                    }}
                >
                    <div style={{ fontSize: 12.5, color: C.muted }}>
                        Total Pajak (PPh 21)
                    </div>
                    <div
                        style={{
                            fontSize: 21,
                            fontWeight: 700,
                            color: C.red,
                            marginTop: 5,
                            fontVariantNumeric: 'tabular-nums',
                        }}
                    >
                        {summary.total_tax}
                    </div>
                </div>
                <div
                    style={{
                        padding: '20px 22px',
                        background: '#FAFBFE',
                    }}
                >
                    <div style={{ fontSize: 12.5, color: C.muted }}>
                        Total Net (Take Home)
                    </div>
                    <div
                        style={{
                            fontSize: 21,
                            fontWeight: 700,
                            color: C.green,
                            marginTop: 5,
                            fontVariantNumeric: 'tabular-nums',
                        }}
                    >
                        {summary.total_net}
                    </div>
                </div>
            </div>
        </div>
    );
}
