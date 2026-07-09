import { Head, Link } from '@inertiajs/react';
import { AIcon, btnP, C } from '@/lib/avana';
import { Empty, Kpi, KpiRow, RecruitmentHeader, Section } from './shell';

interface FunnelRow {
    label: string;
    value: number;
}

/** Ordinal blue ramp (dataviz steps 250→650) — funnel stages darken downward. */
const FUNNEL_RAMP = ['#86b6ef', '#5598e7', '#2a78d6', '#1c5cab', '#104281'];

interface TaskRow {
    id: number;
    title: string;
    subtitle: string;
    at: string | null;
}

interface DashboardProps {
    kpis: {
        incoming: number;
        interviews_today: number;
        active_offers: number;
        time_to_hire: number;
    };
    funnel: FunnelRow[];
    tasks: TaskRow[];
}

export default function RecruitmentDashboard({
    kpis,
    funnel,
    tasks,
}: DashboardProps) {
    const maxFunnel = Math.max(...funnel.map((f) => f.value), 1);

    return (
        <>
            <Head title="Recruitment Dashboard" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="Dashboard"
                    subtitle="Pantau performa rekrutmen & status lowongan secara real-time."
                    action={
                        <Link href="/avana/rekrutmen/create" style={btnP}>
                            <AIcon name="plus" size={16} color="#fff" />
                            Buat Lowongan
                        </Link>
                    }
                />

                <KpiRow>
                    <Kpi
                        label="Pelamar Masuk"
                        value={kpis.incoming}
                        hint="Periode ini"
                        icon="users"
                        color={C.primary}
                    />
                    <Kpi
                        label="Wawancara Hari Ini"
                        value={kpis.interviews_today}
                        hint="Terjadwal"
                        icon="calendar-days"
                        color={C.amber}
                    />
                    <Kpi
                        label="Penawaran Aktif"
                        value={kpis.active_offers}
                        hint="Menunggu"
                        icon="file-check"
                        color={C.green}
                    />
                    <Kpi
                        label="Time-to-hire"
                        value={`${kpis.time_to_hire} hari`}
                        hint="Rata-rata"
                        icon="clock"
                        color="#7C3AED"
                    />
                </KpiRow>

                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1.5fr 1fr',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    <Section
                        title="Funnel Rekrutmen"
                        icon="filter"
                        action={
                            <Link
                                href="/avana/rekrutmen/pipeline"
                                style={{
                                    fontSize: 13,
                                    fontWeight: 600,
                                    color: C.primary,
                                    textDecoration: 'none',
                                }}
                            >
                                Lihat Detail
                            </Link>
                        }
                    >
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 16,
                            }}
                        >
                            {funnel.map((row, i) => (
                                <div
                                    key={row.label}
                                    title={`${row.label}: ${row.value}`}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                            fontSize: 13,
                                            marginBottom: 6,
                                        }}
                                    >
                                        <span style={{ color: C.text }}>
                                            {row.label}
                                        </span>
                                        <span
                                            style={{
                                                fontWeight: 700,
                                                color: C.navy,
                                                fontVariantNumeric:
                                                    'tabular-nums',
                                            }}
                                        >
                                            {row.value}
                                        </span>
                                    </div>
                                    <div
                                        style={{
                                            height: 12,
                                            borderRadius: 6,
                                            background: C.line,
                                            overflow: 'hidden',
                                        }}
                                    >
                                        <div
                                            style={{
                                                width: `${Math.round((row.value / maxFunnel) * 100)}%`,
                                                minWidth: row.value > 0 ? 6 : 0,
                                                height: '100%',
                                                borderRadius: 6,
                                                background:
                                                    FUNNEL_RAMP[i] ??
                                                    FUNNEL_RAMP[
                                                        FUNNEL_RAMP.length - 1
                                                    ],
                                                transition: 'width .3s',
                                            }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Section>

                    <Section title="Tugas Hari Ini" icon="check-check">
                        {tasks.length === 0 ? (
                            <Empty
                                icon="calendar-check"
                                title="Tidak ada tugas"
                                hint="Wawancara terjadwal akan muncul di sini."
                            />
                        ) : (
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 12,
                                }}
                            >
                                {tasks.map((task) => (
                                    <div
                                        key={task.id}
                                        style={{
                                            display: 'flex',
                                            gap: 11,
                                            alignItems: 'flex-start',
                                        }}
                                    >
                                        <div
                                            style={{
                                                width: 34,
                                                height: 34,
                                                borderRadius: 9,
                                                flex: 'none',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                background: C.primary + '14',
                                            }}
                                        >
                                            <AIcon
                                                name="calendar"
                                                size={16}
                                                color={C.primary}
                                            />
                                        </div>
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 600,
                                                    color: C.text,
                                                }}
                                            >
                                                {task.title}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 12,
                                                    color: C.faint,
                                                }}
                                            >
                                                {task.subtitle}
                                                {task.at
                                                    ? ` · ${task.at.slice(0, 16).replace('T', ' ')}`
                                                    : ''}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Section>
                </div>
            </div>
        </>
    );
}
