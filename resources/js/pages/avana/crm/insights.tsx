import { Head, Link } from '@inertiajs/react';
import { Fragment } from 'react';
import CrmController from '@/actions/App/Http/Controllers/Avana/CrmController';
import { AIcon, btnOut, C, card, rp, thCell } from '@/lib/avana';
import { KpiCard } from './components';
import type { CrmInsightsProps } from './types';

const STAGE_BAR: Record<string, string> = {
    lead: '#6B7280',
    qualified: '#2F54C9',
    proposal: '#D97706',
    won: '#16A34A',
    lost: '#DC2626',
};

export default function CrmInsights({
    funnel,
    kpis,
    byOwner,
}: CrmInsightsProps) {
    // The funnel is the deal progression; "lost" is a drop-out shown apart.
    const progression = funnel.filter((row) => row.stage !== 'lost');
    const lost = funnel.find((row) => row.stage === 'lost');
    const maxCount = Math.max(1, ...progression.map((row) => row.count));

    return (
        <>
            <Head title="CRM Insights" />
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
                            <Link
                                href={CrmController.index().url}
                                style={{
                                    color: C.faint,
                                    textDecoration: 'none',
                                }}
                            >
                                CRM
                            </Link>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Insights</span>
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
                            CRM Insights Dashboard
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Funnel konversi, win rate, forecast &amp; performa
                            tim.
                        </div>
                    </div>
                    <Link
                        href={CrmController.index().url}
                        style={{ ...btnOut, textDecoration: 'none' }}
                    >
                        <AIcon name="arrow-left" size={16} color={C.text} />
                        Kembali ke Pipeline
                    </Link>
                </div>

                {/* KPI cards */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(190px, 1fr))',
                        gap: 14,
                        marginBottom: 24,
                    }}
                >
                    <KpiCard
                        label="Total Deal"
                        value={kpis.total_deals}
                        icon="handshake"
                        color={C.primary}
                    />
                    <KpiCard
                        label="Nilai Pipeline Terbuka"
                        value={rp(kpis.open_value)}
                        icon="trending-up"
                        color={C.amber}
                    />
                    <KpiCard
                        label="Nilai Won"
                        value={rp(kpis.won_value)}
                        icon="circle-check"
                        color={C.green}
                    />
                    <KpiCard
                        label="Win Rate"
                        value={`${kpis.win_rate}%`}
                        icon="target"
                        color={C.primary}
                    />
                    <KpiCard
                        label="Forecast (Tertimbang)"
                        value={rp(kpis.forecast)}
                        icon="chart-line"
                        color={C.amber}
                    />
                    <KpiCard
                        label="Aktivitas Bulan Ini"
                        value={kpis.activities_this_month}
                        icon="activity"
                        color={C.primary}
                    />
                    <KpiCard
                        label="Task Terbuka"
                        value={kpis.open_tasks}
                        icon="list-checks"
                        color={C.muted}
                    />
                    <KpiCard
                        label="Task Lewat Tempo"
                        value={kpis.overdue_tasks}
                        icon="triangle-alert"
                        color={C.red}
                    />
                </div>

                {/* Funnel */}
                <div
                    style={{ ...card, padding: '22px 24px', marginBottom: 24 }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            marginBottom: 18,
                            flexWrap: 'wrap',
                            gap: 10,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Funnel Konversi
                        </div>
                        {lost && (
                            <span
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    fontSize: 12,
                                    fontWeight: 600,
                                    color: C.red,
                                    background: 'rgba(220,38,38,.08)',
                                    borderRadius: 100,
                                    padding: '4px 11px',
                                }}
                            >
                                <AIcon
                                    name="circle-x"
                                    size={13}
                                    color={C.red}
                                />
                                Kalah: {lost.count} · {rp(lost.value)}
                            </span>
                        )}
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                        }}
                    >
                        {progression.map((row, index) => {
                            const width = 42 + (row.count / maxCount) * 58;
                            const prev = progression[index - 1];
                            const conv =
                                prev && prev.count > 0
                                    ? Math.round((row.count / prev.count) * 100)
                                    : null;
                            const color = STAGE_BAR[row.stage] ?? C.primary;

                            return (
                                <Fragment key={row.stage}>
                                    {index > 0 && (
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 5,
                                                fontSize: 11,
                                                fontWeight: 600,
                                                color: C.faint,
                                                padding: '5px 0',
                                            }}
                                        >
                                            <AIcon
                                                name="chevron-down"
                                                size={13}
                                                color={C.faint}
                                            />
                                            {conv === null
                                                ? 'lanjut'
                                                : `${conv}% lanjut`}
                                        </div>
                                    )}
                                    <div
                                        style={{
                                            width: `${width}%`,
                                            minWidth: 200,
                                            maxWidth: '100%',
                                            background: `linear-gradient(180deg, ${color}, ${color}d9)`,
                                            borderRadius: 12,
                                            padding: '13px 20px',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            gap: 14,
                                            color: '#fff',
                                            boxShadow: `0 2px 8px ${color}33`,
                                        }}
                                    >
                                        <span
                                            style={{
                                                fontSize: 13.5,
                                                fontWeight: 600,
                                            }}
                                        >
                                            {row.label}
                                        </span>
                                        <span
                                            style={{
                                                display: 'flex',
                                                alignItems: 'baseline',
                                                gap: 12,
                                            }}
                                        >
                                            <span
                                                style={{
                                                    fontSize: 20,
                                                    fontWeight: 700,
                                                    lineHeight: 1,
                                                }}
                                            >
                                                {row.count}
                                            </span>
                                            <span
                                                style={{
                                                    fontSize: 12,
                                                    opacity: 0.9,
                                                    fontVariantNumeric:
                                                        'tabular-nums',
                                                }}
                                            >
                                                {rp(row.value)}
                                            </span>
                                        </span>
                                    </div>
                                </Fragment>
                            );
                        })}
                    </div>
                </div>

                {/* Per-owner performance */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 12,
                    }}
                >
                    Performa per PIC
                </div>
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
                                    <th style={thCell}>PIC</th>
                                    <th
                                        style={{
                                            ...thCell,
                                            textAlign: 'right',
                                        }}
                                    >
                                        Deal
                                    </th>
                                    <th
                                        style={{
                                            ...thCell,
                                            textAlign: 'right',
                                        }}
                                    >
                                        Won
                                    </th>
                                    <th
                                        style={{
                                            ...thCell,
                                            textAlign: 'right',
                                        }}
                                    >
                                        Nilai Won
                                    </th>
                                    <th
                                        style={{
                                            ...thCell,
                                            textAlign: 'right',
                                        }}
                                    >
                                        Pipeline Terbuka
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {byOwner.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            style={{
                                                padding: '24px',
                                                textAlign: 'center',
                                                color: C.faint,
                                                fontSize: 13,
                                            }}
                                        >
                                            Belum ada data deal.
                                        </td>
                                    </tr>
                                )}
                                {byOwner.map((owner) => (
                                    <tr
                                        key={owner.name}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: '13px 18px',
                                                fontSize: 13,
                                                fontWeight: 600,
                                                color: C.text,
                                            }}
                                        >
                                            {owner.name}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 18px',
                                                fontSize: 13,
                                                color: C.muted,
                                                textAlign: 'right',
                                            }}
                                        >
                                            {owner.deals}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 18px',
                                                fontSize: 13,
                                                color: C.green,
                                                fontWeight: 600,
                                                textAlign: 'right',
                                            }}
                                        >
                                            {owner.won}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 18px',
                                                fontSize: 13,
                                                color: C.text,
                                                textAlign: 'right',
                                                fontVariantNumeric:
                                                    'tabular-nums',
                                            }}
                                        >
                                            {rp(owner.won_value)}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 18px',
                                                fontSize: 13,
                                                color: C.muted,
                                                textAlign: 'right',
                                                fontVariantNumeric:
                                                    'tabular-nums',
                                            }}
                                        >
                                            {rp(owner.open_value)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
