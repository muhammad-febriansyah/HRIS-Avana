import { Head, Link } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import { LocationMap } from '@/components/map/location-map';
import { AIcon, C, card } from '@/lib/avana';

interface Attendee {
    id: number;
    name: string;
    employee_number: string | null;
    initials: string;
    avatar_color: string;
}

interface VisitTask {
    id: number;
    title: string;
    is_done: boolean;
    done_at: string | null;
    photo_note: string | null;
    before_photo_url: string | null;
    after_photo_url: string | null;
}

interface VisitDetail {
    id: number;
    employees: Attendee[];
    branch: string | null;
    visit_date: string | null;
    location: string;
    client_name: string | null;
    purpose: string | null;
    notes: string | null;
    photo_urls: string[];
    latitude: number | null;
    longitude: number | null;
    status: string;
    created_at: string | null;
    tasks: VisitTask[];
    task_progress: { done: number; total: number };
}

const STATUS: Record<string, { label: string; color: string; bg: string }> = {
    submitted: { label: 'Terkirim', color: C.green, bg: 'rgba(22,163,74,.1)' },
    draft: { label: 'Draft', color: C.amber, bg: 'rgba(217,119,6,.1)' },
};

const sectionTitle: CSSProperties = {
    fontSize: 15,
    fontWeight: 600,
    color: C.navy,
    marginBottom: 14,
};

function InfoRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div style={{ display: 'flex', gap: 14, padding: '9px 0', borderBottom: `1px solid ${C.line}` }}>
            <span style={{ fontSize: 13, color: C.muted, width: 110, flex: 'none' }}>{label}</span>
            <span style={{ fontSize: 13.5, color: C.text, fontWeight: 500, flex: 1, minWidth: 0, wordBreak: 'break-word' }}>
                {value || <span style={{ color: C.faint, fontWeight: 400 }}>—</span>}
            </span>
        </div>
    );
}

function Thumb({ url, label }: { url: string; label: string }) {
    return (
        <a href={url} target="_blank" rel="noreferrer" style={{ flex: 1, minWidth: 0, textDecoration: 'none' }}>
            <div style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: '.05em', color: C.faint, marginBottom: 4 }}>
                {label}
            </div>
            <img
                src={url}
                alt={label}
                style={{ width: '100%', height: 96, objectFit: 'cover', borderRadius: 8, border: `1px solid ${C.border}` }}
            />
        </a>
    );
}

export default function VisitingShow({ visit }: { visit: VisitDetail }) {
    const st = STATUS[visit.status] ?? { label: visit.status, color: C.muted, bg: 'rgba(107,114,128,.12)' };
    const progress = visit.task_progress;
    const pct = progress.total > 0 ? Math.round((progress.done / progress.total) * 100) : 0;
    const title = visit.client_name || visit.location;

    return (
        <>
            <Head title={`Kunjungan · ${title}`} />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 10 }}>
                    <Link href="/avana/visiting" style={{ color: C.faint, textDecoration: 'none' }}>
                        Visiting Pekerjaan
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{title}</span>
                </div>

                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 16, flexWrap: 'wrap', marginBottom: 22 }}>
                    <div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>{title}</h1>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 6, fontSize: 13.5, color: C.muted, flexWrap: 'wrap' }}>
                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                <AIcon name="map-pin" size={14} color={C.faint} />
                                {visit.location}
                            </span>
                            <span style={{ color: C.border }}>·</span>
                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                <AIcon name="calendar" size={14} color={C.faint} />
                                {visit.visit_date ?? '—'}
                            </span>
                        </div>
                    </div>
                    <span style={{ padding: '5px 13px', borderRadius: 100, fontSize: 12.5, fontWeight: 600, color: st.color, background: st.bg }}>
                        {st.label}
                    </span>
                </div>

                <div className="avn-2col" style={{ display: 'grid', gridTemplateColumns: '1fr 1.4fr', gap: 18, alignItems: 'start' }}>
                    {/* Left column */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                        <div style={{ ...card, padding: '20px 22px' }}>
                            <div style={sectionTitle}>Ringkasan Kunjungan</div>
                            <InfoRow label="Cabang" value={visit.branch} />
                            <InfoRow label="Tanggal" value={visit.visit_date} />
                            <InfoRow label="Lokasi" value={visit.location} />
                            <InfoRow label="Klien" value={visit.client_name} />
                            <InfoRow label="Tujuan" value={visit.purpose} />
                            <InfoRow label="Catatan" value={visit.notes} />
                            <InfoRow label="Dibuat" value={visit.created_at} />
                        </div>

                        <div style={{ ...card, padding: '20px 22px' }}>
                            <div style={sectionTitle}>Peserta ({visit.employees.length})</div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                                {visit.employees.map((e) => (
                                    <div key={e.id} style={{ display: 'flex', alignItems: 'center', gap: 11 }}>
                                        <div style={{ width: 36, height: 36, borderRadius: 9, flex: 'none', display: 'flex', alignItems: 'center', justifyContent: 'center', background: e.avatar_color, color: '#fff', fontSize: 12.5, fontWeight: 700 }}>
                                            {e.initials}
                                        </div>
                                        <div style={{ minWidth: 0 }}>
                                            <div style={{ fontSize: 13.5, fontWeight: 600, color: C.navy }}>{e.name}</div>
                                            <div style={{ fontSize: 12, color: C.faint }}>{e.employee_number ?? '—'}</div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div style={{ ...card, padding: 14 }}>
                            <div style={{ ...sectionTitle, marginBottom: 12, paddingLeft: 8 }}>Titik Lokasi</div>
                            {visit.latitude !== null && visit.longitude !== null ? (
                                <>
                                    <LocationMap
                                        points={[{ lat: visit.latitude, lng: visit.longitude, label: visit.location }]}
                                        height={220}
                                        zoom={15}
                                    />
                                    <div style={{ fontSize: 11.5, color: C.faint, marginTop: 8, paddingLeft: 8 }}>
                                        {visit.latitude.toFixed(5)}, {visit.longitude.toFixed(5)}
                                    </div>
                                </>
                            ) : (
                                <div style={{ padding: '28px 8px', textAlign: 'center', color: C.faint, fontSize: 13 }}>
                                    Tidak ada titik GPS.
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Right column */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                        <div style={{ ...card, padding: '20px 22px' }}>
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
                                <div style={{ ...sectionTitle, marginBottom: 0 }}>Tasklist Pekerjaan</div>
                                <span style={{ fontSize: 12, fontWeight: 700, color: C.primary, background: 'rgba(47,84,201,.1)', padding: '4px 11px', borderRadius: 100 }}>
                                    {progress.done}/{progress.total} Selesai
                                </span>
                            </div>
                            <div style={{ height: 8, borderRadius: 6, background: C.line, overflow: 'hidden', marginBottom: 18 }}>
                                <div style={{ width: `${pct}%`, height: '100%', borderRadius: 6, background: C.green, transition: 'width .3s' }} />
                            </div>

                            {visit.tasks.length === 0 ? (
                                <div style={{ padding: '24px 0', textAlign: 'center', color: C.faint, fontSize: 13 }}>Tidak ada tugas.</div>
                            ) : (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                                    {visit.tasks.map((t) => (
                                        <div key={t.id} style={{ padding: 14, borderRadius: 12, background: C.surface }}>
                                            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 10 }}>
                                                <AIcon
                                                    name={t.is_done ? 'circle-check-big' : 'circle'}
                                                    size={18}
                                                    color={t.is_done ? C.green : C.border}
                                                />
                                                <div style={{ flex: 1, minWidth: 0 }}>
                                                    <div style={{ fontSize: 13.5, fontWeight: 600, color: t.is_done ? C.faint : C.text, textDecoration: t.is_done ? 'line-through' : 'none' }}>
                                                        {t.title}
                                                    </div>
                                                    {t.is_done && t.done_at && (
                                                        <div style={{ fontSize: 11.5, color: C.faint, marginTop: 2 }}>Selesai {t.done_at}</div>
                                                    )}
                                                </div>
                                            </div>

                                            {(t.before_photo_url || t.after_photo_url) && (
                                                <div style={{ display: 'flex', gap: 10, marginTop: 12 }}>
                                                    {t.before_photo_url && <Thumb url={t.before_photo_url} label="BEFORE" />}
                                                    {t.after_photo_url && <Thumb url={t.after_photo_url} label="AFTER" />}
                                                </div>
                                            )}
                                            {t.photo_note && (
                                                <div style={{ fontSize: 12, color: C.muted, marginTop: 10, fontStyle: 'italic' }}>“{t.photo_note}”</div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div style={{ ...card, padding: '20px 22px' }}>
                            <div style={sectionTitle}>Galeri Foto ({visit.photo_urls.length})</div>
                            {visit.photo_urls.length === 0 ? (
                                <div style={{ padding: '24px 0', textAlign: 'center', color: C.faint, fontSize: 13 }}>Tidak ada foto kunjungan.</div>
                            ) : (
                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(120px, 1fr))', gap: 10 }}>
                                    {visit.photo_urls.map((url, i) => (
                                        <a key={url} href={url} target="_blank" rel="noreferrer" title={`Foto ${i + 1}`}>
                                            <img src={url} alt={`Foto ${i + 1}`} style={{ width: '100%', height: 110, objectFit: 'cover', borderRadius: 10, border: `1px solid ${C.border}` }} />
                                        </a>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
