import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import RecruitmentController from '@/actions/App/Http/Controllers/Avana/RecruitmentController';
import { usePermission } from '@/hooks/use-permission';
import { C, card, rp } from '@/lib/avana';
import { Empty, RecruitmentHeader, td, th } from './shell';

interface Offer {
    id: number;
    name: string;
    job_title: string | null;
    salary: number | null;
    start_date: string | null;
    status: string;
    note: string | null;
}

const STATUS_STYLE: Record<string, { c: string; bg: string; label: string }> = {
    draft: { c: C.muted, bg: C.line, label: 'Draft' },
    sent: { c: '#1D4ED8', bg: '#DBEAFE', label: 'Terkirim' },
    approved: { c: '#15803D', bg: '#DCFCE7', label: 'Disetujui' },
    accepted: { c: '#15803D', bg: '#DCFCE7', label: 'Diterima' },
    rejected: { c: '#B91C1C', bg: '#FEE2E2', label: 'Ditolak' },
};

export default function RecruitmentOffers({ offers }: { offers: Offer[] }) {
    const { can } = usePermission();
    const canApprove = can('recruitment.approve');
    const decide = (
        id: number,
        offer_status: 'approved' | 'accepted' | 'rejected',
    ) => {
        router.post(
            RecruitmentController.decideOffer(id).url,
            { offer_status },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Status penawaran diperbarui'),
            },
        );
    };

    return (
        <>
            <Head title="Penawaran" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="Penawaran"
                    subtitle="Kelola penawaran kandidat dengan alur persetujuan."
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
                        Semua Penawaran
                    </div>
                    {offers.length === 0 ? (
                        <Empty
                            icon="file-check"
                            title="Belum ada penawaran"
                            hint="Buat penawaran dari detail kandidat pada tahap Penawaran."
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
                                            'Gaji',
                                            'Mulai Kerja',
                                            'Status',
                                            'Aksi',
                                        ].map((h) => (
                                            <th
                                                key={h}
                                                style={{
                                                    ...th,
                                                    paddingTop: 14,
                                                }}
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {offers.map((o) => {
                                        const st =
                                            STATUS_STYLE[o.status] ??
                                            STATUS_STYLE.draft;

                                        return (
                                            <tr key={o.id}>
                                                <td style={td}>
                                                    <div
                                                        style={{
                                                            fontWeight: 600,
                                                            color: C.navy,
                                                        }}
                                                    >
                                                        {o.name}
                                                    </div>
                                                    <div
                                                        style={{
                                                            fontSize: 12,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        {o.job_title ?? '—'}
                                                    </div>
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {o.salary !== null
                                                        ? rp(o.salary)
                                                        : '—'}
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {o.start_date ?? '—'}
                                                </td>
                                                <td style={td}>
                                                    <span
                                                        style={{
                                                            fontSize: 12,
                                                            fontWeight: 700,
                                                            padding: '4px 10px',
                                                            borderRadius: 6,
                                                            color: st.c,
                                                            background: st.bg,
                                                        }}
                                                    >
                                                        {st.label}
                                                    </span>
                                                </td>
                                                <td style={td}>
                                                    {canApprove &&
                                                    ['sent', 'draft'].includes(
                                                        o.status,
                                                    ) ? (
                                                        <div
                                                            style={{
                                                                display: 'flex',
                                                                gap: 6,
                                                            }}
                                                        >
                                                            <button
                                                                onClick={() =>
                                                                    decide(
                                                                        o.id,
                                                                        'approved',
                                                                    )
                                                                }
                                                                style={{
                                                                    fontSize: 12,
                                                                    fontWeight: 600,
                                                                    color: '#15803D',
                                                                    padding:
                                                                        '5px 10px',
                                                                    borderRadius: 6,
                                                                    border: '1px solid #BBF7D0',
                                                                    background:
                                                                        '#F0FDF4',
                                                                    cursor: 'pointer',
                                                                }}
                                                            >
                                                                Setujui
                                                            </button>
                                                            <button
                                                                onClick={() =>
                                                                    decide(
                                                                        o.id,
                                                                        'rejected',
                                                                    )
                                                                }
                                                                style={{
                                                                    fontSize: 12,
                                                                    fontWeight: 600,
                                                                    color: '#B91C1C',
                                                                    padding:
                                                                        '5px 10px',
                                                                    borderRadius: 6,
                                                                    border: '1px solid #FECACA',
                                                                    background:
                                                                        '#FEF2F2',
                                                                    cursor: 'pointer',
                                                                }}
                                                            >
                                                                Tolak
                                                            </button>
                                                        </div>
                                                    ) : canApprove &&
                                                      o.status ===
                                                          'approved' ? (
                                                        <button
                                                            onClick={() =>
                                                                decide(
                                                                    o.id,
                                                                    'accepted',
                                                                )
                                                            }
                                                            style={{
                                                                fontSize: 12,
                                                                fontWeight: 600,
                                                                color: C.primary,
                                                                padding:
                                                                    '5px 10px',
                                                                borderRadius: 6,
                                                                border: `1px solid ${C.primary}44`,
                                                                background:
                                                                    C.primary +
                                                                    '10',
                                                                cursor: 'pointer',
                                                            }}
                                                        >
                                                            Tandai Diterima
                                                        </button>
                                                    ) : (
                                                        <span
                                                            style={{
                                                                fontSize: 12,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            —
                                                        </span>
                                                    )}
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
