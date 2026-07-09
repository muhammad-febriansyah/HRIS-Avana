import { Head, Link, usePage } from '@inertiajs/react';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';

type Kpi = {
    label: string;
    value: string;
    icon: string;
    iconBg: string;
    iconColor: string;
    delta: string;
    deltaIcon: string;
    deltaColor: string;
};
type Series = { labels: string[]; values: number[] };
type RecentTenant = {
    id: number;
    ini: string;
    avBg: string;
    name: string;
    type: string;
};
type OverdueInvoice = {
    id: number;
    number: string;
    tenant: string;
    amount: string;
    due: string;
};
type QuotaAlert = {
    id: number;
    name: string;
    usage: string;
    used: number;
    max: number;
    pct: number;
};

type SuperDashboardProps = {
    kpis: Kpi[];
    tenantGrowth: Series;
    revenueTrend: Series;
    recentTenants: RecentTenant[];
    overdueInvoices: OverdueInvoice[];
    quotaAlerts: QuotaAlert[];
    userName: string;
    today: string;
};

function TrendChart({ data }: { data: Series }) {
    const values = data.values.length ? data.values : [0];
    const min = Math.min(...values) - 1;
    const max = Math.max(...values) + 1;
    const W = 420,
        H = 150,
        pad = 8;
    const span = Math.max(1, max - min);
    const pts = values.map((v, i) => {
        const x = pad + i * ((W - pad * 2) / Math.max(1, values.length - 1));
        const y = H - 12 - ((v - min) / span) * (H - 40);

        return [x, y] as const;
    });
    const line = pts
        .map(
            (p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1),
        )
        .join(' ');
    const area =
        line +
        ` L${pts[pts.length - 1][0].toFixed(1)} ${H - 12} L${pts[0][0].toFixed(1)} ${H - 12} Z`;

    return (
        <svg
            viewBox={`0 0 ${W} ${H}`}
            style={{ width: '100%', height: 170, overflow: 'visible' }}
        >
            <defs>
                <linearGradient id="tgc" x1={0} y1={0} x2={0} y2={1}>
                    <stop offset="0%" stopColor="#2F54C9" stopOpacity={0.18} />
                    <stop offset="100%" stopColor="#2F54C9" stopOpacity={0} />
                </linearGradient>
            </defs>
            <path d={area} fill="url(#tgc)" />
            <path
                d={line}
                fill="none"
                stroke="#2F54C9"
                strokeWidth={2.5}
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            {pts.map((p, i) => (
                <circle
                    key={i}
                    cx={p[0]}
                    cy={p[1]}
                    r={3.5}
                    fill="#fff"
                    stroke="#2F54C9"
                    strokeWidth={2}
                />
            ))}
            {data.labels.map((l, i) => (
                <text
                    key={'t' + i}
                    x={pts[i]?.[0] ?? 0}
                    y={H + 4}
                    fontSize={11}
                    fill="#9CA3AF"
                    textAnchor="middle"
                    fontFamily="Poppins"
                >
                    {l}
                </text>
            ))}
        </svg>
    );
}

function RevenueChart({ data }: { data: Series }) {
    const max = Math.max(1, ...data.values);

    return (
        <div
            style={{
                display: 'flex',
                gap: 16,
                alignItems: 'flex-end',
                height: 200,
                paddingTop: 10,
            }}
        >
            {data.values.map((v, i) => (
                <div
                    key={i}
                    style={{
                        flex: 1,
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        gap: 9,
                        height: '100%',
                        justifyContent: 'flex-end',
                    }}
                >
                    <div
                        style={{ fontSize: 11.5, fontWeight: 600, color: C.navy }}
                    >
                        {v}
                    </div>
                    <div
                        style={{
                            width: '100%',
                            maxWidth: 34,
                            height: Math.max(4, (v / max) * 150),
                            background:
                                i === data.values.length - 1
                                    ? C.green
                                    : 'linear-gradient(180deg,#6E9BE6,#2F54C9)',
                            borderRadius: '7px 7px 0 0',
                        }}
                    />
                    <div style={{ fontSize: 11, color: C.faint }}>
                        {data.labels[i]}
                    </div>
                </div>
            ))}
        </div>
    );
}

function QuotaChart({ data }: { data: QuotaAlert[] }) {
    // Scale bars to the worst offender so over-quota clients are distinguishable
    // (all clamped-at-100% bars looked identical before). Keep the 100% quota
    // line inside the axis so "how far over" is readable at a glance.
    const axisMax = Math.max(120, ...data.map((q) => q.pct));
    const quotaLine = (100 / axisMax) * 100;

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            {data.map((q) => {
                const over = q.pct >= 100;
                const color = over ? C.red : C.amber;

                return (
                    <div key={q.id}>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'baseline',
                                marginBottom: 6,
                            }}
                        >
                            <span
                                style={{
                                    fontSize: 13,
                                    fontWeight: 500,
                                    color: C.text,
                                }}
                            >
                                {q.name}
                            </span>
                            <span style={{ fontSize: 12, color: C.muted }}>
                                {q.usage} karyawan{' '}
                                <span style={{ fontWeight: 600, color }}>
                                    ({q.pct}%)
                                </span>
                            </span>
                        </div>
                        <div
                            style={{
                                position: 'relative',
                                height: 12,
                                borderRadius: 6,
                                background: C.line,
                                overflow: 'hidden',
                            }}
                        >
                            <div
                                style={{
                                    width: `${(q.pct / axisMax) * 100}%`,
                                    height: '100%',
                                    borderRadius: 6,
                                    background: color,
                                }}
                            />
                            {/* 100% quota reference — bar past it = over quota */}
                            <div
                                style={{
                                    position: 'absolute',
                                    top: 0,
                                    bottom: 0,
                                    left: `${quotaLine}%`,
                                    width: 2,
                                    background: '#fff',
                                    boxShadow: `0 0 0 1px ${C.navy}`,
                                    opacity: 0.5,
                                }}
                            />
                        </div>
                    </div>
                );
            })}
            {/* shared axis footer: 0 — 100% quota line — axis max */}
            <div
                style={{
                    position: 'relative',
                    height: 16,
                    marginTop: -4,
                    fontSize: 10.5,
                    color: C.faint,
                }}
            >
                <span style={{ position: 'absolute', left: 0 }}>0</span>
                <span
                    style={{
                        position: 'absolute',
                        left: `${quotaLine}%`,
                        transform: 'translateX(-50%)',
                        color: C.muted,
                        fontWeight: 600,
                    }}
                >
                    kuota 100%
                </span>
                <span style={{ position: 'absolute', right: 0 }}>
                    {axisMax}%
                </span>
            </div>
        </div>
    );
}

const cardHeader = {
    padding: '18px 20px',
    borderBottom: `1px solid ${C.border}`,
} as const;
const cardTitle = { fontSize: 15, fontWeight: 600, color: C.navy } as const;

export default function DashboardAdmin() {
    const { props } = usePage<SuperDashboardProps>();
    const {
        kpis,
        tenantGrowth,
        revenueTrend,
        recentTenants,
        overdueInvoices,
        quotaAlerts,
        userName,
        today,
    } = props;

    return (
        <>
            <Head title="Dashboard" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 24,
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
                            <span style={{ color: C.muted }}>Dashboard</span>
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
                            Halo, {userName} 👋
                        </h1>
                        <div
                            style={{ fontSize: 14, color: C.muted, marginTop: 4 }}
                        >
                            Ringkasan platform — {today}
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <Link
                            href="/avana/billing"
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="receipt" size={16} />
                            Billing
                        </Link>
                        <Link
                            href="/avana/klien/create"
                            style={{ ...btnP, textDecoration: 'none' }}
                        >
                            <AIcon name="plus" size={16} />
                            Tambah Klien
                        </Link>
                    </div>
                </div>

                <div
                    className="avn-kpi"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4,1fr)',
                        gap: 16,
                        marginBottom: 20,
                    }}
                >
                    {kpis.map((k, i) => (
                        <div key={i} style={{ ...card, padding: '18px 20px' }}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                }}
                            >
                                <div
                                    style={{
                                        width: 40,
                                        height: 40,
                                        borderRadius: 10,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        background: k.iconBg,
                                        color: k.iconColor,
                                    }}
                                >
                                    <AIcon
                                        name={k.icon}
                                        size={20}
                                        color={k.iconColor}
                                    />
                                </div>
                                {k.delta && (
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 3,
                                            fontSize: 12,
                                            fontWeight: 600,
                                            color: k.deltaColor,
                                        }}
                                    >
                                        <AIcon
                                            name={k.deltaIcon}
                                            size={13}
                                            color={k.deltaColor}
                                        />
                                        {k.delta}
                                    </div>
                                )}
                            </div>
                            <div
                                style={{
                                    fontSize: 27,
                                    fontWeight: 700,
                                    color: C.navy,
                                    marginTop: 14,
                                    letterSpacing: '-.01em',
                                }}
                            >
                                {k.value}
                            </div>
                            <div
                                style={{
                                    fontSize: 13,
                                    color: C.muted,
                                    marginTop: 2,
                                }}
                            >
                                {k.label}
                            </div>
                        </div>
                    ))}
                </div>

                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1.6fr 1fr',
                        gap: 16,
                        marginBottom: 20,
                    }}
                >
                    <div style={card}>
                        <div style={cardHeader}>
                            <div style={cardTitle}>Pertumbuhan Klien</div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    marginTop: 2,
                                }}
                            >
                                6 bulan terakhir
                            </div>
                        </div>
                        <div style={{ padding: 20 }}>
                            <TrendChart data={tenantGrowth} />
                        </div>
                    </div>
                    <div style={card}>
                        <div style={cardHeader}>
                            <div style={cardTitle}>Revenue Terbayar</div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    marginTop: 2,
                                }}
                            >
                                Juta rupiah / bulan
                            </div>
                        </div>
                        <div style={{ padding: 20 }}>
                            <RevenueChart data={revenueTrend} />
                        </div>
                    </div>
                </div>

                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                        marginBottom: 20,
                    }}
                >
                    <div style={card}>
                        <div style={cardHeader}>
                            <div style={cardTitle}>Klien Terbaru</div>
                        </div>
                        <div style={{ padding: '6px 20px 16px' }}>
                            {recentTenants.length === 0 && (
                                <div
                                    style={{
                                        padding: '14px 0',
                                        fontSize: 13,
                                        color: C.faint,
                                    }}
                                >
                                    Belum ada klien.
                                </div>
                            )}
                            {recentTenants.map((t) => (
                                <Link
                                    key={t.id}
                                    href={`/avana/klien/${t.id}/edit`}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 12,
                                        padding: '11px 0',
                                        borderBottom: `1px solid ${C.line}`,
                                        textDecoration: 'none',
                                    }}
                                >
                                    <div
                                        style={{
                                            width: 34,
                                            height: 34,
                                            borderRadius: '50%',
                                            flex: 'none',
                                            background: t.avBg,
                                            color: '#fff',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            fontSize: 12.5,
                                            fontWeight: 600,
                                        }}
                                    >
                                        {t.ini}
                                    </div>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div
                                            style={{
                                                fontSize: 13,
                                                fontWeight: 500,
                                                color: C.text,
                                                whiteSpace: 'nowrap',
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                            }}
                                        >
                                            {t.name}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: C.faint,
                                            }}
                                        >
                                            {t.type}
                                        </div>
                                    </div>
                                    <AIcon
                                        name="chevron-right"
                                        size={15}
                                        color={C.faint}
                                    />
                                </Link>
                            ))}
                        </div>
                    </div>

                    <div style={card}>
                        <div
                            style={{
                                ...cardHeader,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                            }}
                        >
                            <div style={cardTitle}>Invoice Jatuh Tempo</div>
                            <span
                                style={{
                                    background: 'rgba(217,119,6,.1)',
                                    color: C.amber,
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    padding: '3px 9px',
                                    borderRadius: 100,
                                }}
                            >
                                {overdueInvoices.length}
                            </span>
                        </div>
                        <div style={{ padding: '6px 20px 16px' }}>
                            {overdueInvoices.length === 0 && (
                                <div
                                    style={{
                                        padding: '14px 0',
                                        fontSize: 13,
                                        color: C.faint,
                                    }}
                                >
                                    Tidak ada invoice tertunggak.
                                </div>
                            )}
                            {overdueInvoices.map((inv) => (
                                <Link
                                    key={inv.id}
                                    href={`/avana/billing/invoice/${inv.id}/cetak`}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 12,
                                        padding: '11px 0',
                                        borderBottom: `1px solid ${C.line}`,
                                        textDecoration: 'none',
                                    }}
                                >
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div
                                            style={{
                                                fontSize: 13,
                                                fontWeight: 500,
                                                color: C.text,
                                                whiteSpace: 'nowrap',
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                            }}
                                        >
                                            {inv.tenant}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: C.faint,
                                            }}
                                        >
                                            {inv.number} · jatuh tempo {inv.due}
                                        </div>
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 13,
                                            fontWeight: 600,
                                            color: C.amber,
                                            flex: 'none',
                                        }}
                                    >
                                        {inv.amount}
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                </div>

                <div style={card}>
                    <div style={cardHeader}>
                        <div style={cardTitle}>Klien Mendekati Batas Kuota</div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                                marginTop: 2,
                            }}
                        >
                            Karyawan terpakai ≥ 80% dari kuota paket
                        </div>
                    </div>
                    <div style={{ padding: '10px 20px 18px' }}>
                        {quotaAlerts.length === 0 && (
                            <div
                                style={{
                                    padding: '14px 0',
                                    fontSize: 13,
                                    color: C.faint,
                                }}
                            >
                                Semua klien masih dalam batas kuota.
                            </div>
                        )}
                        {quotaAlerts.map((q) => (
                            <div
                                key={q.id}
                                style={{
                                    padding: '11px 0',
                                    borderBottom: `1px solid ${C.line}`,
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        marginBottom: 7,
                                    }}
                                >
                                    <span
                                        style={{
                                            fontSize: 13,
                                            fontWeight: 500,
                                            color: C.text,
                                        }}
                                    >
                                        {q.name}
                                    </span>
                                    <span
                                        style={{
                                            fontSize: 12.5,
                                            fontWeight: 600,
                                            color: q.pct >= 100 ? C.red : C.amber,
                                        }}
                                    >
                                        {q.usage} ({q.pct}%)
                                    </span>
                                </div>
                                <div
                                    style={{
                                        height: 7,
                                        borderRadius: 100,
                                        background: C.line,
                                        overflow: 'hidden',
                                    }}
                                >
                                    <div
                                        style={{
                                            width: `${q.pct}%`,
                                            height: '100%',
                                            borderRadius: 100,
                                            background:
                                                q.pct >= 100 ? C.red : C.amber,
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}
