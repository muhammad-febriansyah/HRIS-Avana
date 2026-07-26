import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import { EmptyState, formatDate, Panel, StatCard } from './components';

interface AttendanceDay {
    date: string;
    label: string;
    hours: number;
    status: string | null;
}

interface AwayColleague {
    id: number;
    name: string | null;
    leave_type: string | null;
    end_date: string | null;
}

interface Props {
    userName: string;
    today: string;
    todayAttendance: {
        clock_in: string | null;
        clock_out: string | null;
        status: string | null;
    };
    stats: {
        work_hours_month: number;
        leave_available: number;
        pending_requests: number;
    };
    attendanceWeek: AttendanceDay[];
    awayToday: AwayColleague[];
}

type FlashProps = { flash?: { success?: string } };

export default function SayaDashboard({
    userName,
    today,
    todayAttendance,
    stats,
    attendanceWeek,
    awayToday,
}: Props) {
    const { flash } = usePage<FlashProps>().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const peakHours = Math.max(...attendanceWeek.map((d) => d.hours), 8);

    return (
        <>
            <Head title="Beranda" />
            <div style={{ padding: '28px 32px' }}>
                {/* Greeting + quick actions */}
                <div style={{ ...card, padding: '24px 26px', marginBottom: 18 }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'flex-start',
                            justifyContent: 'space-between',
                            flexWrap: 'wrap',
                            gap: 16,
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
                                Halo, {userName} 👋
                            </h1>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    fontSize: 13.5,
                                    color: C.muted,
                                    marginTop: 6,
                                }}
                            >
                                <AIcon
                                    name="calendar"
                                    size={14}
                                    color={C.faint}
                                />
                                {today}
                            </div>
                        </div>
                        <div style={{ display: 'flex', gap: 10 }}>
                            <Link
                                href="/avana/saya/izin"
                                style={{
                                    ...btnOut,
                                    textDecoration: 'none',
                                }}
                            >
                                <AIcon
                                    name="file-clock"
                                    size={16}
                                    color={C.text}
                                />
                                Ajukan Izin
                            </Link>
                            <Link
                                href="/avana/saya/cuti"
                                style={{ ...btnP, textDecoration: 'none' }}
                            >
                                <AIcon name="palmtree" size={16} color="#fff" />
                                Ajukan Cuti
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Stat tiles */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(220px, 1fr))',
                        gap: 14,
                        marginBottom: 18,
                    }}
                >
                    <StatCard
                        label="Absen Hari Ini"
                        value={todayAttendance.clock_in ?? '—'}
                        unit={
                            todayAttendance.clock_out
                                ? `s/d ${todayAttendance.clock_out}`
                                : todayAttendance.clock_in
                                  ? 'belum pulang'
                                  : 'belum absen'
                        }
                        icon="fingerprint"
                        tone={C.primary}
                    />
                    <StatCard
                        label="Jam Kerja Bulan Ini"
                        value={stats.work_hours_month.toLocaleString('id-ID')}
                        unit="jam"
                        icon="clock"
                        tone={C.sky}
                    />
                    <StatCard
                        label="Sisa Cuti"
                        value={stats.leave_available.toLocaleString('id-ID')}
                        unit="hari"
                        icon="palmtree"
                        tone={C.green}
                    />
                    <StatCard
                        label="Menunggu Persetujuan"
                        value={stats.pending_requests}
                        unit="pengajuan"
                        icon="hourglass"
                        tone={C.amber}
                    />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 2fr) minmax(0, 1fr)',
                        gap: 16,
                        alignItems: 'start',
                    }}
                >
                    {/* Weekly attendance bars */}
                    <Panel
                        title="Absensi 7 Hari Terakhir"
                        subtitle="Jam kerja tercatat per hari"
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'flex-end',
                                gap: 10,
                                height: 190,
                            }}
                        >
                            {attendanceWeek.map((day) => (
                                <div
                                    key={day.date}
                                    style={{
                                        flex: 1,
                                        display: 'flex',
                                        flexDirection: 'column',
                                        alignItems: 'center',
                                        gap: 8,
                                        height: '100%',
                                        justifyContent: 'flex-end',
                                    }}
                                >
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            fontWeight: 600,
                                            color:
                                                day.hours > 0
                                                    ? C.navy
                                                    : C.faint,
                                        }}
                                    >
                                        {day.hours > 0
                                            ? `${day.hours.toLocaleString('id-ID')}j`
                                            : '—'}
                                    </div>
                                    <div
                                        style={{
                                            width: '100%',
                                            maxWidth: 44,
                                            height: `${Math.max((day.hours / peakHours) * 130, 4)}px`,
                                            borderRadius: '7px 7px 3px 3px',
                                            background:
                                                day.status === 'late'
                                                    ? C.amber
                                                    : day.hours > 0
                                                      ? C.primary
                                                      : C.border,
                                        }}
                                    />
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: C.muted,
                                        }}
                                    >
                                        {day.label}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Panel>

                    {/* Who is out today */}
                    <Panel
                        title="Sedang Cuti Hari Ini"
                        subtitle="Rekan satu departemen"
                        padded={false}
                    >
                        {awayToday.length === 0 ? (
                            <EmptyState
                                icon="users"
                                message="Tidak ada yang cuti hari ini."
                            />
                        ) : (
                            <div>
                                {awayToday.map((person) => (
                                    <div
                                        key={person.id}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 11,
                                            padding: '13px 18px',
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <div
                                            style={{
                                                width: 34,
                                                height: 34,
                                                borderRadius: '50%',
                                                background: `${C.primary}1a`,
                                                color: C.primary,
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                fontSize: 12.5,
                                                fontWeight: 700,
                                                flexShrink: 0,
                                            }}
                                        >
                                            {(person.name ?? '?')
                                                .split(' ')
                                                .slice(0, 2)
                                                .map((part) => part.charAt(0))
                                                .join('')
                                                .toUpperCase()}
                                        </div>
                                        <div style={{ minWidth: 0 }}>
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
                                                    fontSize: 11.5,
                                                    color: C.faint,
                                                }}
                                            >
                                                {person.leave_type ?? 'Cuti'} ·
                                                s/d {formatDate(person.end_date)}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Panel>
                </div>
            </div>
        </>
    );
}
