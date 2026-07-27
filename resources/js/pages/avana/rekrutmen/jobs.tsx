import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { usePermission } from '@/hooks/use-permission';
import { AIcon, btnP, C, card } from '@/lib/avana';
import { Empty, Kpi, KpiRow, RecruitmentHeader } from './shell';

interface Posting {
    id: number;
    title: string;
    department: string | null;
    location: string | null;
    employment_type: string;
    quota: number;
    status: string;
    description: string | null;
    posted_date: string | null;
    applicants_count: number;
}

interface JobsProps {
    postings: Posting[];
    kpis: {
        open_postings: number;
        total_postings: number;
        total_quota: number;
    };
}

const TYPE_LABEL: Record<string, string> = {
    tetap: 'FULL TIME',
    kontrak: 'KONTRAK',
    magang: 'MAGANG',
    harian: 'HARIAN',
};

function subjectCode(title: string): string {
    return `[${title.trim().slice(0, 18)}]`;
}

export default function RecruitmentJobs({ postings, kpis }: JobsProps) {
    const { can } = usePermission();
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        if (!q) {
            return postings;
        }

        return postings.filter(
            (p) =>
                p.title.toLowerCase().includes(q) ||
                (p.department ?? '').toLowerCase().includes(q),
        );
    }, [postings, query]);

    const copy = (code: string) => {
        void navigator.clipboard?.writeText(code);
        toast.success('Kode email disalin');
    };

    return (
        <>
            <Head title="Lowongan" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="Lowongan"
                    subtitle="Kelola posisi terbuka & pantau minat pelamar."
                    action={
                        can('recruitment.create') ? (
                            <Link href="/avana/rekrutmen/create" style={btnP}>
                                <AIcon name="plus" size={16} color="#fff" />
                                Buat Lowongan Baru
                            </Link>
                        ) : null
                    }
                />

                <KpiRow>
                    <Kpi
                        label="Lowongan Terbuka"
                        value={kpis.open_postings}
                        icon="briefcase"
                        color={C.primary}
                    />
                    <Kpi
                        label="Total Lowongan"
                        value={kpis.total_postings}
                        icon="layers"
                        color="#7C3AED"
                    />
                    <Kpi
                        label="Total Kuota"
                        value={kpis.total_quota}
                        icon="users"
                        color={C.green}
                    />
                    <Kpi
                        label="Total Pelamar"
                        value={postings.reduce(
                            (s, p) => s + p.applicants_count,
                            0,
                        )}
                        icon="user-plus"
                        color={C.amber}
                    />
                </KpiRow>

                <div style={{ ...card, padding: 14, marginBottom: 18 }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '0 6px',
                        }}
                    >
                        <AIcon name="search" size={17} color={C.faint} />
                        <input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Cari berdasarkan judul atau departemen…"
                            style={{
                                flex: 1,
                                border: 'none',
                                outline: 'none',
                                fontSize: 14,
                                color: C.text,
                                background: 'transparent',
                            }}
                        />
                    </div>
                </div>

                {filtered.length === 0 ? (
                    <div style={{ ...card, padding: 20 }}>
                        <Empty
                            icon="briefcase"
                            title="Belum ada lowongan"
                            hint="Buat lowongan pertama untuk mulai menerima pelamar."
                        />
                    </div>
                ) : (
                    <div
                        className="avn-cards"
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fill, minmax(300px, 1fr))',
                            gap: 18,
                        }}
                    >
                        {filtered.map((p) => (
                            <div
                                key={p.id}
                                style={{
                                    ...card,
                                    padding: 0,
                                    overflow: 'hidden',
                                }}
                            >
                                <div style={{ padding: '20px 20px 16px' }}>
                                    <div
                                        style={{
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                            alignItems: 'flex-start',
                                            marginBottom: 14,
                                        }}
                                    >
                                        <div
                                            style={{
                                                width: 40,
                                                height: 40,
                                                borderRadius: 10,
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                background: C.primary + '14',
                                            }}
                                        >
                                            <AIcon
                                                name="briefcase"
                                                size={19}
                                                color={C.primary}
                                            />
                                        </div>
                                        <span
                                            style={{
                                                fontSize: 11,
                                                fontWeight: 700,
                                                letterSpacing: '.04em',
                                                padding: '4px 10px',
                                                borderRadius: 6,
                                                color:
                                                    p.status === 'open'
                                                        ? '#1D4ED8'
                                                        : C.muted,
                                                background:
                                                    p.status === 'open'
                                                        ? '#DBEAFE'
                                                        : C.line,
                                            }}
                                        >
                                            {p.status === 'open'
                                                ? 'PUBLISHED'
                                                : 'CLOSED'}
                                        </span>
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 17,
                                            fontWeight: 700,
                                            color: C.navy,
                                        }}
                                    >
                                        {p.title}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.muted,
                                            marginTop: 4,
                                        }}
                                    >
                                        {(p.department ?? 'Umum') +
                                            ' · ' +
                                            (TYPE_LABEL[p.employment_type] ??
                                                p.employment_type) +
                                            (p.posted_date
                                                ? ' · ' + p.posted_date
                                                : '')}
                                    </div>
                                    {p.description && (
                                        <div
                                            style={{
                                                fontSize: 13,
                                                color: C.text,
                                                marginTop: 10,
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {p.description}
                                        </div>
                                    )}

                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 8,
                                            marginTop: 14,
                                            padding: '9px 11px',
                                            borderRadius: 8,
                                            background: '#FEF9C3',
                                            fontSize: 12,
                                        }}
                                    >
                                        <span style={{ color: '#854D0E' }}>
                                            Kode email:
                                        </span>
                                        <code
                                            style={{
                                                flex: 1,
                                                fontSize: 11.5,
                                                color: '#713F12',
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {subjectCode(p.title)}
                                        </code>
                                        <button
                                            onClick={() =>
                                                copy(subjectCode(p.title))
                                            }
                                            style={{
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                gap: 5,
                                                border: '1px solid rgba(180,83,9,.3)',
                                                borderRadius: 6,
                                                padding: '4px 9px',
                                                background:
                                                    'rgba(180,83,9,.08)',
                                                color: '#B45309',
                                                fontWeight: 700,
                                                fontSize: 12,
                                                cursor: 'pointer',
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            <AIcon
                                                name="copy"
                                                size={12}
                                                color="#B45309"
                                            />
                                            Salin
                                        </button>
                                    </div>
                                </div>
                                <Link
                                    href="/avana/rekrutmen/candidates"
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        gap: 7,
                                        padding: '13px',
                                        borderTop: `1px solid ${C.line}`,
                                        fontSize: 13.5,
                                        fontWeight: 600,
                                        color: C.navy,
                                        textDecoration: 'none',
                                    }}
                                >
                                    Lihat Kandidat ({p.applicants_count})
                                    <AIcon
                                        name="arrow-right"
                                        size={15}
                                        color={C.navy}
                                    />
                                </Link>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
