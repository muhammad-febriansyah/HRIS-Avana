import { Head, Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import { EmptyState, formatDate, Panel } from './components';

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

interface PersonalDocument {
    id: number;
    name: string;
    meta: string;
    extension: string | null;
    download_url: string | null;
}

interface NewColleague {
    id: number;
    name: string;
    role: string | null;
    join_date: string | null;
    photo_url: string | null;
}

interface AnnouncementItem {
    id: number;
    title: string;
    excerpt: string;
    category: string | null;
    pinned: boolean;
    published_at: string | null;
}

interface CalendarEventItem {
    id: number;
    title: string;
    type: string;
    type_label: string;
    start_date: string;
    end_date: string;
    all_day: boolean;
    color: string | null;
}

interface Birthday {
    id: number;
    name: string;
    is_self: boolean;
    date: string;
    is_today: boolean;
    photo_url: string | null;
}

interface Props {
    userName: string;
    greeting: string;
    today: string;
    todayIso: string;
    todayAttendance: {
        clock_in: string | null;
        clock_out: string | null;
        status: string | null;
    };
    stats: {
        work_hours_month: number;
        leave_available: number;
        leave_quota: number;
        pending_requests: number;
        week_hours: number;
        week_target: number;
        week_delta: number | null;
        tasks: { done: number; total: number };
        next_leave: { start: string; end: string } | null;
    };
    attendanceWeek: AttendanceDay[];
    awayToday: AwayColleague[];
    documents: PersonalDocument[];
    newColleagues: NewColleague[];
    announcements: AnnouncementItem[];
    calendar: {
        month: string;
        label: string;
        events: CalendarEventItem[];
    };
    birthdays: Birthday[];
}

type FlashProps = { flash?: { success?: string } };

const WEEKDAYS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const sectionLabel: CSSProperties = {
    fontSize: 11.5,
    fontWeight: 600,
    letterSpacing: '.05em',
    textTransform: 'uppercase',
    color: C.faint,
};

/** Initials for an avatar fallback, at most two letters. */
function initials(name: string): string {
    return (
        name
            .split(' ')
            .slice(0, 2)
            .map((part) => part.charAt(0))
            .join('')
            .toUpperCase() || '?'
    );
}

/** Round-trip a `Y-m-d` string into a local date, avoiding UTC parsing. */
function parseIso(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

/** `Y-m-d` for a local date, without the timezone shift `toISOString` adds. */
function toIso(date: Date): string {
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/** Tint used for an event dot, falling back per event type. */
function eventColor(event: CalendarEventItem): string {
    if (event.color) {
        return event.color;
    }

    switch (event.type) {
        case 'holiday':
            return C.red;
        case 'deadline':
            return C.amber;
        case 'training':
            return C.violet;
        case 'birthday':
            return C.green;
        default:
            return C.primary;
    }
}

/** Pick the file icon and tint from a document's extension. */
function documentIcon(document: PersonalDocument): {
    icon: string;
    color: string;
} {
    const extension = (
        document.extension ??
        document.name.split('.').pop() ??
        ''
    ).toLowerCase();

    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
        return { icon: 'image', color: C.primary };
    }

    if (extension === 'pdf') {
        return { icon: 'file-text', color: C.red };
    }

    return { icon: 'file-text', color: C.green };
}

/**
 * Headline figure with a progress bar underneath, the shape the three tiles
 * across the top of the dashboard share.
 */
function ProgressCard({
    label,
    value,
    unit,
    icon,
    tone,
    ratio,
    footnote,
    footnoteColor,
}: {
    label: string;
    value: string;
    unit?: string;
    icon: string;
    tone: string;
    ratio: number | null;
    footnote: string;
    footnoteColor?: string;
}) {
    return (
        <div style={{ ...card, padding: '18px 20px' }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    marginBottom: 12,
                }}
            >
                <div style={sectionLabel}>{label}</div>
                <AIcon name={icon} size={17} color={tone} />
            </div>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 6 }}>
                <div
                    style={{
                        fontSize: 26,
                        fontWeight: 700,
                        color: C.navy,
                        letterSpacing: '-.02em',
                    }}
                >
                    {value}
                </div>
                {unit && (
                    <div style={{ fontSize: 13, color: C.muted }}>{unit}</div>
                )}
            </div>
            {ratio !== null && (
                <div
                    style={{
                        height: 6,
                        borderRadius: 100,
                        background: C.line,
                        marginTop: 12,
                        overflow: 'hidden',
                    }}
                >
                    <div
                        style={{
                            width: `${Math.min(Math.max(ratio, 0), 1) * 100}%`,
                            height: '100%',
                            borderRadius: 100,
                            background: tone,
                        }}
                    />
                </div>
            )}
            <div
                style={{
                    fontSize: 12,
                    color: footnoteColor ?? C.muted,
                    marginTop: 10,
                }}
            >
                {footnote}
            </div>
        </div>
    );
}

/** Circular avatar backed by a photo when there is one, initials otherwise. */
function Avatar({
    name,
    photoUrl,
    size = 34,
}: {
    name: string;
    photoUrl: string | null;
    size?: number;
}) {
    if (photoUrl) {
        return (
            <img
                src={photoUrl}
                alt={name}
                style={{
                    width: size,
                    height: size,
                    borderRadius: '50%',
                    objectFit: 'cover',
                    flexShrink: 0,
                }}
            />
        );
    }

    return (
        <div
            style={{
                width: size,
                height: size,
                borderRadius: '50%',
                background: `${C.primary}1a`,
                color: C.primary,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: size * 0.37,
                fontWeight: 700,
                flexShrink: 0,
            }}
        >
            {initials(name)}
        </div>
    );
}

export default function SayaDashboard({
    userName,
    greeting,
    today,
    todayIso,
    todayAttendance,
    stats,
    attendanceWeek,
    awayToday,
    documents,
    newColleagues,
    announcements,
    calendar,
    birthdays,
}: Props) {
    const { flash } = usePage<FlashProps>().props;
    const [selectedDate, setSelectedDate] = useState<string>(todayIso);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const peakHours = Math.max(...attendanceWeek.map((d) => d.hours), 8);
    const workedDays = attendanceWeek.filter((d) => d.hours > 0);
    const averageHours =
        workedDays.length > 0
            ? workedDays.reduce((sum, d) => sum + d.hours, 0) /
              workedDays.length
            : 0;

    /** The month grid, padded to whole weeks so the columns line up. */
    const monthGrid = useMemo(() => {
        const first = parseIso(`${calendar.month}-01`);
        const start = new Date(first);
        start.setDate(start.getDate() - start.getDay());

        const last = new Date(first.getFullYear(), first.getMonth() + 1, 0);
        const end = new Date(last);
        end.setDate(end.getDate() + (6 - end.getDay()));

        const days: { iso: string; day: number; inMonth: boolean }[] = [];

        for (
            const cursor = new Date(start);
            cursor <= end;
            cursor.setDate(cursor.getDate() + 1)
        ) {
            days.push({
                iso: toIso(cursor),
                day: cursor.getDate(),
                inMonth: cursor.getMonth() === first.getMonth(),
            });
        }

        return days;
    }, [calendar.month]);

    /** Events keyed by every date they span, so a day cell is one lookup. */
    const eventsByDate = useMemo(() => {
        const map = new Map<string, CalendarEventItem[]>();

        for (const event of calendar.events) {
            const cursor = parseIso(event.start_date);
            const end = parseIso(event.end_date);

            while (cursor <= end) {
                const iso = toIso(cursor);
                map.set(iso, [...(map.get(iso) ?? []), event]);
                cursor.setDate(cursor.getDate() + 1);
            }
        }

        return map;
    }, [calendar.events]);

    const selectedEvents = eventsByDate.get(selectedDate) ?? [];
    const todayEvents = eventsByDate.get(todayIso) ?? [];

    /** Load a month into the calendar panel without a full page visit. */
    const loadMonth = (month: string) => {
        if (month === calendar.month) {
            return;
        }

        router.get(
            window.location.pathname,
            { month },
            {
                only: ['calendar'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    /** The month `offset` months away from the one currently shown. */
    const shiftMonth = (offset: number) => {
        const [year, month] = calendar.month.split('-').map(Number);
        const target = new Date(year, month - 1 + offset, 1);

        loadMonth(
            `${target.getFullYear()}-${`${target.getMonth() + 1}`.padStart(2, '0')}`,
        );
    };

    const attendanceDone = todayAttendance.clock_out !== null;
    const weekRatio =
        stats.week_target > 0 ? stats.week_hours / stats.week_target : null;
    const taskRatio =
        stats.tasks.total > 0 ? stats.tasks.done / stats.tasks.total : null;
    const leaveRatio =
        stats.leave_quota > 0
            ? stats.leave_available / stats.leave_quota
            : null;

    return (
        <>
            <Head title="Beranda" />
            <div style={{ padding: '28px 32px' }}>
                {/* Greeting + quick actions */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 20,
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
                            {greeting}, {userName}
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
                            <AIcon name="calendar" size={14} color={C.faint} />
                            {today}
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <Link
                            href="/avana/saya/cuti"
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="palmtree" size={16} color={C.text} />
                            Ajukan Cuti
                        </Link>
                        <Link
                            href="/avana/saya/absensi"
                            style={{ ...btnP, textDecoration: 'none' }}
                        >
                            <AIcon name="clock" size={16} color="#fff" />
                            {attendanceDone
                                ? 'Absensi Saya'
                                : todayAttendance.clock_in
                                  ? 'Absen Pulang'
                                  : 'Absen Masuk'}
                        </Link>
                    </div>
                </div>

                {/* Progress tiles */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(240px, 1fr))',
                        gap: 14,
                        marginBottom: 18,
                    }}
                >
                    <ProgressCard
                        label="Jam Kerja Minggu Ini"
                        value={stats.week_hours.toLocaleString('id-ID')}
                        unit={`/ ${stats.week_target}j`}
                        icon="clock"
                        tone={C.primary}
                        ratio={weekRatio}
                        footnote={
                            stats.week_delta === null
                                ? 'Belum ada data minggu lalu'
                                : `${stats.week_delta > 0 ? '+' : ''}${stats.week_delta}% dari minggu lalu`
                        }
                        footnoteColor={
                            stats.week_delta === null
                                ? undefined
                                : stats.week_delta >= 0
                                  ? C.green
                                  : C.red
                        }
                    />
                    <ProgressCard
                        label="Tugas Selesai"
                        value={`${stats.tasks.done}`}
                        unit={`/ ${stats.tasks.total}`}
                        icon="clipboard-check"
                        tone={C.green}
                        ratio={taskRatio}
                        footnote={
                            stats.tasks.total === 0
                                ? 'Belum ada tugas bulan ini'
                                : stats.tasks.done === stats.tasks.total
                                  ? 'Semua tugas selesai'
                                  : `${stats.tasks.total - stats.tasks.done} tugas tersisa`
                        }
                    />
                    <ProgressCard
                        label="Sisa Cuti"
                        value={stats.leave_available.toLocaleString('id-ID')}
                        unit={
                            stats.leave_quota > 0
                                ? `/ ${stats.leave_quota.toLocaleString('id-ID')} hari`
                                : 'hari'
                        }
                        icon="palmtree"
                        tone={C.amber}
                        ratio={leaveRatio}
                        footnote={
                            stats.next_leave
                                ? `Berikutnya: ${stats.next_leave.start} – ${stats.next_leave.end}`
                                : 'Belum ada cuti terjadwal'
                        }
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
                    {/* Main column */}
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 16,
                        }}
                    >
                        <Panel
                            title="Sedang Cuti Hari Ini"
                            subtitle="Rekan satu departemen"
                            padded={false}
                            action={
                                <span
                                    style={{
                                        padding: '3px 10px',
                                        borderRadius: 100,
                                        fontSize: 11.5,
                                        fontWeight: 600,
                                        color: C.primary,
                                        background: `${C.primary}14`,
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    {awayToday.length} orang
                                </span>
                            }
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
                                            <Avatar
                                                name={person.name ?? '?'}
                                                photoUrl={null}
                                            />
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
                                                    {person.leave_type ??
                                                        'Cuti'}{' '}
                                                    · s/d{' '}
                                                    {formatDate(
                                                        person.end_date,
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Panel>

                        <Panel
                            title="Absensi Mingguan"
                            subtitle={
                                averageHours > 0
                                    ? `Rata-rata ${averageHours.toLocaleString('id-ID', { maximumFractionDigits: 1 })} jam/hari`
                                    : 'Belum ada jam kerja tercatat'
                            }
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

                        <Panel
                            title="Dokumen Pribadi"
                            action={
                                <Link
                                    href="/avana/saya/dokumen"
                                    style={{
                                        fontSize: 12.5,
                                        fontWeight: 600,
                                        color: C.primary,
                                        textDecoration: 'none',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    Lihat semua
                                </Link>
                            }
                        >
                            {documents.length === 0 ? (
                                <EmptyState
                                    icon="folder"
                                    message="Belum ada dokumen tersimpan."
                                />
                            ) : (
                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns:
                                            'repeat(auto-fit, minmax(240px, 1fr))',
                                        gap: 12,
                                    }}
                                >
                                    {documents.map((document) => {
                                        const { icon, color } =
                                            documentIcon(document);

                                        return (
                                            <div
                                                key={document.id}
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 11,
                                                    padding: '12px 14px',
                                                    border: `1px solid ${C.border}`,
                                                    borderRadius: 10,
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        width: 34,
                                                        height: 34,
                                                        borderRadius: 8,
                                                        background: `${color}1a`,
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent:
                                                            'center',
                                                        flexShrink: 0,
                                                    }}
                                                >
                                                    <AIcon
                                                        name={icon}
                                                        size={16}
                                                        color={color}
                                                    />
                                                </div>
                                                <div
                                                    style={{
                                                        minWidth: 0,
                                                        flex: 1,
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            fontSize: 13,
                                                            fontWeight: 600,
                                                            color: C.text,
                                                            overflow: 'hidden',
                                                            textOverflow:
                                                                'ellipsis',
                                                            whiteSpace:
                                                                'nowrap',
                                                        }}
                                                    >
                                                        {document.name}
                                                    </div>
                                                    <div
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        {document.meta}
                                                    </div>
                                                </div>
                                                {document.download_url && (
                                                    <a
                                                        href={
                                                            document.download_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        title="Unduh"
                                                        style={{
                                                            color: C.muted,
                                                            display: 'flex',
                                                        }}
                                                    >
                                                        <AIcon
                                                            name="download"
                                                            size={16}
                                                            color={C.muted}
                                                        />
                                                    </a>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </Panel>

                        <Panel
                            title="Rekan Baru"
                            subtitle="Bergabung 30 hari terakhir"
                            padded={false}
                        >
                            {newColleagues.length === 0 ? (
                                <EmptyState
                                    icon="user-plus"
                                    message="Belum ada rekan baru."
                                />
                            ) : (
                                <div>
                                    {newColleagues.map((colleague) => (
                                        <div
                                            key={colleague.id}
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 11,
                                                padding: '13px 18px',
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <Avatar
                                                name={colleague.name}
                                                photoUrl={colleague.photo_url}
                                            />
                                            <div
                                                style={{
                                                    minWidth: 0,
                                                    flex: 1,
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        fontSize: 13,
                                                        fontWeight: 600,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {colleague.name}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {colleague.role ?? '—'}
                                                </div>
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.faint,
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {colleague.join_date}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Panel>

                        <Panel title="Pengumuman" padded={false}>
                            {announcements.length === 0 ? (
                                <EmptyState
                                    icon="megaphone"
                                    message="Belum ada pengumuman."
                                />
                            ) : (
                                <div style={{ padding: '14px 18px' }}>
                                    {announcements.map((announcement) => (
                                        <div
                                            key={announcement.id}
                                            style={{
                                                background: C.surface,
                                                borderRadius: 10,
                                                padding: '13px 15px',
                                                marginBottom: 10,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent:
                                                        'space-between',
                                                    gap: 10,
                                                }}
                                            >
                                                <span
                                                    style={{
                                                        ...sectionLabel,
                                                        color: C.primary,
                                                    }}
                                                >
                                                    {announcement.category ??
                                                        'Umum'}
                                                </span>
                                                <span
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                        whiteSpace: 'nowrap',
                                                    }}
                                                >
                                                    {announcement.published_at}
                                                </span>
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                    marginTop: 5,
                                                }}
                                            >
                                                {announcement.title}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 12.5,
                                                    color: C.muted,
                                                    marginTop: 3,
                                                }}
                                            >
                                                {announcement.excerpt}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Panel>
                    </div>

                    {/* Side column */}
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 16,
                        }}
                    >
                        <div style={{ ...card, padding: '16px 18px' }}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    gap: 10,
                                    marginBottom: 14,
                                }}
                            >
                                <button
                                    onClick={() => {
                                        setSelectedDate(todayIso);
                                        loadMonth(todayIso.slice(0, 7));
                                    }}
                                    style={{
                                        padding: '5px 11px',
                                        borderRadius: 7,
                                        border: `1px solid ${C.border}`,
                                        background: '#fff',
                                        fontSize: 12,
                                        fontWeight: 600,
                                        color: C.text,
                                        cursor: 'pointer',
                                    }}
                                >
                                    Hari Ini
                                </button>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 4,
                                    }}
                                >
                                    <button
                                        onClick={() => shiftMonth(-1)}
                                        aria-label="Bulan sebelumnya"
                                        style={{
                                            width: 26,
                                            height: 26,
                                            borderRadius: 7,
                                            border: `1px solid ${C.border}`,
                                            background: '#fff',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <AIcon
                                            name="chevron-left"
                                            size={14}
                                            color={C.muted}
                                        />
                                    </button>
                                    <button
                                        onClick={() => shiftMonth(1)}
                                        aria-label="Bulan berikutnya"
                                        style={{
                                            width: 26,
                                            height: 26,
                                            borderRadius: 7,
                                            border: `1px solid ${C.border}`,
                                            background: '#fff',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <AIcon
                                            name="chevron-right"
                                            size={14}
                                            color={C.muted}
                                        />
                                    </button>
                                </div>
                                <div
                                    style={{
                                        fontSize: 14,
                                        fontWeight: 600,
                                        color: C.navy,
                                        marginLeft: 'auto',
                                    }}
                                >
                                    {calendar.label}
                                </div>
                            </div>

                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: 'repeat(7, 1fr)',
                                    gap: 2,
                                }}
                            >
                                {WEEKDAYS.map((day) => (
                                    <div
                                        key={day}
                                        style={{
                                            ...sectionLabel,
                                            textAlign: 'center',
                                            padding: '4px 0 8px',
                                        }}
                                    >
                                        {day}
                                    </div>
                                ))}
                                {monthGrid.map((cell) => {
                                    const dayEvents =
                                        eventsByDate.get(cell.iso) ?? [];
                                    const isToday = cell.iso === todayIso;
                                    const isSelected =
                                        cell.iso === selectedDate;

                                    return (
                                        <button
                                            key={cell.iso}
                                            onClick={() =>
                                                setSelectedDate(cell.iso)
                                            }
                                            style={{
                                                aspectRatio: '1',
                                                border: 'none',
                                                borderRadius: 8,
                                                background: isSelected
                                                    ? C.primary
                                                    : isToday
                                                      ? `${C.primary}14`
                                                      : 'transparent',
                                                color: isSelected
                                                    ? '#fff'
                                                    : cell.inMonth
                                                      ? C.text
                                                      : C.faint,
                                                fontSize: 12.5,
                                                fontWeight:
                                                    isToday || isSelected
                                                        ? 700
                                                        : 500,
                                                cursor: 'pointer',
                                                display: 'flex',
                                                flexDirection: 'column',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                gap: 3,
                                            }}
                                        >
                                            {cell.day}
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    gap: 2,
                                                    height: 4,
                                                }}
                                            >
                                                {dayEvents
                                                    .slice(0, 3)
                                                    .map((event) => (
                                                        <span
                                                            key={event.id}
                                                            style={{
                                                                width: 4,
                                                                height: 4,
                                                                borderRadius:
                                                                    '50%',
                                                                background:
                                                                    isSelected
                                                                        ? '#fff'
                                                                        : eventColor(
                                                                              event,
                                                                          ),
                                                            }}
                                                        />
                                                    ))}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <Panel
                            title="Acara"
                            subtitle={formatDate(selectedDate)}
                            padded={false}
                        >
                            {selectedEvents.length === 0 ? (
                                <EmptyState
                                    icon="calendar"
                                    message="Tidak ada acara pada tanggal ini."
                                />
                            ) : (
                                <div>
                                    {selectedEvents.map((event) => (
                                        <div
                                            key={event.id}
                                            style={{
                                                display: 'flex',
                                                gap: 10,
                                                padding: '13px 18px',
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <span
                                                style={{
                                                    width: 3,
                                                    borderRadius: 100,
                                                    background:
                                                        eventColor(event),
                                                    flexShrink: 0,
                                                }}
                                            />
                                            <div style={{ minWidth: 0 }}>
                                                <div
                                                    style={{
                                                        fontSize: 13,
                                                        fontWeight: 600,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {event.title}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {event.type_label}
                                                    {event.all_day
                                                        ? ' · Sepanjang hari'
                                                        : ''}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Panel>

                        <Panel title="Agenda Hari Ini" padded={false}>
                            {todayEvents.length === 0 ? (
                                <EmptyState
                                    icon="calendar-clock"
                                    message="Tidak ada agenda hari ini."
                                />
                            ) : (
                                <div>
                                    {todayEvents.map((event) => (
                                        <div
                                            key={event.id}
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 10,
                                                padding: '13px 18px',
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <span
                                                style={{
                                                    width: 8,
                                                    height: 8,
                                                    borderRadius: '50%',
                                                    background:
                                                        eventColor(event),
                                                    flexShrink: 0,
                                                }}
                                            />
                                            <div
                                                style={{
                                                    fontSize: 13,
                                                    color: C.text,
                                                }}
                                            >
                                                {event.title}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Panel>

                        <Panel
                            title="Ulang Tahun"
                            subtitle="30 hari ke depan"
                            padded={false}
                        >
                            {birthdays.length === 0 ? (
                                <EmptyState
                                    icon="cake"
                                    message="Tidak ada ulang tahun terdekat."
                                />
                            ) : (
                                <div>
                                    {birthdays.map((person) => (
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
                                            <Avatar
                                                name={person.name}
                                                photoUrl={person.photo_url}
                                            />
                                            <div
                                                style={{
                                                    minWidth: 0,
                                                    flex: 1,
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        fontSize: 13,
                                                        fontWeight: 600,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {person.is_self
                                                        ? `${person.name} (Anda)`
                                                        : person.name}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: person.is_today
                                                            ? C.green
                                                            : C.faint,
                                                        fontWeight:
                                                            person.is_today
                                                                ? 600
                                                                : 400,
                                                    }}
                                                >
                                                    {person.is_today
                                                        ? 'Hari ini'
                                                        : person.date}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Panel>
                    </div>
                </div>
            </div>
        </>
    );
}
