import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import { DataTable } from '@/pages/avana/pengumuman/data-table';
import { makeAiColumns, type Rec, tierOf } from './ai-columns';
import { RecruitmentHeader } from './shell';

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
    const columns = useMemo(() => makeAiColumns(), []);

    const total = recommendations.length;
    const avg =
        total > 0
            ? Math.round(
                  recommendations.reduce(
                      (sum, r) => sum + (r.confidence ?? 0),
                      0,
                  ) / total,
              )
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

                        <DataTable
                            columns={columns}
                            data={recommendations}
                            searchPlaceholder="Cari kandidat atau posisi…"
                            pageSize={10}
                        />
                    </>
                )}
            </div>
        </>
    );
}
