import { Head, router } from '@inertiajs/react';
import { AIcon, C } from '@/lib/avana';

interface Breakdown {
    key: string;
    label: string;
    score: number;
    count: number;
}

interface EmployeeMood {
    id: number;
    name: string;
    employee_number: string | null;
    mood: string;
    mood_label: string;
    score: number;
    time: string | null;
}

interface TrendPoint {
    date: string;
    label: string;
    index: number | null;
    count: number;
}

interface MoodProps {
    date: string;
    summary: {
        index: number | null;
        total: number;
        breakdown: Breakdown[];
    };
    employees: EmployeeMood[];
    trend: TrendPoint[];
}

const EMOJI: Record<string, string> = {
    sangat_baik: '😄',
    baik: '🙂',
    biasa: '😐',
    kurang: '🙁',
    buruk: '😣',
};

function moodColor(score: number): string {
    if (score >= 4) return C.green;
    if (score === 3) return C.amber;
    return C.red;
}

function indexLabel(index: number | null): string {
    if (index === null) return 'Belum ada data';
    if (index >= 80) return 'Tim dalam kondisi prima';
    if (index >= 60) return 'Suasana tim positif';
    if (index >= 40) return 'Perlu perhatian';
    return 'Butuh tindak lanjut';
}

const card: React.CSSProperties = {
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 12,
    boxShadow: '0 1px 2px rgba(15,23,42,.04)',
};

export default function AvanaMood({ date, summary, employees, trend }: MoodProps) {
    const setDate = (value: string) => {
        router.get(
            window.location.pathname,
            { date: value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const maxCount = Math.max(1, ...summary.breakdown.map((b) => b.count));
    const idxColor = summary.index === null ? C.faint : summary.index >= 60 ? C.green : summary.index >= 40 ? C.amber : C.red;

    return (
        <>
            <Head title="Mood Karyawan" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ marginBottom: 22 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                        <span>Kehadiran</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Mood Karyawan</span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', flexWrap: 'wrap', gap: 12 }}>
                        <div>
                            <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Mood Karyawan</h1>
                            <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                                Rekap check-in perasaan harian karyawan dari aplikasi — anonim antar rekan, hanya untuk HR.
                            </div>
                        </div>
                        <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: C.muted }}>
                            <AIcon name="calendar" size={16} color={C.faint} />
                            <input
                                type="date"
                                value={date}
                                onChange={(e) => setDate(e.target.value)}
                                style={{
                                    height: 38,
                                    padding: '0 12px',
                                    background: '#fff',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 8,
                                    color: C.text,
                                    fontSize: 13.5,
                                }}
                            />
                        </label>
                    </div>
                </div>

                {/* Top: index + total + trend */}
                <div style={{ display: 'grid', gridTemplateColumns: '260px 1fr', gap: 16, marginBottom: 16 }}>
                    <div style={{ ...card, padding: 20, display: 'flex', flexDirection: 'column', gap: 6 }}>
                        <div style={{ fontSize: 13, color: C.muted, fontWeight: 500 }}>Mood Index Tim</div>
                        <div style={{ display: 'flex', alignItems: 'baseline', gap: 6 }}>
                            <div style={{ fontSize: 44, fontWeight: 700, color: idxColor, lineHeight: 1 }}>
                                {summary.index ?? '—'}
                            </div>
                            <div style={{ fontSize: 15, color: C.faint, fontWeight: 600 }}>/100</div>
                        </div>
                        <div style={{ fontSize: 13, color: idxColor, fontWeight: 600 }}>{indexLabel(summary.index)}</div>
                        <div style={{ marginTop: 6, fontSize: 12.5, color: C.muted, display: 'flex', alignItems: 'center', gap: 6 }}>
                            <AIcon name="users" size={14} color={C.faint} />
                            {summary.total} karyawan check-in hari ini
                        </div>
                    </div>

                    {/* Trend */}
                    <div style={{ ...card, padding: 20 }}>
                        <div style={{ fontSize: 13, color: C.muted, fontWeight: 500, marginBottom: 14 }}>Tren 7 Hari</div>
                        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 12, height: 90 }}>
                            {trend.map((t) => (
                                <div key={t.date} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6 }}>
                                    <div style={{ fontSize: 11, color: C.faint, fontWeight: 600 }}>{t.index ?? ''}</div>
                                    <div style={{ width: '100%', maxWidth: 34, height: 60, background: C.line, borderRadius: 6, display: 'flex', alignItems: 'flex-end', overflow: 'hidden' }}>
                                        <div
                                            style={{
                                                width: '100%',
                                                height: `${t.index ?? 0}%`,
                                                background: t.index === null ? C.border : t.index >= 60 ? C.green : t.index >= 40 ? C.amber : C.red,
                                                borderRadius: 6,
                                                transition: 'height .2s',
                                            }}
                                        />
                                    </div>
                                    <div style={{ fontSize: 11, color: C.muted }}>{t.label}</div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Breakdown */}
                <div style={{ ...card, padding: 20, marginBottom: 16 }}>
                    <div style={{ fontSize: 13, color: C.muted, fontWeight: 500, marginBottom: 16 }}>Sebaran Hari Ini</div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 14 }}>
                        {summary.breakdown.map((b) => {
                            const color = moodColor(b.score);
                            return (
                                <div key={b.key} style={{ textAlign: 'center' }}>
                                    <div style={{ fontSize: 28 }}>{EMOJI[b.key]}</div>
                                    <div style={{ fontSize: 22, fontWeight: 700, color: C.navy, marginTop: 4 }}>{b.count}</div>
                                    <div style={{ fontSize: 12, color: C.muted, marginBottom: 8 }}>{b.label}</div>
                                    <div style={{ height: 6, background: C.line, borderRadius: 4, overflow: 'hidden' }}>
                                        <div style={{ width: `${(b.count / maxCount) * 100}%`, height: '100%', background: color, borderRadius: 4 }} />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Employee table */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ padding: '16px 18px', borderBottom: `1px solid ${C.border}`, fontSize: 13, color: C.muted, fontWeight: 500 }}>
                        Detail per Karyawan
                    </div>
                    {employees.length === 0 ? (
                        <div style={{ padding: '48px 18px', textAlign: 'center', color: C.faint, fontSize: 14 }}>
                            Belum ada check-in pada tanggal ini.
                        </div>
                    ) : (
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead>
                                <tr style={{ background: C.surface }}>
                                    <th style={th}>Karyawan</th>
                                    <th style={th}>NIK</th>
                                    <th style={th}>Perasaan</th>
                                    <th style={{ ...th, textAlign: 'right' }}>Jam</th>
                                </tr>
                            </thead>
                            <tbody>
                                {employees.map((e) => {
                                    const color = moodColor(e.score);
                                    return (
                                        <tr key={e.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                            <td style={{ ...td, fontWeight: 600, color: C.navy }}>{e.name}</td>
                                            <td style={{ ...td, color: C.muted }}>{e.employee_number ?? '—'}</td>
                                            <td style={td}>
                                                <span
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: 6,
                                                        padding: '3px 10px',
                                                        borderRadius: 100,
                                                        background: `${color}1f`,
                                                        color,
                                                        fontSize: 12.5,
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    <span>{EMOJI[e.mood]}</span>
                                                    {e.mood_label}
                                                </span>
                                            </td>
                                            <td style={{ ...td, textAlign: 'right', color: C.muted }}>{e.time ?? '—'}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </>
    );
}

const th: React.CSSProperties = {
    textAlign: 'left',
    padding: '11px 18px',
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    textTransform: 'uppercase',
    letterSpacing: '.03em',
};

const td: React.CSSProperties = {
    padding: '13px 18px',
    fontSize: 13.5,
    color: C.text,
};
