import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import { LocationMap } from '@/components/map/location-map';
import { AIcon, C, card } from '@/lib/avana';

interface GalleryImage {
    url: string;
    label: string;
}

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
        <div
            style={{
                display: 'flex',
                gap: 14,
                padding: '9px 0',
                borderBottom: `1px solid ${C.line}`,
            }}
        >
            <span
                style={{
                    fontSize: 13,
                    color: C.muted,
                    width: 110,
                    flex: 'none',
                }}
            >
                {label}
            </span>
            <span
                style={{
                    fontSize: 13.5,
                    color: C.text,
                    fontWeight: 500,
                    flex: 1,
                    minWidth: 0,
                    wordBreak: 'break-word',
                }}
            >
                {value || (
                    <span style={{ color: C.faint, fontWeight: 400 }}>—</span>
                )}
            </span>
        </div>
    );
}

function Thumb({
    url,
    label,
    onOpen,
}: {
    url: string;
    label: string;
    onOpen: (url: string) => void;
}) {
    return (
        <button
            type="button"
            onClick={() => onOpen(url)}
            style={{
                flex: 1,
                minWidth: 0,
                textAlign: 'left',
                padding: 0,
                border: 'none',
                background: 'none',
                cursor: 'zoom-in',
            }}
        >
            <div
                style={{
                    fontSize: 10.5,
                    fontWeight: 700,
                    letterSpacing: '.05em',
                    color: C.faint,
                    marginBottom: 4,
                }}
            >
                {label}
            </div>
            <div
                style={{
                    position: 'relative',
                    width: '100%',
                    height: 96,
                    borderRadius: 8,
                    overflow: 'hidden',
                    border: `1px solid ${C.border}`,
                }}
            >
                <img
                    src={url}
                    alt={label}
                    style={{
                        width: '100%',
                        height: '100%',
                        objectFit: 'cover',
                    }}
                />
                <span
                    style={{
                        position: 'absolute',
                        right: 6,
                        bottom: 6,
                        width: 22,
                        height: 22,
                        borderRadius: 6,
                        background: 'rgba(17,24,39,.62)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <AIcon name="maximize-2" size={12} color="#fff" />
                </span>
            </div>
        </button>
    );
}

/**
 * Full-screen image viewer so before/after and gallery photos can be seen
 * uncropped (the thumbnails are cover-cropped). Supports prev/next across all
 * photos on the page plus Esc / arrow-key navigation.
 */
function Lightbox({
    images,
    index,
    onIndex,
    onClose,
}: {
    images: GalleryImage[];
    index: number;
    onIndex: (i: number) => void;
    onClose: () => void;
}) {
    const count = images.length;
    const current = images[index];

    const go = useCallback(
        (delta: number) => onIndex((index + delta + count) % count),
        [index, count, onIndex],
    );

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                onClose();
            } else if (e.key === 'ArrowRight') {
                go(1);
            } else if (e.key === 'ArrowLeft') {
                go(-1);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [go, onClose]);

    if (!current) {
        return null;
    }

    const navBtn: CSSProperties = {
        position: 'absolute',
        top: '50%',
        transform: 'translateY(-50%)',
        width: 44,
        height: 44,
        borderRadius: '50%',
        border: 'none',
        background: 'rgba(255,255,255,.14)',
        color: '#fff',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        cursor: 'pointer',
    };

    return (
        <div
            onClick={onClose}
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 60,
                background: 'rgba(9,11,20,.86)',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 24,
            }}
        >
            <div
                style={{
                    position: 'absolute',
                    top: 18,
                    left: 22,
                    color: 'rgba(255,255,255,.9)',
                    fontSize: 13.5,
                    fontWeight: 600,
                }}
            >
                {current.label}
                {count > 1 && (
                    <span
                        style={{
                            color: 'rgba(255,255,255,.5)',
                            marginLeft: 8,
                            fontWeight: 400,
                        }}
                    >
                        {index + 1} / {count}
                    </span>
                )}
            </div>
            <button
                type="button"
                onClick={onClose}
                style={{
                    position: 'absolute',
                    top: 16,
                    right: 18,
                    width: 38,
                    height: 38,
                    borderRadius: '50%',
                    border: 'none',
                    background: 'rgba(255,255,255,.14)',
                    color: '#fff',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    cursor: 'pointer',
                }}
            >
                <AIcon name="x" size={19} color="#fff" />
            </button>

            <img
                src={current.url}
                alt={current.label}
                onClick={(e) => e.stopPropagation()}
                style={{
                    maxWidth: '90vw',
                    maxHeight: '82vh',
                    objectFit: 'contain',
                    borderRadius: 10,
                    cursor: 'default',
                }}
            />

            {count > 1 && (
                <>
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            go(-1);
                        }}
                        style={{ ...navBtn, left: 20 }}
                    >
                        <AIcon name="chevron-left" size={22} color="#fff" />
                    </button>
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            go(1);
                        }}
                        style={{ ...navBtn, right: 20 }}
                    >
                        <AIcon name="chevron-right" size={22} color="#fff" />
                    </button>
                </>
            )}
        </div>
    );
}

export default function VisitingShow({ visit }: { visit: VisitDetail }) {
    const st = STATUS[visit.status] ?? {
        label: visit.status,
        color: C.muted,
        bg: 'rgba(107,114,128,.12)',
    };
    const progress = visit.task_progress;
    const pct =
        progress.total > 0
            ? Math.round((progress.done / progress.total) * 100)
            : 0;
    const title = visit.client_name || visit.location;

    // Flat list of every task before/after photo so the lightbox can page
    // through them in one continuous sequence.
    const images = useMemo<GalleryImage[]>(() => {
        const list: GalleryImage[] = [];
        visit.tasks.forEach((t) => {
            if (t.before_photo_url) {
                list.push({
                    url: t.before_photo_url,
                    label: `${t.title} · Before`,
                });
            }

            if (t.after_photo_url) {
                list.push({
                    url: t.after_photo_url,
                    label: `${t.title} · After`,
                });
            }
        });

        return list;
    }, [visit]);

    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);
    const openLightbox = useCallback(
        (url: string) => {
            const i = images.findIndex((im) => im.url === url);

            if (i !== -1) {
                setLightboxIndex(i);
            }
        },
        [images],
    );

    return (
        <>
            <Head title={`Kunjungan · ${title}`} />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 10,
                    }}
                >
                    <Link
                        href="/avana/visiting"
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        Visiting Pekerjaan
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{title}</span>
                </div>

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        gap: 16,
                        flexWrap: 'wrap',
                        marginBottom: 22,
                    }}
                >
                    <div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: 0,
                                letterSpacing: '-.01em',
                            }}
                        >
                            {title}
                        </h1>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                marginTop: 6,
                                fontSize: 13.5,
                                color: C.muted,
                                flexWrap: 'wrap',
                            }}
                        >
                            <span
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 5,
                                }}
                            >
                                <AIcon
                                    name="map-pin"
                                    size={14}
                                    color={C.faint}
                                />
                                {visit.location}
                            </span>
                            <span style={{ color: C.border }}>·</span>
                            <span
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 5,
                                }}
                            >
                                <AIcon
                                    name="calendar"
                                    size={14}
                                    color={C.faint}
                                />
                                {visit.visit_date ?? '—'}
                            </span>
                        </div>
                    </div>
                    <span
                        style={{
                            padding: '5px 13px',
                            borderRadius: 100,
                            fontSize: 12.5,
                            fontWeight: 600,
                            color: st.color,
                            background: st.bg,
                        }}
                    >
                        {st.label}
                    </span>
                </div>

                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1.4fr',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    {/* Left column */}
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 18,
                        }}
                    >
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
                            <div style={sectionTitle}>
                                Peserta ({visit.employees.length})
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 12,
                                }}
                            >
                                {visit.employees.map((e) => (
                                    <div
                                        key={e.id}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 11,
                                        }}
                                    >
                                        <div
                                            style={{
                                                width: 36,
                                                height: 36,
                                                borderRadius: 9,
                                                flex: 'none',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                background: e.avatar_color,
                                                color: '#fff',
                                                fontSize: 12.5,
                                                fontWeight: 700,
                                            }}
                                        >
                                            {e.initials}
                                        </div>
                                        <div style={{ minWidth: 0 }}>
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {e.name}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 12,
                                                    color: C.faint,
                                                }}
                                            >
                                                {e.employee_number ?? '—'}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div style={{ ...card, padding: 14 }}>
                            <div
                                style={{
                                    ...sectionTitle,
                                    marginBottom: 12,
                                    paddingLeft: 8,
                                }}
                            >
                                Titik Lokasi
                            </div>
                            {visit.latitude !== null &&
                            visit.longitude !== null ? (
                                <>
                                    <LocationMap
                                        points={[
                                            {
                                                lat: visit.latitude,
                                                lng: visit.longitude,
                                                label: visit.location,
                                            },
                                        ]}
                                        height={220}
                                        zoom={15}
                                    />
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: C.faint,
                                            marginTop: 8,
                                            paddingLeft: 8,
                                        }}
                                    >
                                        {visit.latitude.toFixed(5)},{' '}
                                        {visit.longitude.toFixed(5)}
                                    </div>
                                </>
                            ) : (
                                <div
                                    style={{
                                        padding: '28px 8px',
                                        textAlign: 'center',
                                        color: C.faint,
                                        fontSize: 13,
                                    }}
                                >
                                    Tidak ada titik GPS.
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Right column */}
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 18,
                        }}
                    >
                        <div style={{ ...card, padding: '20px 22px' }}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    marginBottom: 12,
                                }}
                            >
                                <div
                                    style={{ ...sectionTitle, marginBottom: 0 }}
                                >
                                    Tasklist Pekerjaan
                                </div>
                                <span
                                    style={{
                                        fontSize: 12,
                                        fontWeight: 700,
                                        color: C.primary,
                                        background: 'rgba(47,84,201,.1)',
                                        padding: '4px 11px',
                                        borderRadius: 100,
                                    }}
                                >
                                    {progress.done}/{progress.total} Selesai
                                </span>
                            </div>
                            <div
                                style={{
                                    height: 8,
                                    borderRadius: 6,
                                    background: C.line,
                                    overflow: 'hidden',
                                    marginBottom: 18,
                                }}
                            >
                                <div
                                    style={{
                                        width: `${pct}%`,
                                        height: '100%',
                                        borderRadius: 6,
                                        background: C.green,
                                        transition: 'width .3s',
                                    }}
                                />
                            </div>

                            {visit.tasks.length === 0 ? (
                                <div
                                    style={{
                                        padding: '24px 0',
                                        textAlign: 'center',
                                        color: C.faint,
                                        fontSize: 13,
                                    }}
                                >
                                    Tidak ada tugas.
                                </div>
                            ) : (
                                <div
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 14,
                                    }}
                                >
                                    {visit.tasks.map((t) => (
                                        <div
                                            key={t.id}
                                            style={{
                                                padding: 14,
                                                borderRadius: 12,
                                                background: C.surface,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'flex-start',
                                                    gap: 10,
                                                }}
                                            >
                                                <AIcon
                                                    name={
                                                        t.is_done
                                                            ? 'circle-check-big'
                                                            : 'circle'
                                                    }
                                                    size={18}
                                                    color={
                                                        t.is_done
                                                            ? C.green
                                                            : C.border
                                                    }
                                                />
                                                <div
                                                    style={{
                                                        flex: 1,
                                                        minWidth: 0,
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            fontSize: 13.5,
                                                            fontWeight: 600,
                                                            color: t.is_done
                                                                ? C.faint
                                                                : C.text,
                                                            textDecoration:
                                                                t.is_done
                                                                    ? 'line-through'
                                                                    : 'none',
                                                        }}
                                                    >
                                                        {t.title}
                                                    </div>
                                                    {t.is_done && t.done_at && (
                                                        <div
                                                            style={{
                                                                fontSize: 11.5,
                                                                color: C.faint,
                                                                marginTop: 2,
                                                            }}
                                                        >
                                                            Selesai {t.done_at}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            {(t.before_photo_url ||
                                                t.after_photo_url) && (
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        gap: 10,
                                                        marginTop: 12,
                                                    }}
                                                >
                                                    {t.before_photo_url && (
                                                        <Thumb
                                                            url={
                                                                t.before_photo_url
                                                            }
                                                            label="BEFORE"
                                                            onOpen={
                                                                openLightbox
                                                            }
                                                        />
                                                    )}
                                                    {t.after_photo_url && (
                                                        <Thumb
                                                            url={
                                                                t.after_photo_url
                                                            }
                                                            label="AFTER"
                                                            onOpen={
                                                                openLightbox
                                                            }
                                                        />
                                                    )}
                                                </div>
                                            )}
                                            {t.photo_note && (
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        color: C.muted,
                                                        marginTop: 10,
                                                        fontStyle: 'italic',
                                                    }}
                                                >
                                                    “{t.photo_note}”
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {lightboxIndex !== null && (
                <Lightbox
                    images={images}
                    index={lightboxIndex}
                    onIndex={setLightboxIndex}
                    onClose={() => setLightboxIndex(null)}
                />
            )}
        </>
    );
}
