import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { MonthPicker } from '@/components/avana/date-picker';
import { AIcon, btnOut, C, card } from '@/lib/avana';
import { buildMonthCells, shiftMonth, todayYmd } from '@/lib/month-grid';
import {
    EmptyState,
    formatDate,
    PageHeader,
    PageShell,
    Pill,
} from './components';

interface EventRow {
    id: number;
    title: string;
    type: string | null;
    type_label: string;
    start_date: string | null;
    end_date: string | null;
    all_day: boolean;
    color: string | null;
    description: string | null;
    scope: 'personal' | 'departemen' | 'perusahaan';
}

interface Props {
    month: string;
    upcoming: EventRow[];
    past: EventRow[];
}

/** Colour per audience the event is addressed to. */
const SCOPE_TONE: Record<string, string> = {
    personal: C.primary,
    departemen: C.sky,
    perusahaan: C.green,
};

const SCOPE_LABEL: Record<string, string> = {
    personal: 'Untuk Saya',
    departemen: 'Departemen',
    perusahaan: 'Perusahaan',
};

const WEEKDAYS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

/** "Juli 2026" for a `YYYY-MM` string. */
export default function SayaKalender({ month, upcoming, past }: Props) {
    const today = todayYmd();
    const cells = useMemo(() => buildMonthCells(month), [month]);
    const [selectedDate, setSelectedDate] = useState<string | null>(null);

    const events = useMemo(() => [...upcoming, ...past], [upcoming, past]);

    /** Events covering a day — a multi-day event shows on each of its days. */
    const eventsOn = (date: string): EventRow[] =>
        events.filter((event) => {
            const start = event.start_date;

            if (!start) {
                return false;
            }

            return date >= start && date <= (event.end_date ?? start);
        });

    const navigate = (delta: number) => {
        goToMonth(shiftMonth(month, delta));
    };

    const goToMonth = (next: string) => {
        setSelectedDate(null);
        router.get(
            '/avana/saya/kalender',
            { month: next },
            { preserveScroll: true, preserveState: true },
        );
    };

    const selectedEvents = selectedDate ? eventsOn(selectedDate) : [];

    return (
        <>
            <Head title="Kalender Saya" />
            <PageShell>
                <PageHeader
                    title="Kalender Saya"
                    subtitle="Agenda perusahaan, departemenmu, dan yang ditujukan khusus untukmu."
                />

                {/* Month navigation */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 12,
                        marginBottom: 14,
                    }}
                >
                    <button
                        type="button"
                        onClick={() => navigate(-1)}
                        style={{ ...btnOut, height: 38 }}
                    >
                        <AIcon name="chevron-left" size={16} color={C.text} />
                        Sebelumnya
                    </button>
                    {/*
                     * Jumping straight to a month beats clicking "Berikutnya"
                     * eleven times to reach next December.
                     */}
                    <MonthPicker value={month} onChange={goToMonth} />
                    <button
                        type="button"
                        onClick={() => navigate(1)}
                        style={{ ...btnOut, height: 38 }}
                    >
                        Berikutnya
                        <AIcon name="chevron-right" size={16} color={C.text} />
                    </button>
                </div>

                {/* Month grid */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(7, 1fr)',
                            background: '#FAFBFD',
                            borderBottom: `1px solid ${C.border}`,
                        }}
                    >
                        {WEEKDAYS.map((weekday) => (
                            <div
                                key={weekday}
                                style={{
                                    padding: '11px 12px',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    letterSpacing: '.04em',
                                    textTransform: 'uppercase',
                                    textAlign: 'center',
                                }}
                            >
                                {weekday}
                            </div>
                        ))}
                    </div>

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(7, 1fr)',
                        }}
                    >
                        {cells.map((cell, index) => {
                            const dayEvents = eventsOn(cell.date);
                            const isToday = cell.date === today;
                            const isSelected = cell.date === selectedDate;

                            return (
                                <div
                                    key={cell.date + index}
                                    onClick={() =>
                                        setSelectedDate(
                                            isSelected ? null : cell.date,
                                        )
                                    }
                                    style={{
                                        minHeight: 104,
                                        padding: '8px 8px 10px',
                                        borderRight:
                                            (index + 1) % 7 === 0
                                                ? 'none'
                                                : `1px solid ${C.line}`,
                                        borderBottom: `1px solid ${C.line}`,
                                        background: isSelected
                                            ? `${C.primary}0d`
                                            : cell.inMonth
                                              ? '#fff'
                                              : '#FAFBFD',
                                        cursor: 'pointer',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 5,
                                    }}
                                >
                                    <div
                                        style={{
                                            alignSelf: 'flex-end',
                                            width: 24,
                                            height: 24,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            borderRadius: '50%',
                                            fontSize: 12.5,
                                            fontWeight: isToday ? 700 : 500,
                                            color: isToday
                                                ? '#fff'
                                                : cell.inMonth
                                                  ? C.text
                                                  : C.faint,
                                            background: isToday
                                                ? C.primary
                                                : 'transparent',
                                        }}
                                    >
                                        {cell.day}
                                    </div>

                                    {dayEvents.slice(0, 2).map((event) => (
                                        <div
                                            key={event.id}
                                            title={event.title}
                                            style={{
                                                fontSize: 10.5,
                                                fontWeight: 600,
                                                color:
                                                    event.color ??
                                                    SCOPE_TONE[event.scope],
                                                background: `${event.color ?? SCOPE_TONE[event.scope]}1a`,
                                                borderRadius: 6,
                                                padding: '3px 6px',
                                                whiteSpace: 'nowrap',
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                            }}
                                        >
                                            {event.title}
                                        </div>
                                    ))}

                                    {dayEvents.length > 2 && (
                                        <div
                                            style={{
                                                fontSize: 10.5,
                                                color: C.faint,
                                                paddingLeft: 2,
                                            }}
                                        >
                                            +{dayEvents.length - 2} lainnya
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Detail for the day the reader clicked */}
                <div style={{ marginTop: 16 }}>
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <div
                            style={{
                                padding: '16px 18px',
                                borderBottom: `1px solid ${C.border}`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: 12,
                            }}
                        >
                            <div>
                                <div
                                    style={{
                                        fontSize: 15,
                                        fontWeight: 600,
                                        color: C.navy,
                                    }}
                                >
                                    {selectedDate
                                        ? formatDate(selectedDate)
                                        : 'Agenda Bulan Ini'}
                                </div>
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                        marginTop: 2,
                                    }}
                                >
                                    {selectedDate
                                        ? `${selectedEvents.length.toLocaleString('id-ID')} agenda pada tanggal ini`
                                        : 'Klik tanggal untuk melihat detailnya'}
                                </div>
                            </div>
                            {selectedDate && (
                                <button
                                    type="button"
                                    onClick={() => setSelectedDate(null)}
                                    style={{ ...btnOut, height: 36 }}
                                >
                                    <AIcon name="x" size={15} color={C.text} />
                                    Tampilkan semua
                                </button>
                            )}
                        </div>

                        {(selectedDate ? selectedEvents : events).length ===
                        0 ? (
                            <EmptyState
                                icon="calendar-days"
                                message={
                                    selectedDate
                                        ? 'Tidak ada agenda pada tanggal ini.'
                                        : 'Tidak ada agenda di bulan ini.'
                                }
                            />
                        ) : (
                            (selectedDate ? selectedEvents : events).map(
                                (event, index) => (
                                    <div
                                        key={event.id}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'flex-start',
                                            gap: 12,
                                            padding: '13px 18px',
                                            borderTop:
                                                index === 0
                                                    ? 'none'
                                                    : `1px solid ${C.line}`,
                                        }}
                                    >
                                        <span
                                            style={{
                                                width: 4,
                                                alignSelf: 'stretch',
                                                borderRadius: 999,
                                                background:
                                                    event.color ??
                                                    SCOPE_TONE[event.scope],
                                                flex: 'none',
                                            }}
                                        />
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 8,
                                                    flexWrap: 'wrap',
                                                }}
                                            >
                                                <span
                                                    style={{
                                                        fontSize: 13.5,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {event.title}
                                                </span>
                                                <Pill
                                                    label={
                                                        SCOPE_LABEL[event.scope]
                                                    }
                                                    color={
                                                        SCOPE_TONE[
                                                            event.scope
                                                        ] ?? C.muted
                                                    }
                                                />
                                                {event.type_label && (
                                                    <Pill
                                                        label={event.type_label}
                                                        color={C.faint}
                                                    />
                                                )}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.faint,
                                                    marginTop: 3,
                                                }}
                                            >
                                                {formatDate(event.start_date)}
                                                {event.end_date &&
                                                    event.end_date !==
                                                        event.start_date &&
                                                    ` – ${formatDate(event.end_date)}`}
                                                {event.all_day
                                                    ? ' · sehari penuh'
                                                    : ''}
                                            </div>
                                            {event.description && (
                                                <div
                                                    style={{
                                                        fontSize: 12.5,
                                                        color: C.muted,
                                                        marginTop: 5,
                                                    }}
                                                >
                                                    {event.description}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ),
                            )
                        )}
                    </div>
                </div>
            </PageShell>
        </>
    );
}
