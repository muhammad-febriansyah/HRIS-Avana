import { Head, usePage } from '@inertiajs/react';
import { BadgeCheck, MapPin, Sparkles } from 'lucide-react';
import type { ReactNode } from 'react';
import { C } from '@/lib/avana';

/** Shared glass-card look for the floating product artifacts in the hero. */
const glassCard = {
    background: 'rgba(255,255,255,.08)',
    border: '1px solid rgba(255,255,255,.18)',
    borderRadius: 14,
    backdropFilter: 'blur(14px)',
    WebkitBackdropFilter: 'blur(14px)',
    boxShadow: '0 18px 40px rgba(5,10,30,.35)',
    color: '#fff',
} as const;

/**
 * AvanaHR-branded authentication shell: form on the left, navy gradient
 * marketing hero on the right (hidden on small screens). Wraps every
 * `auth/*` page (login, register, password reset, email verification).
 */
export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: ReactNode;
}) {
    const { website } = usePage().props;

    return (
        <div
            style={{
                minHeight: '100vh',
                display: 'flex',
                background: '#fff',
                fontFamily: "'Poppins',system-ui,sans-serif",
                color: C.text,
            }}
        >
            <Head>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link
                    rel="preconnect"
                    href="https://fonts.gstatic.com"
                    crossOrigin=""
                />
                <link
                    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
                    rel="stylesheet"
                />
            </Head>

            <style>{`
                @keyframes avn-auth-rise {
                    from { opacity: 0; transform: translateY(14px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                @keyframes avn-auth-drift {
                    0%, 100% { transform: translateY(0); }
                    50% { transform: translateY(-10px); }
                }
                .avn-auth-form { animation: avn-auth-rise .45s ease-out both; }
                .avn-auth-float { animation: avn-auth-drift 7s ease-in-out infinite; }
                .avn-auth-float2 { animation: avn-auth-drift 9s ease-in-out 1.2s infinite; }
                @media (prefers-reduced-motion: reduce) {
                    .avn-auth-form, .avn-auth-float, .avn-auth-float2 { animation: none; }
                }
            `}</style>

            {/* LEFT — form */}
            <div
                style={{
                    flex: 1,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    padding: 40,
                }}
            >
                <div
                    className="avn-auth-form"
                    style={{ width: '100%', maxWidth: 400 }}
                >
                    <img
                        src={website.logo_url ?? '/avana/logo-full.png'}
                        alt={website.site_name ?? 'AvanaHR'}
                        style={{ height: 46, marginBottom: 40 }}
                        onError={(event) => {
                            // A tenant logo that 404s (moved storage, broken
                            // upload) must not greet visitors with alt text.
                            const img = event.currentTarget;
                            if (!img.src.endsWith('/avana/logo-full.png')) {
                                img.src = '/avana/logo-full.png';
                            }
                        }}
                    />
                    {title && (
                        <div
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                letterSpacing: '-.01em',
                            }}
                        >
                            {title}
                        </div>
                    )}
                    {description && (
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 6,
                                marginBottom: 30,
                            }}
                        >
                            {description}
                        </div>
                    )}

                    {children}

                    <div
                        style={{
                            textAlign: 'center',
                            fontSize: 12.5,
                            color: C.faint,
                            marginTop: 40,
                        }}
                    >
                        © 2026 AvanaHR · Advancing People, Empowering Growth
                    </div>
                </div>
            </div>

            {/* RIGHT — hero */}
            <div
                className="login-hero"
                style={{
                    flex: 1,
                    background:
                        'linear-gradient(150deg,#0E1A3A 0%,#1c3175 55%,#2F54C9 100%)',
                    position: 'relative',
                    overflow: 'hidden',
                    display: 'flex',
                    flexDirection: 'column',
                    justifyContent: 'center',
                    padding: 64,
                }}
            >
                <div
                    style={{
                        position: 'absolute',
                        top: -80,
                        right: -60,
                        width: 320,
                        height: 320,
                        borderRadius: '50%',
                        background: 'rgba(110,155,230,.18)',
                    }}
                />
                <div
                    style={{
                        position: 'absolute',
                        bottom: -100,
                        left: -80,
                        width: 360,
                        height: 360,
                        borderRadius: '50%',
                        background: 'rgba(110,155,230,.10)',
                    }}
                />
                {/* Floating product artifacts: the two things the platform
                    actually produces — a payslip and a verified clock-in.
                    Decorative circles say "template"; the product says us. */}
                <div
                    className="avn-auth-float"
                    aria-hidden
                    style={{
                        ...glassCard,
                        position: 'absolute',
                        top: 48,
                        right: 56,
                        width: 250,
                        padding: '16px 18px',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                            marginBottom: 10,
                        }}
                    >
                        <span style={{ fontSize: 12, fontWeight: 600 }}>
                            Slip Gaji · Juni
                        </span>
                        <span
                            style={{
                                fontSize: 10.5,
                                fontWeight: 600,
                                padding: '2px 8px',
                                borderRadius: 100,
                                background: 'rgba(74,222,128,.18)',
                                color: '#86EFAC',
                            }}
                        >
                            Terbayar
                        </span>
                    </div>
                    {[
                        ['Gaji Pokok', 'Rp 10.000.000'],
                        ['Lembur', 'Rp 854.186'],
                        ['PPh 21', '- Rp 522.907'],
                    ].map(([k, v]) => (
                        <div
                            key={k}
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                fontSize: 11.5,
                                color: 'rgba(255,255,255,.75)',
                                padding: '3px 0',
                            }}
                        >
                            <span>{k}</span>
                            <span
                                style={{
                                    fontVariantNumeric: 'tabular-nums',
                                }}
                            >
                                {v}
                            </span>
                        </div>
                    ))}
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            fontSize: 12.5,
                            fontWeight: 700,
                            borderTop: '1px solid rgba(255,255,255,.15)',
                            marginTop: 8,
                            paddingTop: 8,
                        }}
                    >
                        <span>Take Home Pay</span>
                        <span style={{ fontVariantNumeric: 'tabular-nums' }}>
                            Rp 9.939.995
                        </span>
                    </div>
                </div>

                <div
                    className="avn-auth-float2"
                    aria-hidden
                    style={{
                        ...glassCard,
                        position: 'absolute',
                        bottom: 48,
                        right: 56,
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        padding: '12px 16px',
                    }}
                >
                    <span
                        style={{
                            width: 34,
                            height: 34,
                            borderRadius: '50%',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            background: 'rgba(74,222,128,.16)',
                        }}
                    >
                        <BadgeCheck size={18} color="#86EFAC" />
                    </span>
                    <span>
                        <span
                            style={{
                                display: 'block',
                                fontSize: 12.5,
                                fontWeight: 600,
                            }}
                        >
                            Clock-in 07:58 · Wajah cocok
                        </span>
                        <span
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 4,
                                fontSize: 11,
                                color: 'rgba(255,255,255,.65)',
                                marginTop: 2,
                            }}
                        >
                            <MapPin size={11} /> Dalam area kantor
                        </span>
                    </span>
                </div>

                <div
                    style={{
                        position: 'relative',
                        color: '#fff',
                        maxWidth: 440,
                    }}
                >
                    <div
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '7px 14px',
                            background: 'rgba(255,255,255,.1)',
                            border: '1px solid rgba(255,255,255,.16)',
                            borderRadius: 100,
                            fontSize: 12.5,
                            fontWeight: 500,
                            marginBottom: 28,
                        }}
                    >
                        <Sparkles size={14} color={C.sky} />
                        Platform HRIS / HCM Multi-tenant
                    </div>
                    <div
                        style={{
                            fontSize: 38,
                            fontWeight: 700,
                            lineHeight: 1.18,
                            letterSpacing: '-.02em',
                        }}
                    >
                        Satu platform untuk seluruh siklus karyawan Anda.
                    </div>
                    <div
                        style={{
                            fontSize: 15,
                            lineHeight: 1.7,
                            color: 'rgba(255,255,255,.7)',
                            marginTop: 20,
                        }}
                    >
                        Dari rekrutmen, absensi berbasis GPS, pengajuan cuti,
                        hingga payroll &amp; slip gaji — semua terintegrasi dan
                        sesuai regulasi Indonesia.
                    </div>
                    <div style={{ display: 'flex', gap: 36, marginTop: 44 }}>
                        <div>
                            <div style={{ fontSize: 28, fontWeight: 700 }}>
                                12.400+
                            </div>
                            <div
                                style={{
                                    fontSize: 13,
                                    color: 'rgba(255,255,255,.6)',
                                    marginTop: 2,
                                }}
                            >
                                Karyawan dikelola
                            </div>
                        </div>
                        <div>
                            <div style={{ fontSize: 28, fontWeight: 700 }}>
                                98,7%
                            </div>
                            <div
                                style={{
                                    fontSize: 13,
                                    color: 'rgba(255,255,255,.6)',
                                    marginTop: 2,
                                }}
                            >
                                Akurasi payroll
                            </div>
                        </div>
                        <div>
                            <div style={{ fontSize: 28, fontWeight: 700 }}>
                                320+
                            </div>
                            <div
                                style={{
                                    fontSize: 13,
                                    color: 'rgba(255,255,255,.6)',
                                    marginTop: 2,
                                }}
                            >
                                Perusahaan
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
