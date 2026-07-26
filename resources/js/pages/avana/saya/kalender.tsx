import { Head, router } from '@inertiajs/react';
import { C, card } from '@/lib/avana';
import {
    EmptyState,
    formatDate,
    inputStyle,
    PageHeader,
    PageShell,
    Panel,
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

export default function SayaKalender({ month, upcoming, past }: Props) {
    const changeMonth = (value: string) =>
        router.get(
            '/avana/saya/kalender',
            { month: value },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <>
            <Head title="Kalender Saya" />
            <PageShell>
                <PageHeader
                    title="Kalender Saya"
                    subtitle="Agenda perusahaan, departemenmu, dan yang ditujukan khusus untukmu."
                    action={
                        <input
                            type="month"
                            value={month}
                            onChange={(event) =>
                                changeMonth(event.target.value)
                            }
                            style={{ ...inputStyle, width: 190 }}
                        />
                    }
                />

                <Panel
                    title="Akan Datang"
                    subtitle={`${upcoming.length.toLocaleString('id-ID')} agenda`}
                    padded={false}
                >
                    {upcoming.length === 0 ? (
                        <EmptyState
                            icon="calendar-days"
                            message="Tidak ada agenda yang akan datang di bulan ini."
                        />
                    ) : (
                        upcoming.map((event, index) => (
                            <EventRowView
                                key={event.id}
                                event={event}
                                isFirst={index === 0}
                            />
                        ))
                    )}
                </Panel>

                {past.length > 0 && (
                    <div style={{ marginTop: 16 }}>
                        <Panel
                            title="Sudah Lewat"
                            subtitle={`${past.length.toLocaleString('id-ID')} agenda`}
                            padded={false}
                        >
                            {past.map((event, index) => (
                                <EventRowView
                                    key={event.id}
                                    event={event}
                                    isFirst={index === 0}
                                    dimmed
                                />
                            ))}
                        </Panel>
                    </div>
                )}
            </PageShell>
        </>
    );
}

function EventRowView({
    event,
    isFirst,
    dimmed = false,
}: {
    event: EventRow;
    isFirst: boolean;
    dimmed?: boolean;
}) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'flex-start',
                gap: 14,
                padding: '14px 18px',
                borderTop: isFirst ? 'none' : `1px solid ${C.line}`,
                opacity: dimmed ? 0.65 : 1,
            }}
        >
            <div
                style={{
                    ...card,
                    boxShadow: 'none',
                    width: 52,
                    flex: 'none',
                    padding: '8px 0',
                    textAlign: 'center',
                    borderLeft: `3px solid ${event.color ?? C.primary}`,
                }}
            >
                <div
                    style={{
                        fontSize: 17,
                        fontWeight: 700,
                        color: C.navy,
                        lineHeight: 1.1,
                    }}
                >
                    {event.start_date
                        ? Number(event.start_date.slice(8, 10))
                        : '—'}
                </div>
                <div style={{ fontSize: 10.5, color: C.faint }}>
                    {event.start_date
                        ? new Date(
                              `${event.start_date}T00:00:00`,
                          ).toLocaleDateString('id-ID', { month: 'short' })
                        : ''}
                </div>
            </div>

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
                        label={SCOPE_LABEL[event.scope]}
                        color={SCOPE_TONE[event.scope] ?? C.muted}
                    />
                    {event.type_label && (
                        <Pill label={event.type_label} color={C.faint} />
                    )}
                </div>
                <div style={{ fontSize: 11.5, color: C.faint, marginTop: 3 }}>
                    {formatDate(event.start_date)}
                    {event.end_date &&
                        event.end_date !== event.start_date &&
                        ` – ${formatDate(event.end_date)}`}
                    {event.all_day ? ' · sehari penuh' : ''}
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
    );
}
