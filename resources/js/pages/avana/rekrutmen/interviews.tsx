import { Head } from '@inertiajs/react';
import { AIcon, C, card } from '@/lib/avana';
import { Empty, RecruitmentHeader, td, th } from './shell';

interface Interview {
    id: number;
    name: string;
    job_title: string | null;
    type: string | null;
    status: string;
    location: string | null;
    interview_at: string | null;
}

const TYPE_LABEL: Record<string, string> = {
    hr: 'HR',
    technical: 'Teknis',
    user: 'User',
    final: 'Final',
};

const STATUS_STYLE: Record<string, { c: string; bg: string; label: string }> = {
    scheduled: { c: '#1D4ED8', bg: '#DBEAFE', label: 'Terjadwal' },
    completed: { c: '#15803D', bg: '#DCFCE7', label: 'Selesai' },
    cancelled: { c: '#B91C1C', bg: '#FEE2E2', label: 'Dibatalkan' },
};

export default function RecruitmentInterviews({
    interviews,
}: {
    interviews: Interview[];
}) {
    return (
        <>
            <Head title="Interview" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="Interview"
                    subtitle="Kelola wawancara kandidat & scorecard."
                />

                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '18px 20px 4px',
                            fontSize: 15,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        Jadwal Wawancara
                    </div>
                    {interviews.length === 0 ? (
                        <Empty
                            icon="calendar-days"
                            title="Belum ada wawancara"
                            hint="Jadwalkan wawancara dari detail kandidat."
                        />
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                }}
                            >
                                <thead>
                                    <tr>
                                        {[
                                            'Kandidat',
                                            'Jenis & Status',
                                            'Waktu',
                                            'Lokasi',
                                            'Aksi',
                                        ].map((h) => (
                                            <th
                                                key={h}
                                                style={{ ...th, paddingTop: 14 }}
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {interviews.map((iv) => {
                                        const st =
                                            STATUS_STYLE[iv.status] ??
                                            STATUS_STYLE.scheduled;
                                        return (
                                            <tr key={iv.id}>
                                                <td style={td}>
                                                    <div
                                                        style={{
                                                            fontWeight: 600,
                                                            color: C.navy,
                                                        }}
                                                    >
                                                        {iv.name}
                                                    </div>
                                                    <div
                                                        style={{
                                                            fontSize: 12,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        {iv.job_title ?? '—'}
                                                    </div>
                                                </td>
                                                <td style={td}>
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            flexDirection:
                                                                'column',
                                                            gap: 5,
                                                            alignItems:
                                                                'flex-start',
                                                        }}
                                                    >
                                                        <span
                                                            style={{
                                                                fontSize: 12,
                                                                fontWeight: 600,
                                                                color: C.text,
                                                                padding:
                                                                    '3px 9px',
                                                                borderRadius: 6,
                                                                border: `1px solid ${C.line}`,
                                                            }}
                                                        >
                                                            {TYPE_LABEL[
                                                                iv.type ?? 'hr'
                                                            ] ?? 'HR'}
                                                        </span>
                                                        <span
                                                            style={{
                                                                fontSize: 11.5,
                                                                fontWeight: 700,
                                                                padding:
                                                                    '3px 9px',
                                                                borderRadius: 6,
                                                                color: st.c,
                                                                background: st.bg,
                                                            }}
                                                        >
                                                            {st.label}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {iv.interview_at
                                                        ? iv.interview_at.slice(
                                                              0,
                                                              16,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td style={td}>
                                                    <span
                                                        style={{
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            gap: 5,
                                                            color: C.muted,
                                                        }}
                                                    >
                                                        <AIcon
                                                            name="map-pin"
                                                            size={13}
                                                            color={C.faint}
                                                        />
                                                        {iv.location ?? 'Online'}
                                                    </span>
                                                </td>
                                                <td style={td}>
                                                    <span
                                                        style={{
                                                            display: 'inline-flex',
                                                            alignItems: 'center',
                                                            gap: 6,
                                                            fontSize: 12.5,
                                                            fontWeight: 600,
                                                            color: C.primary,
                                                        }}
                                                    >
                                                        <AIcon
                                                            name="clipboard-check"
                                                            size={14}
                                                            color={C.primary}
                                                        />
                                                        Scorecard
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
