import { Head } from '@inertiajs/react';
import { C, card } from '@/lib/avana';
import { Empty, RecruitmentHeader, td, th } from './shell';

interface Candidate {
    id: number;
    name: string;
    stage: string;
    stage_label: string;
    last_update: string | null;
}

/** One manpower need, with the candidates chasing it. */
interface Need {
    id: number;
    position_title: string;
    department: string | null;
    vacancy: number;
    candidates: Candidate[];
}

interface Row {
    id: number;
    request_number: string | null;
    status: string;
    vacancy: number;
    needs: Need[];
}

interface Props {
    requests: Row[];
}

const STAGE_COLOR: Record<string, { c: string; bg: string }> = {
    applied: { c: '#1D4ED8', bg: '#DBEAFE' },
    screening: { c: '#B45309', bg: '#FEF3C7' },
    shortlisted: { c: '#7C3AED', bg: '#EDE9FE' },
    interview: { c: '#0891B2', bg: '#CFFAFE' },
    offer: { c: '#C2410C', bg: '#FFEDD5' },
    hired: { c: '#15803D', bg: '#DCFCE7' },
    rejected: { c: '#B91C1C', bg: '#FEE2E2' },
};

const REQ_STATUS: Record<string, string> = {
    open: 'Open',
    in_process: 'Diproses',
    closed: 'Ditutup',
};

export default function CandidateProgressPage({ requests }: Props) {
    return (
        <>
            <Head title="Candidate Progress" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="Candidate Progress"
                    subtitle="Pantau perkembangan kandidat dari hiring request Anda (hanya lihat, stage 9)."
                />

                {requests.length === 0 ? (
                    <div style={{ ...card, padding: 0 }}>
                        <Empty
                            icon="activity"
                            title="Belum ada progress"
                            hint="Kandidat dari hiring request Anda akan tampil di sini."
                        />
                    </div>
                ) : (
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 16,
                        }}
                    >
                        {requests.map((r) => (
                            <div
                                key={r.id}
                                style={{
                                    ...card,
                                    padding: 0,
                                    overflow: 'hidden',
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        alignItems: 'center',
                                        flexWrap: 'wrap',
                                        gap: 10,
                                        padding: '16px 20px',
                                        borderBottom: `1px solid ${C.line}`,
                                        background: '#F8FAFC',
                                    }}
                                >
                                    <div
                                        style={{
                                            fontSize: 14,
                                            fontWeight: 700,
                                            color: C.navy,
                                        }}
                                    >
                                        {r.request_number ?? '—'}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.muted,
                                            fontWeight: 600,
                                        }}
                                    >
                                        {r.needs.length} kebutuhan · {r.vacancy}{' '}
                                        lowongan ·{' '}
                                        {REQ_STATUS[r.status] ?? r.status}
                                    </div>
                                </div>

                                {/* One block per need: a request for three
                                    different roles would otherwise pool every
                                    candidate into one undifferentiated list. */}
                                {r.needs.map((need) => (
                                    <NeedBlock key={need.id} need={need} />
                                ))}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function NeedBlock({ need }: { need: Need }) {
    return (
        <div style={{ borderBottom: `1px solid ${C.line}` }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    flexWrap: 'wrap',
                    gap: 10,
                    padding: '14px 20px',
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
                        {need.position_title}
                    </div>
                    <div
                        style={{
                            fontSize: 12.5,
                            color: C.faint,
                            marginTop: 2,
                        }}
                    >
                        {[need.department, `${need.vacancy} lowongan`]
                            .filter(Boolean)
                            .join(' · ')}
                    </div>
                </div>
                <div
                    style={{
                        fontSize: 12.5,
                        color: C.muted,
                        fontWeight: 600,
                    }}
                >
                    {need.candidates.length} kandidat
                </div>
            </div>

            {need.candidates.length === 0 ? (
                <div
                    style={{
                        padding: '22px 20px',
                        fontSize: 13,
                        color: C.faint,
                    }}
                >
                    Belum ada kandidat.
                </div>
            ) : (
                <div style={{ overflowX: 'auto' }}>
                    <table
                        style={{
                            width: '100%',
                            borderCollapse: 'collapse',
                            minWidth: 480,
                        }}
                    >
                        <thead>
                            <tr>
                                {['Kandidat', 'Status', 'Update Terakhir'].map(
                                    (h) => (
                                        <th
                                            key={h}
                                            style={{
                                                ...th,
                                                paddingTop: 14,
                                            }}
                                        >
                                            {h}
                                        </th>
                                    ),
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {need.candidates.map((c) => {
                                const sc =
                                    STAGE_COLOR[c.stage] ?? STAGE_COLOR.applied;

                                return (
                                    <tr key={c.id}>
                                        <td
                                            style={{
                                                ...td,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {c.name}
                                        </td>
                                        <td style={td}>
                                            <span
                                                style={{
                                                    fontSize: 12,
                                                    fontWeight: 700,
                                                    padding: '4px 10px',
                                                    borderRadius: 6,
                                                    color: sc.c,
                                                    background: sc.bg,
                                                }}
                                            >
                                                {c.stage_label}
                                            </span>
                                        </td>
                                        <td
                                            style={{
                                                ...td,
                                                color: C.muted,
                                            }}
                                        >
                                            {c.last_update ?? '—'}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
