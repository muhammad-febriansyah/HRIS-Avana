import { Head, Link } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import { RecruitmentHeader } from './shell';

interface Rec {
    id: number;
    name: string;
    job_title: string | null;
    stage: string | null;
    confidence: number | null;
    recommendation: string | null;
}

interface Tier {
    label: string;
    color: string;
    bg: string;
}

const STAGE_LABELS: Record<string, string> = {
    applied: 'Melamar',
    screening: 'Screening',
    shortlisted: 'Shortlist',
    interview: 'Wawancara',
    offer: 'Penawaran',
    hired: 'Diterima',
    rejected: 'Ditolak',
};

/** Classify a match score into a colour-coded recommendation tier. */
function tierOf(confidence: number | null): Tier {
    const score = confidence ?? 0;

    if (score >= 80) {
        return {
            label: 'Rekomendasi Kuat',
            color: C.green,
            bg: 'rgba(22,163,74,.1)',
        };
    }

    if (score >= 60) {
        return {
            label: 'Layak Dipertimbangkan',
            color: C.amber,
            bg: 'rgba(217,119,6,.1)',
        };
    }

    return { label: 'Kurang Sesuai', color: C.red, bg: 'rgba(220,38,38,.1)' };
}

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function Stat({
    label,
    value,
    hint,
    color,
}: {
    label: string;
    value: string;
    hint?: string;
    color?: string;
}) {
    return (
        <div style={{ ...card, padding: '16px 18px' }}>
            <div
                style={{
                    fontSize: 12,
                    color: C.faint,
                    fontWeight: 600,
                    letterSpacing: '.02em',
                }}
            >
                {label}
            </div>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'baseline',
                    gap: 7,
                    marginTop: 6,
                }}
            >
                <span
                    style={{
                        fontSize: 24,
                        fontWeight: 700,
                        color: color ?? C.navy,
                    }}
                >
                    {value}
                </span>
                {hint && (
                    <span style={{ fontSize: 12, color: C.faint }}>{hint}</span>
                )}
            </div>
        </div>
    );
}

export default function RecruitmentAi({
    recommendations,
}: {
    recommendations: Rec[];
}) {
    const total = recommendations.length;
    const scores = recommendations.map((r) => r.confidence ?? 0);
    const avg =
        total > 0
            ? Math.round(scores.reduce((sum, value) => sum + value, 0) / total)
            : 0;
    const strong = recommendations.filter(
        (r) => (r.confidence ?? 0) >= 80,
    ).length;
    const review = recommendations.filter(
        (r) => (r.confidence ?? 0) < 60,
    ).length;

    const backLink = (
        <Link
            href="/avana/rekrutmen/candidates"
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 7,
                fontSize: 13.5,
                fontWeight: 600,
                color: C.navy,
                padding: '9px 15px',
                borderRadius: 8,
                border: `1px solid ${C.line}`,
                textDecoration: 'none',
            }}
        >
            <AIcon name="arrow-left" size={15} color={C.navy} />
            Ke Kandidat
        </Link>
    );

    const chip: CSSProperties = {
        display: 'inline-flex',
        alignItems: 'center',
        gap: 5,
        fontSize: 11.5,
        color: C.muted,
        background: C.surface,
        border: `1px solid ${C.line}`,
        padding: '2px 9px',
        borderRadius: 100,
    };

    return (
        <>
            <Head title="AI Intelligence" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="AI Intelligence"
                    subtitle="Peringkat kandidat berdasarkan skor kecocokan AI, dari yang paling sesuai."
                    action={backLink}
                />

                {total === 0 ? (
                    <div
                        style={{
                            ...card,
                            padding: '60px 24px',
                            textAlign: 'center',
                            border: `1px dashed ${C.line}`,
                        }}
                    >
                        <div
                            style={{
                                width: 64,
                                height: 64,
                                borderRadius: 16,
                                margin: '0 auto 18px',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                background: '#F3E8FF',
                            }}
                        >
                            <AIcon name="sparkles" size={30} color="#7C3AED" />
                        </div>
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 700,
                                color: C.navy,
                            }}
                        >
                            Belum Ada Rekomendasi AI
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                maxWidth: 440,
                                margin: '8px auto 0',
                            }}
                        >
                            Fitur pencocokan AI belum diaktifkan. Rekomendasi
                            akan muncul di sini saat kandidat dicocokkan dengan
                            posisi terbuka berdasarkan skill, pengalaman, dan
                            ketersediaan.
                        </div>
                    </div>
                ) : (
                    <>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    'repeat(auto-fit, minmax(180px, 1fr))',
                                gap: 14,
                                marginBottom: 20,
                            }}
                        >
                            <Stat
                                label="Total Kandidat"
                                value={String(total)}
                            />
                            <Stat
                                label="Rata-rata Skor"
                                value={`${avg}%`}
                                color={tierOf(avg).color}
                            />
                            <Stat
                                label="Rekomendasi Kuat"
                                value={String(strong)}
                                hint="skor ≥ 80%"
                                color={C.green}
                            />
                            <Stat
                                label="Perlu Ditinjau"
                                value={String(review)}
                                hint="skor < 60%"
                                color={C.red}
                            />
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 10,
                            }}
                        >
                            {recommendations.map((r, index) => {
                                const tier = tierOf(r.confidence);
                                const score = r.confidence ?? 0;

                                return (
                                    <div
                                        key={r.id}
                                        style={{
                                            ...card,
                                            padding: '14px 18px',
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 16,
                                        }}
                                    >
                                        <div
                                            style={{
                                                width: 22,
                                                textAlign: 'center',
                                                fontSize: 13,
                                                fontWeight: 700,
                                                color: C.faint,
                                                flex: 'none',
                                            }}
                                        >
                                            {index + 1}
                                        </div>

                                        <div
                                            style={{
                                                width: 42,
                                                height: 42,
                                                borderRadius: 11,
                                                flex: 'none',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                background: tier.bg,
                                                color: tier.color,
                                                fontSize: 14,
                                                fontWeight: 700,
                                            }}
                                        >
                                            {initials(r.name)}
                                        </div>

                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div
                                                style={{
                                                    fontSize: 14.5,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                    whiteSpace: 'nowrap',
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                }}
                                            >
                                                {r.name}
                                            </div>
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 7,
                                                    marginTop: 5,
                                                    flexWrap: 'wrap',
                                                }}
                                            >
                                                <span style={chip}>
                                                    <AIcon
                                                        name="briefcase"
                                                        size={11}
                                                        color={C.faint}
                                                    />
                                                    {r.job_title ?? '—'}
                                                </span>
                                                {r.stage && (
                                                    <span style={chip}>
                                                        {STAGE_LABELS[
                                                            r.stage
                                                        ] ?? r.stage}
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        <span
                                            style={{
                                                fontSize: 11.5,
                                                fontWeight: 700,
                                                padding: '5px 12px',
                                                borderRadius: 100,
                                                color: tier.color,
                                                background: tier.bg,
                                                whiteSpace: 'nowrap',
                                                flex: 'none',
                                            }}
                                        >
                                            {tier.label}
                                        </span>

                                        <div
                                            style={{ width: 128, flex: 'none' }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'baseline',
                                                    justifyContent: 'flex-end',
                                                    gap: 1,
                                                }}
                                            >
                                                <span
                                                    style={{
                                                        fontSize: 22,
                                                        fontWeight: 700,
                                                        color: tier.color,
                                                    }}
                                                >
                                                    {score}
                                                </span>
                                                <span
                                                    style={{
                                                        fontSize: 12,
                                                        fontWeight: 600,
                                                        color: tier.color,
                                                    }}
                                                >
                                                    %
                                                </span>
                                            </div>
                                            <div
                                                style={{
                                                    height: 6,
                                                    borderRadius: 6,
                                                    background: C.line,
                                                    marginTop: 5,
                                                    overflow: 'hidden',
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        width: `${score}%`,
                                                        height: '100%',
                                                        background: tier.color,
                                                        borderRadius: 6,
                                                    }}
                                                />
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 10.5,
                                                    color: C.faint,
                                                    textAlign: 'right',
                                                    marginTop: 4,
                                                }}
                                            >
                                                Skor kecocokan
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
