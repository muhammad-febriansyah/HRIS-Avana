import { Head, router } from '@inertiajs/react';
import { AIcon, btnOut, C, card } from '@/lib/avana';
import {
    EmptyState,
    formatDate,
    PageHeader,
    PageShell,
    Panel,
    Pill,
} from './components';

interface ScheduleDay {
    date: string;
    day_label: string;
    day_short: string;
    is_today: boolean;
    is_scheduled: boolean;
    is_off: boolean;
    shift_name: string | null;
    start: string | null;
    end: string | null;
}

interface AwayColleague {
    id: number;
    name: string | null;
    leave_type: string | null;
    start_date: string | null;
    end_date: string | null;
}

interface Props {
    days: ScheduleDay[];
    weekStart: string;
    weekEnd: string;
    awayThisWeek: AwayColleague[];
}

export default function SayaJadwal({
    days,
    weekStart,
    weekEnd,
    awayThisWeek,
}: Props) {
    const shiftWeek = (offset: number) => {
        const target = new Date(`${weekStart}T00:00:00`);
        target.setDate(target.getDate() + offset * 7);

        router.get(
            '/avana/saya/jadwal',
            { start: target.toISOString().slice(0, 10) },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Jadwal Saya" />
            <PageShell>
                <PageHeader
                    title="Jadwal Saya"
                    subtitle={`${formatDate(weekStart)} – ${formatDate(weekEnd)}`}
                    action={
                        <div style={{ display: 'flex', gap: 8 }}>
                            <button
                                type="button"
                                onClick={() => shiftWeek(-1)}
                                style={{ ...btnOut, height: 40 }}
                            >
                                <AIcon
                                    name="chevron-left"
                                    size={16}
                                    color={C.text}
                                />
                                Minggu Lalu
                            </button>
                            <button
                                type="button"
                                onClick={() => shiftWeek(1)}
                                style={{ ...btnOut, height: 40 }}
                            >
                                Minggu Depan
                                <AIcon
                                    name="chevron-right"
                                    size={16}
                                    color={C.text}
                                />
                            </button>
                        </div>
                    }
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(7, minmax(0, 1fr))',
                        gap: 10,
                        marginBottom: 18,
                    }}
                >
                    {days.map((day) => (
                        <div
                            key={day.date}
                            style={{
                                ...card,
                                padding: '14px 12px',
                                textAlign: 'center',
                                border: day.is_today
                                    ? `1px solid ${C.primary}`
                                    : `1px solid ${C.border}`,
                                background: day.is_today
                                    ? `${C.primary}0a`
                                    : '#fff',
                            }}
                        >
                            <div
                                style={{
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: day.is_today ? C.primary : C.faint,
                                    textTransform: 'uppercase',
                                    letterSpacing: '.04em',
                                }}
                            >
                                {day.day_short}
                            </div>
                            <div
                                style={{
                                    fontSize: 20,
                                    fontWeight: 700,
                                    color: C.navy,
                                    margin: '6px 0 10px',
                                }}
                            >
                                {Number(day.date.slice(8, 10))}
                            </div>

                            {!day.is_scheduled && (
                                <Pill label="Belum diatur" color={C.faint} />
                            )}
                            {day.is_scheduled && day.is_off && (
                                <Pill label="Libur" color={C.muted} />
                            )}
                            {day.is_scheduled && !day.is_off && (
                                <>
                                    <div
                                        style={{
                                            fontSize: 12,
                                            fontWeight: 600,
                                            color: C.text,
                                        }}
                                    >
                                        {day.shift_name ?? 'Shift'}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: C.muted,
                                            marginTop: 3,
                                        }}
                                    >
                                        {day.start ?? '—'}–{day.end ?? '—'}
                                    </div>
                                </>
                            )}
                        </div>
                    ))}
                </div>

                <Panel
                    title="Cuti Rekan Minggu Ini"
                    subtitle="Departemen yang sama denganmu"
                    padded={false}
                >
                    {awayThisWeek.length === 0 ? (
                        <EmptyState
                            icon="users"
                            message="Tidak ada rekan yang cuti minggu ini."
                        />
                    ) : (
                        <div>
                            {awayThisWeek.map((person) => (
                                <div
                                    key={person.id}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        gap: 12,
                                        padding: '13px 18px',
                                        borderTop: `1px solid ${C.line}`,
                                    }}
                                >
                                    <div
                                        style={{
                                            fontSize: 13,
                                            fontWeight: 600,
                                            color: C.text,
                                        }}
                                    >
                                        {person.name ?? '—'}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12,
                                            color: C.muted,
                                        }}
                                    >
                                        {person.leave_type ?? 'Cuti'} ·{' '}
                                        {formatDate(person.start_date)} –{' '}
                                        {formatDate(person.end_date)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Panel>
            </PageShell>
        </>
    );
}
