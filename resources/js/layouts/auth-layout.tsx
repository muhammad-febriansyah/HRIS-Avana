import { Head } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import type { ReactNode } from 'react';
import { C } from '@/lib/avana';

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
    return (
        <div style={{ minHeight: '100vh', display: 'flex', background: '#fff', fontFamily: "'Poppins',system-ui,sans-serif", color: C.text }}>
            <Head>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="" />
                <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
            </Head>

            {/* LEFT — form */}
            <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 40 }}>
                <div style={{ width: '100%', maxWidth: 400 }}>
                    <img src="/avana/logo-full.png" alt="AvanaHR" style={{ height: 46, marginBottom: 40 }} />
                    {title && <div style={{ fontSize: 24, fontWeight: 600, color: C.navy, letterSpacing: '-.01em' }}>{title}</div>}
                    {description && <div style={{ fontSize: 14, color: C.muted, marginTop: 6, marginBottom: 30 }}>{description}</div>}

                    {children}

                    <div style={{ textAlign: 'center', fontSize: 12.5, color: C.faint, marginTop: 40 }}>© 2026 AvanaHR · Advancing People, Empowering Growth</div>
                </div>
            </div>

            {/* RIGHT — hero */}
            <div
                className="login-hero"
                style={{ flex: 1, background: 'linear-gradient(150deg,#0E1A3A 0%,#1c3175 55%,#2F54C9 100%)', position: 'relative', overflow: 'hidden', display: 'flex', flexDirection: 'column', justifyContent: 'center', padding: 64 }}
            >
                <div style={{ position: 'absolute', top: -80, right: -60, width: 320, height: 320, borderRadius: '50%', background: 'rgba(110,155,230,.18)' }} />
                <div style={{ position: 'absolute', bottom: -100, left: -80, width: 360, height: 360, borderRadius: '50%', background: 'rgba(110,155,230,.10)' }} />
                <div style={{ position: 'relative', color: '#fff', maxWidth: 440 }}>
                    <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, padding: '7px 14px', background: 'rgba(255,255,255,.1)', border: '1px solid rgba(255,255,255,.16)', borderRadius: 100, fontSize: 12.5, fontWeight: 500, marginBottom: 28 }}>
                        <Sparkles size={14} color={C.sky} />
                        Platform HRIS / HCM Multi-tenant
                    </div>
                    <div style={{ fontSize: 38, fontWeight: 700, lineHeight: 1.18, letterSpacing: '-.02em' }}>Satu platform untuk seluruh siklus karyawan Anda.</div>
                    <div style={{ fontSize: 15, lineHeight: 1.7, color: 'rgba(255,255,255,.7)', marginTop: 20 }}>Dari rekrutmen, absensi berbasis GPS, pengajuan cuti, hingga payroll &amp; slip gaji — semua terintegrasi dan sesuai regulasi Indonesia.</div>
                    <div style={{ display: 'flex', gap: 36, marginTop: 44 }}>
                        <div>
                            <div style={{ fontSize: 28, fontWeight: 700 }}>12.400+</div>
                            <div style={{ fontSize: 13, color: 'rgba(255,255,255,.6)', marginTop: 2 }}>Karyawan dikelola</div>
                        </div>
                        <div>
                            <div style={{ fontSize: 28, fontWeight: 700 }}>98,7%</div>
                            <div style={{ fontSize: 13, color: 'rgba(255,255,255,.6)', marginTop: 2 }}>Akurasi payroll</div>
                        </div>
                        <div>
                            <div style={{ fontSize: 28, fontWeight: 700 }}>320+</div>
                            <div style={{ fontSize: 13, color: 'rgba(255,255,255,.6)', marginTop: 2 }}>Perusahaan</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
