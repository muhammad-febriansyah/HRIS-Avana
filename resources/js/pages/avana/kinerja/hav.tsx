import { Head, Link } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import PerformanceController from '@/actions/App/Http/Controllers/Avana/PerformanceController';
import { AIcon, C, card, thCell } from '@/lib/avana';

interface HavRow {
    employee_id: number;
    employee: string;
    department: string | null;
    position: string | null;
    score: number;
    tenure_years: number;
    hav_index: number;
    category: string;
}

interface HavProps {
    rows: HavRow[];
    kpis: { rated: number; avg_hav: number; stars: number; at_risk: number };
}

const CATEGORY_COLORS: Record<string, [string, string]> = {
    Bintang: [C.green, 'rgba(22,163,74,.1)'],
    'Potensial Tinggi': [C.sky, 'rgba(110,155,230,.15)'],
    Inti: [C.primary, 'rgba(47,84,201,.1)'],
    Berkembang: [C.amber, 'rgba(217,119,6,.1)'],
    'Perlu Pengembangan': [C.red, 'rgba(220,38,38,.1)'],
};

const tdCell: CSSProperties = { padding: '13px 16px', fontSize: 13, color: C.text, borderTop: `1px solid ${C.line}` };

function CategoryPill({ category }: { category: string }) {
    const [color, bg] = CATEGORY_COLORS[category] ?? [C.muted, 'rgba(107,114,128,.12)'];
    return <span style={{ padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color, background: bg }}>{category}</span>;
}

function Kpi({ icon, label, value, accent }: { icon: string; label: string; value: string | number; accent: string }) {
    return (
        <div style={{ ...card, padding: 18, flex: 1, minWidth: 0 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                <div style={{ width: 34, height: 34, borderRadius: 9, background: `${accent}1a`, color: accent, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <AIcon name={icon} size={16} color={accent} />
                </div>
                <div style={{ fontSize: 12.5, color: C.muted }}>{label}</div>
            </div>
            <div style={{ fontSize: 22, fontWeight: 700, color: C.navy }}>{value}</div>
        </div>
    );
}

export default function Hav({ rows, kpis }: HavProps) {
    return (
        <>
            <Head title="Human Asset Value" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 14 }}>
                    <Link href={PerformanceController.index()} style={{ color: C.faint, textDecoration: 'none' }}>Kinerja</Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Human Asset Value</span>
                </div>
                <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Human Asset Value (HAV)</h1>
                <p style={{ fontSize: 13.5, color: C.muted, margin: '6px 0 22px' }}>
                    Nilai aset SDM dari skor kinerja terkini × faktor masa kerja. Indeks tinggi = aset bernilai &amp; teruji.
                </p>

                <div style={{ display: 'flex', gap: 14, marginBottom: 24, flexWrap: 'wrap' }}>
                    <Kpi icon="users" label="Karyawan Dinilai" value={kpis.rated} accent={C.primary} />
                    <Kpi icon="trending-up" label="Rata-rata HAV" value={kpis.avg_hav} accent={C.sky} />
                    <Kpi icon="star" label="Bintang" value={kpis.stars} accent={C.green} />
                    <Kpi icon="triangle-alert" label="Perlu Pengembangan" value={kpis.at_risk} accent={C.red} />
                </div>

                <div style={{ ...card, overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ background: C.surface }}>
                                <th style={thCell}>Karyawan</th>
                                <th style={thCell}>Departemen</th>
                                <th style={{ ...thCell, textAlign: 'right' }}>Skor</th>
                                <th style={{ ...thCell, textAlign: 'right' }}>Masa Kerja</th>
                                <th style={{ ...thCell, textAlign: 'right' }}>Indeks HAV</th>
                                <th style={thCell}>Kategori</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr><td colSpan={6} style={{ ...tdCell, textAlign: 'center', color: C.faint, padding: '40px 0' }}>Belum ada karyawan dengan penilaian kinerja.</td></tr>
                            ) : (
                                rows.map((row, index) => (
                                    <tr key={row.employee_id}>
                                        <td style={{ ...tdCell, fontWeight: 600, color: C.navy }}>
                                            <span style={{ color: C.faint, marginRight: 8 }}>#{index + 1}</span>
                                            {row.employee}
                                            {row.position ? <div style={{ fontSize: 11.5, color: C.faint, fontWeight: 400 }}>{row.position}</div> : null}
                                        </td>
                                        <td style={tdCell}>{row.department ?? '—'}</td>
                                        <td style={{ ...tdCell, textAlign: 'right' }}>{row.score}</td>
                                        <td style={{ ...tdCell, textAlign: 'right' }}>{row.tenure_years} thn</td>
                                        <td style={{ ...tdCell, textAlign: 'right', fontWeight: 700, color: C.navy }}>{row.hav_index}</td>
                                        <td style={tdCell}><CategoryPill category={row.category} /></td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
