import { Head } from '@inertiajs/react';
import { AIcon, C, card } from '@/lib/avana';
import {
    EmptyState,
    formatDate,
    PageHeader,
    PageShell,
    Panel,
    Pill,
    StatCard,
} from './components';

interface Task {
    id: number;
    title: string;
    is_done: boolean;
    done_at: string | null;
}

interface Visit {
    id: number;
    visit_date: string | null;
    location: string | null;
    client_name: string | null;
    purpose: string | null;
    status: string;
    status_label: string;
    total: number;
    done: number;
    percent: number;
    tasks: Task[];
}

interface Props {
    visits: Visit[];
    summary: { visits: number; tasks: number; done: number; open: number };
}

/** Colour per visit status. */
const STATUS_TONE: Record<string, string> = {
    planned: C.faint,
    scheduled: C.primary,
    ongoing: C.amber,
    in_progress: C.amber,
    completed: C.green,
    done: C.green,
    cancelled: C.muted,
};

export default function SayaTugas({ visits, summary }: Props) {
    return (
        <>
            <Head title="Tugas Saya" />
            <PageShell>
                <PageHeader
                    title="Tugas Saya"
                    subtitle="Checklist tugas dari kunjungan lapanganmu. Centang dan unggah foto lewat aplikasi mobile."
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(210px, 1fr))',
                        gap: 14,
                        marginBottom: 18,
                    }}
                >
                    <StatCard
                        label="Kunjungan"
                        value={summary.visits}
                        unit="agenda"
                        icon="map-pinned"
                        tone={C.primary}
                    />
                    <StatCard
                        label="Tugas Selesai"
                        value={summary.done}
                        unit={`dari ${summary.tasks}`}
                        icon="circle-check"
                        tone={C.green}
                    />
                    <StatCard
                        label="Belum Selesai"
                        value={summary.open}
                        unit="tugas"
                        icon="hourglass"
                        tone={C.amber}
                    />
                </div>

                {visits.length === 0 ? (
                    <Panel padded={false}>
                        <EmptyState
                            icon="clipboard-list"
                            message="Belum ada kunjungan lapangan atas namamu."
                        />
                    </Panel>
                ) : (
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        {visits.map((visit) => (
                            <div
                                key={visit.id}
                                style={{ ...card, padding: '18px 20px' }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        justifyContent: 'space-between',
                                        flexWrap: 'wrap',
                                        gap: 12,
                                    }}
                                >
                                    <div style={{ minWidth: 0 }}>
                                        <div
                                            style={{
                                                fontSize: 15,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {visit.client_name ??
                                                visit.location ??
                                                'Kunjungan'}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12.5,
                                                color: C.muted,
                                                marginTop: 3,
                                            }}
                                        >
                                            {formatDate(visit.visit_date)}
                                            {visit.location &&
                                                ` · ${visit.location}`}
                                        </div>
                                        {visit.purpose && (
                                            <div
                                                style={{
                                                    fontSize: 12,
                                                    color: C.faint,
                                                    marginTop: 3,
                                                }}
                                            >
                                                {visit.purpose}
                                            </div>
                                        )}
                                    </div>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 10,
                                        }}
                                    >
                                        <Pill
                                            label={visit.status_label}
                                            color={
                                                STATUS_TONE[visit.status] ??
                                                C.muted
                                            }
                                        />
                                        <span
                                            style={{
                                                fontSize: 12.5,
                                                fontWeight: 600,
                                                color: C.muted,
                                            }}
                                        >
                                            {visit.done}/{visit.total}
                                        </span>
                                    </div>
                                </div>

                                {visit.total > 0 && (
                                    <div
                                        style={{
                                            height: 7,
                                            borderRadius: 999,
                                            background: C.line,
                                            overflow: 'hidden',
                                            margin: '14px 0 4px',
                                        }}
                                    >
                                        <div
                                            style={{
                                                width: `${visit.percent}%`,
                                                height: '100%',
                                                background:
                                                    visit.percent === 100
                                                        ? C.green
                                                        : C.primary,
                                            }}
                                        />
                                    </div>
                                )}

                                {visit.tasks.length === 0 ? (
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.faint,
                                            marginTop: 12,
                                        }}
                                    >
                                        Belum ada tugas pada kunjungan ini.
                                    </div>
                                ) : (
                                    <div
                                        style={{
                                            marginTop: 12,
                                            display: 'flex',
                                            flexDirection: 'column',
                                            gap: 8,
                                        }}
                                    >
                                        {visit.tasks.map((task) => (
                                            <div
                                                key={task.id}
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 9,
                                                    fontSize: 13,
                                                }}
                                            >
                                                <AIcon
                                                    name={
                                                        task.is_done
                                                            ? 'circle-check'
                                                            : 'circle'
                                                    }
                                                    size={16}
                                                    color={
                                                        task.is_done
                                                            ? C.green
                                                            : C.faint
                                                    }
                                                />
                                                <span
                                                    style={{
                                                        color: task.is_done
                                                            ? C.faint
                                                            : C.text,
                                                        textDecoration:
                                                            task.is_done
                                                                ? 'line-through'
                                                                : 'none',
                                                    }}
                                                >
                                                    {task.title}
                                                </span>
                                                {task.done_at && (
                                                    <span
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        ·{' '}
                                                        {formatDate(
                                                            task.done_at,
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </PageShell>
        </>
    );
}
