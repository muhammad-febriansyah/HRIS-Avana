import { Head, Link } from '@inertiajs/react';
import { AIcon, C, card } from '@/lib/avana';
import { RecruitmentHeader } from './shell';

interface Rec {
    id: number;
    name: string;
    job_title: string | null;
    confidence: number | null;
    recommendation: string | null;
}

export default function RecruitmentAi({
    recommendations,
}: {
    recommendations: Rec[];
}) {
    return (
        <>
            <Head title="AI Intelligence" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="AI Intelligence"
                    subtitle="Rekrutmen cerdas berbasis pencocokan kandidat."
                    action={
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
                    }
                />

                {recommendations.length === 0 ? (
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
                            Fitur pencocokan AI belum diaktifkan. Rekomendasi akan
                            muncul di sini saat kandidat cocok dengan posisi
                            terbuka berdasarkan skill, pengalaman, dan
                            ketersediaan.
                        </div>
                    </div>
                ) : (
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 12,
                        }}
                    >
                        {recommendations.map((r) => (
                            <div
                                key={r.id}
                                style={{
                                    ...card,
                                    padding: '16px 20px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                }}
                            >
                                <div>
                                    <div
                                        style={{
                                            fontSize: 14.5,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {r.name}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.faint,
                                        }}
                                    >
                                        {r.job_title ?? '—'}
                                    </div>
                                </div>
                                <span
                                    style={{
                                        fontSize: 12,
                                        fontWeight: 700,
                                        padding: '5px 12px',
                                        borderRadius: 7,
                                        color: '#7C3AED',
                                        background: '#F3E8FF',
                                    }}
                                >
                                    {(r.recommendation ?? '').toUpperCase()}
                                    {r.confidence !== null
                                        ? ` · ${r.confidence}%`
                                        : ''}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
