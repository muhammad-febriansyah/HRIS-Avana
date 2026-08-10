import { Head, Link, router, usePage } from '@inertiajs/react';
import { WaIcon } from '@/components/avana-ui/wa-icon';
import { AIcon, C, hexA } from '@/lib/avana';

/* ============================================================
 * The one error page every HTTP failure lands on (403/404/419/
 * 429/500/503), wired up in AppServiceProvider via
 * Inertia::handleExceptionsUsing().
 * ============================================================ */

interface ErrorMeta {
    title: string;
    description: string;
    icon: string;
    accent: string;
    /** Reloading is the fix for an expired session or a rate limit. */
    retry?: boolean;
}

const META: Record<number, ErrorMeta> = {
    403: {
        title: 'Akses ditolak',
        description:
            'Peran Anda belum diberi izin untuk halaman ini. Hubungi admin HR perusahaan Anda bila seharusnya bisa dibuka.',
        icon: 'lock',
        accent: '#DC2626',
    },
    404: {
        title: 'Halaman tidak ditemukan',
        description:
            'Alamat yang Anda buka sudah berpindah atau tidak pernah ada. Periksa kembali tautannya.',
        icon: 'compass',
        accent: '#2F54C9',
    },
    419: {
        title: 'Sesi Anda berakhir',
        description:
            'Halaman ini terbuka terlalu lama sehingga sesi keamanannya kedaluwarsa. Muat ulang lalu coba lagi.',
        icon: 'clock-alert',
        accent: '#D97706',
        retry: true,
    },
    429: {
        title: 'Terlalu banyak permintaan',
        description:
            'Permintaan Anda terlalu cepat berurutan. Tunggu sebentar, lalu coba lagi.',
        icon: 'gauge',
        accent: '#D97706',
        retry: true,
    },
    500: {
        title: 'Terjadi kesalahan di sistem',
        description:
            'Ada yang gagal di sisi kami, bukan pada data Anda. Tim AvanaHR sudah dapat catatannya — silakan coba beberapa saat lagi.',
        icon: 'server-crash',
        accent: '#DC2626',
        retry: true,
    },
    503: {
        title: 'Sedang pemeliharaan',
        description:
            'AvanaHR sedang diperbarui sebentar. Data Anda aman dan layanan segera kembali.',
        icon: 'wrench',
        accent: '#0EA5E9',
        retry: true,
    },
};

const FALLBACK: ErrorMeta = {
    title: 'Terjadi kesalahan',
    description:
        'Permintaan Anda tidak dapat diselesaikan. Coba ulangi, atau hubungi tim AvanaHR bila berlanjut.',
    icon: 'circle-alert',
    accent: '#DC2626',
    retry: true,
};

type SharedProps = {
    auth?: { user?: { name?: string } | null };
    website?: {
        site_name?: string;
        logo_url?: string | null;
        contact?: { phone?: string; whatsapp?: string };
    };
};

export default function ErrorPage({ status }: { status: number }) {
    const { auth, website } = usePage<SharedProps>().props;
    const meta = META[status] ?? FALLBACK;
    const signedIn = Boolean(auth?.user);
    const whatsapp = website?.contact?.whatsapp || website?.contact?.phone || '';
    const waHref = `https://wa.me/${whatsapp.replace(/[^\d]/g, '')}`;

    return (
        <>
            <Head title={`${status} · ${meta.title}`} />
            <div
                style={{
                    minHeight: '100vh',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    padding: '40px 20px',
                    background: `radial-gradient(1200px 520px at 50% -10%, ${hexA(meta.accent, 0.1)}, transparent 70%), ${C.surface}`,
                    fontFamily:
                        "Poppins, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif",
                }}
            >
                <div
                    style={{
                        position: 'relative',
                        width: '100%',
                        maxWidth: 560,
                        textAlign: 'center',
                    }}
                >
                    {/* Watermarked status code behind the card. */}
                    <div
                        aria-hidden
                        style={{
                            position: 'absolute',
                            inset: 0,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: 220,
                            fontWeight: 800,
                            lineHeight: 1,
                            letterSpacing: '-.04em',
                            color: hexA(meta.accent, 0.07),
                            userSelect: 'none',
                            pointerEvents: 'none',
                        }}
                    >
                        {status}
                    </div>

                    <div
                        style={{
                            position: 'relative',
                            background: '#fff',
                            border: `1px solid ${hexA(C.navy, 0.06)}`,
                            borderRadius: 20,
                            padding: '38px 34px 32px',
                            boxShadow: '0 4px 16px rgba(15,26,58,.045)',
                        }}
                    >
                        {website?.logo_url ? (
                            <img
                                src={website.logo_url}
                                alt={website.site_name ?? 'AvanaHR'}
                                style={{
                                    height: 76,
                                    maxWidth: 300,
                                    margin: '0 auto 24px',
                                    display: 'block',
                                    objectFit: 'contain',
                                }}
                            />
                        ) : (
                            <div
                                style={{
                                    fontSize: 17,
                                    fontWeight: 700,
                                    letterSpacing: '.08em',
                                    color: C.primary,
                                    marginBottom: 24,
                                }}
                            >
                                {(website?.site_name ?? 'AVANAHR').toUpperCase()}
                            </div>
                        )}

                        <div
                            style={{
                                width: 64,
                                height: 64,
                                margin: '0 auto 18px',
                                borderRadius: 18,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                background: hexA(meta.accent, 0.1),
                                border: `1px solid ${hexA(meta.accent, 0.22)}`,
                            }}
                        >
                            <AIcon
                                name={meta.icon}
                                size={28}
                                color={meta.accent}
                            />
                        </div>

                        <div
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 7,
                                fontSize: 11.5,
                                fontWeight: 700,
                                letterSpacing: '.06em',
                                color: meta.accent,
                                background: hexA(meta.accent, 0.09),
                                padding: '4px 11px',
                                borderRadius: 999,
                                marginBottom: 12,
                            }}
                        >
                            ERROR {status}
                        </div>

                        <h1
                            style={{
                                fontSize: 23,
                                fontWeight: 700,
                                color: C.navy,
                                margin: 0,
                                letterSpacing: '-.01em',
                            }}
                        >
                            {meta.title}
                        </h1>

                        <p
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                lineHeight: 1.65,
                                margin: '10px auto 0',
                                maxWidth: 430,
                            }}
                        >
                            {meta.description}
                        </p>

                        <div
                            style={{
                                display: 'flex',
                                gap: 10,
                                justifyContent: 'center',
                                flexWrap: 'wrap',
                                marginTop: 26,
                            }}
                        >
                            <Link
                                href={signedIn ? '/dashboard' : '/'}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    height: 44,
                                    padding: '0 20px',
                                    borderRadius: 11,
                                    background: C.primary,
                                    color: '#fff',
                                    fontSize: 14,
                                    fontWeight: 600,
                                    textDecoration: 'none',
                                }}
                            >
                                <AIcon
                                    name={signedIn ? 'layout-dashboard' : 'house'}
                                    size={16}
                                    color="#fff"
                                />
                                {signedIn ? 'Ke Dashboard' : 'Ke Halaman Utama'}
                            </Link>

                            {meta.retry ? (
                                <button
                                    type="button"
                                    onClick={() => router.reload()}
                                    style={secondaryBtn}
                                >
                                    <AIcon
                                        name="refresh-cw"
                                        size={15}
                                        color={C.text}
                                    />
                                    Coba Lagi
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => window.history.back()}
                                    style={secondaryBtn}
                                >
                                    <AIcon
                                        name="arrow-left"
                                        size={15}
                                        color={C.text}
                                    />
                                    Kembali
                                </button>
                            )}

                            {whatsapp !== '' && (
                                <a
                                    href={waHref}
                                    target="_blank"
                                    rel="noreferrer"
                                    style={{
                                        ...secondaryBtn,
                                        border: '1px solid rgba(37,211,102,.4)',
                                        color: '#128C7E',
                                    }}
                                >
                                    <WaIcon size={15} color="#25D366" />
                                    Bantuan
                                </a>
                            )}
                        </div>
                    </div>

                    <div
                        style={{
                            fontSize: 11.5,
                            color: C.faint,
                            marginTop: 16,
                        }}
                    >
                        {website?.site_name ?? 'AvanaHR'} · sebutkan kode{' '}
                        <b style={{ color: C.muted }}>{status}</b> saat menghubungi
                        tim dukungan
                    </div>
                </div>
            </div>
        </>
    );
}

const secondaryBtn: React.CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 8,
    height: 44,
    padding: '0 18px',
    borderRadius: 11,
    border: `1px solid ${C.border}`,
    background: '#fff',
    color: C.text,
    fontSize: 14,
    fontWeight: 600,
    cursor: 'pointer',
    textDecoration: 'none',
};
