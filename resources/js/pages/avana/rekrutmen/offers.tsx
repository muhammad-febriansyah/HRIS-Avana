import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import RecruitmentController from '@/actions/App/Http/Controllers/Avana/RecruitmentController';
import { usePermission } from '@/hooks/use-permission';
import { AIcon, ActionBtn, btnOut, btnP, C, card, rp } from '@/lib/avana';
import { Empty, RecruitmentHeader, td, th } from './shell';

interface OnboardingItem {
    id: number;
    label: string;
    is_done: boolean;
}

interface Offer {
    id: number;
    name: string;
    job_title: string | null;
    salary: number | null;
    start_date: string | null;
    status: string;
    note: string | null;
    activated: boolean;
    onboarding_items: OnboardingItem[];
    onboarding_ready: boolean;
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
    const [onbId, setOnbId] = useState<number | null>(null);
    const onbOffer = offers.find((o) => o.id === onbId) ?? null;

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

    const startOnboarding = (id: number) =>
        router.post(
            RecruitmentController.startOnboarding(id).url,
            {},
            { preserveScroll: true },
        );

    const toggleItem = (itemId: number) =>
        router.post(
            RecruitmentController.toggleOnboardingItem(itemId).url,
            {},
            { preserveScroll: true },
        );

    const activate = (id: number) => {
        router.post(
            RecruitmentController.activateEmployee(id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Kandidat diaktivasi');
                    setOnbId(null);
                },
                onError: () =>
                    toast.error('Lengkapi checklist onboarding dulu'),
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
                                                                flexWrap:
                                                                    'wrap',
                                                            }}
                                                        >
                                                            <ActionBtn
                                                                icon="check"
                                                                label="Setujui"
                                                                variant="success"
                                                                onClick={() =>
                                                                    decide(
                                                                        o.id,
                                                                        'approved',
                                                                    )
                                                                }
                                                            />
                                                            <ActionBtn
                                                                icon="x"
                                                                label="Tolak"
                                                                variant="danger"
                                                                onClick={() =>
                                                                    decide(
                                                                        o.id,
                                                                        'rejected',
                                                                    )
                                                                }
                                                            />
                                                        </div>
                                                    ) : canApprove &&
                                                      o.status ===
                                                          'approved' ? (
                                                        <ActionBtn
                                                            icon="badge-check"
                                                            label="Tandai Diterima"
                                                            variant="primary"
                                                            onClick={() =>
                                                                decide(
                                                                    o.id,
                                                                    'accepted',
                                                                )
                                                            }
                                                        />
                                                    ) : o.status ===
                                                          'accepted' &&
                                                      !o.activated &&
                                                      canApprove ? (
                                                        <button
                                                            onClick={() =>
                                                                setOnbId(o.id)
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
                                                                display:
                                                                    'inline-flex',
                                                                alignItems:
                                                                    'center',
                                                                gap: 5,
                                                            }}
                                                        >
                                                            <AIcon
                                                                name="clipboard-check"
                                                                size={13}
                                                                color="#15803D"
                                                            />
                                                            Onboarding
                                                        </button>
                                                    ) : o.activated ? (
                                                        <span
                                                            style={{
                                                                fontSize: 12,
                                                                fontWeight: 600,
                                                                color: C.green,
                                                            }}
                                                        >
                                                            ✓ Karyawan Aktif
                                                        </span>
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

            {onbOffer && (
                <div
                    onClick={() => setOnbId(null)}
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(15,23,42,.45)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 50,
                        padding: 20,
                    }}
                >
                    <div
                        onClick={(e) => e.stopPropagation()}
                        style={{
                            ...card,
                            width: 460,
                            maxWidth: '100%',
                            padding: 24,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 17,
                                fontWeight: 700,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Onboarding — {onbOffer.name}
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 16,
                            }}
                        >
                            Selesaikan seluruh checklist sebelum mengaktivasi
                            kandidat menjadi karyawan.
                        </div>

                        {onbOffer.onboarding_items.length === 0 ? (
                            <button
                                onClick={() => startOnboarding(onbOffer.id)}
                                style={{
                                    ...btnOut,
                                    width: '100%',
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon name="list-plus" size={15} />
                                Siapkan Checklist
                            </button>
                        ) : (
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 8,
                                    marginBottom: 18,
                                }}
                            >
                                {onbOffer.onboarding_items.map((it) => (
                                    <button
                                        key={it.id}
                                        onClick={() => toggleItem(it.id)}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 10,
                                            padding: '10px 12px',
                                            borderRadius: 9,
                                            border: `1px solid ${it.is_done ? '#BBF7D0' : C.border}`,
                                            background: it.is_done
                                                ? '#F0FDF4'
                                                : '#fff',
                                            cursor: 'pointer',
                                            textAlign: 'left',
                                        }}
                                    >
                                        <span
                                            style={{
                                                width: 20,
                                                height: 20,
                                                borderRadius: 6,
                                                flex: 'none',
                                                border: `1.5px solid ${it.is_done ? C.green : C.border}`,
                                                background: it.is_done
                                                    ? C.green
                                                    : '#fff',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                            }}
                                        >
                                            {it.is_done && (
                                                <AIcon
                                                    name="check"
                                                    size={13}
                                                    color="#fff"
                                                />
                                            )}
                                        </span>
                                        <span
                                            style={{
                                                fontSize: 13.5,
                                                color: C.text,
                                                fontWeight: 500,
                                            }}
                                        >
                                            {it.label}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        )}

                        <button
                            onClick={() => activate(onbOffer.id)}
                            disabled={!onbOffer.onboarding_ready}
                            style={{
                                ...btnP,
                                width: '100%',
                                justifyContent: 'center',
                                background: onbOffer.onboarding_ready
                                    ? C.green
                                    : C.faint,
                                cursor: onbOffer.onboarding_ready
                                    ? 'pointer'
                                    : 'not-allowed',
                            }}
                        >
                            <AIcon name="user-check" size={15} color="#fff" />
                            Aktivasi Karyawan
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}
