import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { AIcon, C, card } from '@/lib/avana';
import { DataTable } from '@/pages/avana/pengumuman/data-table';
import { makeAiColumns, type Rec, tierOf } from './ai-columns';
import { RecruitmentHeader } from './shell';

/** Laravel's XSRF-TOKEN cookie, decoded, for the streaming fetch POST. */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
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
    const columns = useMemo(() => makeAiColumns(), []);
    const [rows, setRows] = useState<Rec[]>(recommendations);
    const [analyzing, setAnalyzing] = useState(false);
    const [progress, setProgress] = useState({ done: 0, total: 0 });

    const total = rows.length;
    const avg =
        total > 0
            ? Math.round(
                  rows.reduce((sum, r) => sum + (r.confidence ?? 0), 0) / total,
              )
            : 0;
    const strong = rows.filter((r) => (r.confidence ?? 0) >= 80).length;
    const review = rows.filter((r) => (r.confidence ?? 0) < 60).length;

    const analyze = async () => {
        if (analyzing) {
            return;
        }

        setAnalyzing(true);
        setProgress({ done: 0, total: rows.length });

        try {
            const response = await fetch('/avana/rekrutmen/ai/analyze', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-XSRF-TOKEN': xsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/x-ndjson',
                },
            });

            if (!response.ok || !response.body) {
                throw new Error('request failed');
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let processed = 0;

            const handle = (message: Record<string, unknown>) => {
                if (message.error) {
                    toast.error(String(message.error));
                    return;
                }

                if (typeof message.total === 'number') {
                    setProgress({ done: 0, total: message.total });
                    return;
                }

                if (message.done) {
                    toast.success(
                        `Analisa AI selesai: ${message.analyzed ?? 0} kandidat.`,
                    );
                    return;
                }

                if (typeof message.id === 'number') {
                    processed += 1;
                    setProgress((prev) => ({ ...prev, done: processed }));

                    if (!message.skipped) {
                        setRows((prev) =>
                            prev.map((row) =>
                                row.id === message.id
                                    ? {
                                          ...row,
                                          confidence: message.score as number,
                                          recommendation:
                                              message.recommendation as string,
                                          reasoning:
                                              message.reasoning as string,
                                      }
                                    : row,
                            ),
                        );
                    }
                }
            };

            for (;;) {
                const { value, done } = await reader.read();

                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() ?? '';

                for (const line of lines) {
                    const trimmed = line.trim();

                    if (!trimmed) {
                        continue;
                    }

                    try {
                        handle(JSON.parse(trimmed));
                    } catch {
                        // ignore a malformed/partial chunk
                    }
                }
            }
        } catch {
            toast.error('Analisa AI gagal. Periksa Pengaturan AI.');
        } finally {
            setAnalyzing(false);
        }
    };

    const headerActions = (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 10,
                flexWrap: 'wrap',
            }}
        >
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
            <button
                type="button"
                onClick={analyze}
                disabled={analyzing}
                style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: 7,
                    fontSize: 13.5,
                    fontWeight: 600,
                    color: '#fff',
                    padding: '9px 16px',
                    borderRadius: 8,
                    border: 'none',
                    background: '#7C3AED',
                    cursor: analyzing ? 'default' : 'pointer',
                    opacity: analyzing ? 0.85 : 1,
                }}
            >
                <AIcon name="sparkles" size={15} color="#fff" />
                {analyzing
                    ? `Menganalisa ${progress.done}/${progress.total || rows.length}…`
                    : 'Analisa dengan AI'}
            </button>
        </div>
    );

    return (
        <>
            <Head title="AI Intelligence" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="AI Intelligence"
                    subtitle="Peringkat kandidat berdasarkan skor kecocokan AI, dari yang paling sesuai."
                    action={headerActions}
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
                            Belum Ada Kandidat
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                maxWidth: 440,
                                margin: '8px auto 0',
                            }}
                        >
                            Tambahkan kandidat pada lowongan terbuka, lalu
                            jalankan analisa AI untuk menilai kecocokan mereka.
                        </div>
                    </div>
                ) : (
                    <>
                        {analyzing && (
                            <div
                                style={{
                                    ...card,
                                    padding: '12px 16px',
                                    marginBottom: 16,
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 12,
                                }}
                            >
                                <AIcon
                                    name="sparkles"
                                    size={16}
                                    color="#7C3AED"
                                />
                                <div style={{ flex: 1 }}>
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.muted,
                                            marginBottom: 6,
                                        }}
                                    >
                                        AI menganalisa kandidat… {progress.done}
                                        /{progress.total || rows.length}
                                    </div>
                                    <div
                                        style={{
                                            height: 6,
                                            borderRadius: 6,
                                            background: C.line,
                                            overflow: 'hidden',
                                        }}
                                    >
                                        <div
                                            style={{
                                                width: `${progress.total ? Math.round((progress.done / progress.total) * 100) : 0}%`,
                                                height: '100%',
                                                background: '#7C3AED',
                                                borderRadius: 6,
                                                transition: 'width .3s',
                                            }}
                                        />
                                    </div>
                                </div>
                            </div>
                        )}

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
                            data={rows}
                            searchPlaceholder="Cari kandidat atau posisi…"
                            pageSize={10}
                        />
                    </>
                )}
            </div>
        </>
    );
}
