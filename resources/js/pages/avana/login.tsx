import { Head, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AIcon, C } from '@/lib/avana';

export default function AvanaLogin() {
    const goToDashboard = () => {
        router.visit('/avana/dashboard');
    };

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        goToDashboard();
    };

    return (
        <>
            <Head title="Masuk">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
            </Head>
            <div style={{ minHeight: '100vh', display: 'flex', background: '#fff', fontFamily: "'Poppins',system-ui,sans-serif", color: C.text }}>
                {/* LEFT — login form */}
                <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 40 }}>
                    <div style={{ width: '100%', maxWidth: 400 }}>
                        <img src="/avana/logo-full.png" alt="AvanaHR" style={{ height: 46, marginBottom: 40 }} />
                        <div style={{ fontSize: 24, fontWeight: 600, color: C.navy, letterSpacing: '-.01em' }}>Masuk ke akun Anda</div>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 6, marginBottom: 30 }}>Kelola karyawan, absensi, dan payroll dalam satu platform.</div>
                        <form onSubmit={handleSubmit}>
                            <label style={{ display: 'block', fontSize: 13, fontWeight: 500, color: C.text, marginBottom: 7 }}>
                                Email <span style={{ color: C.red }}>*</span>
                            </label>
                            <div style={{ position: 'relative', marginBottom: 18 }}>
                                <AIcon name="mail" size={17} color={C.faint} style={{ position: 'absolute', left: 13, top: '50%', transform: 'translateY(-50%)' }} />
                                <input
                                    type="email"
                                    placeholder="nama@perusahaan.co.id"
                                    defaultValue="admin@avanahr.co.id"
                                    style={{ width: '100%', height: 44, padding: '0 14px 0 40px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 14, outline: 'none', transition: 'border-color .15s,box-shadow .15s' }}
                                />
                            </div>
                            <label style={{ display: 'block', fontSize: 13, fontWeight: 500, color: C.text, marginBottom: 7 }}>
                                Kata Sandi <span style={{ color: C.red }}>*</span>
                            </label>
                            <div style={{ position: 'relative', marginBottom: 14 }}>
                                <AIcon name="lock" size={17} color={C.faint} style={{ position: 'absolute', left: 13, top: '50%', transform: 'translateY(-50%)' }} />
                                <input
                                    type="password"
                                    placeholder="Masukkan kata sandi"
                                    defaultValue="password"
                                    style={{ width: '100%', height: 44, padding: '0 14px 0 40px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 14, outline: 'none', transition: 'border-color .15s,box-shadow .15s' }}
                                />
                            </div>
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 24 }}>
                                <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: C.muted, cursor: 'pointer' }}>
                                    <input type="checkbox" style={{ width: 15, height: 15, accentColor: C.primary }} />
                                    Ingat saya
                                </label>
                                <a href="#" style={{ fontSize: 13, color: C.primary, textDecoration: 'none', fontWeight: 500 }}>
                                    Lupa sandi?
                                </a>
                            </div>
                            <button
                                type="submit"
                                style={{ width: '100%', height: 46, background: C.primary, color: '#fff', border: 'none', borderRadius: 8, fontSize: 15, fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, transition: 'background .15s,box-shadow .15s' }}
                            >
                                <AIcon name="log-in" size={18} />
                                Masuk
                            </button>
                        </form>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 14, margin: '24px 0', color: C.faint, fontSize: 12 }}>
                            <div style={{ flex: 1, height: 1, background: C.border }} />
                            atau
                            <div style={{ flex: 1, height: 1, background: C.border }} />
                        </div>
                        <button
                            onClick={goToDashboard}
                            style={{ width: '100%', height: 46, background: '#fff', color: C.text, border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 14, fontWeight: 500, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 10, transition: 'background .15s,border-color .15s' }}
                        >
                            <AIcon name="key-round" size={17} color={C.primary} />
                            Masuk dengan SSO Perusahaan
                        </button>
                        <div style={{ textAlign: 'center', fontSize: 12.5, color: C.faint, marginTop: 40 }}>© 2026 AvanaHR · Advancing People, Empowering Growth</div>
                    </div>
                </div>

                {/* RIGHT — navy gradient hero */}
                <div
                    className="login-hero"
                    style={{ flex: 1, background: 'linear-gradient(150deg,#0E1A3A 0%,#1c3175 55%,#2F54C9 100%)', position: 'relative', overflow: 'hidden', display: 'flex', flexDirection: 'column', justifyContent: 'center', padding: 64 }}
                >
                    <div style={{ position: 'absolute', top: -80, right: -60, width: 320, height: 320, borderRadius: '50%', background: 'rgba(110,155,230,.18)' }} />
                    <div style={{ position: 'absolute', bottom: -100, left: -80, width: 360, height: 360, borderRadius: '50%', background: 'rgba(110,155,230,.10)' }} />
                    <div style={{ position: 'relative', color: '#fff', maxWidth: 440 }}>
                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, padding: '7px 14px', background: 'rgba(255,255,255,.1)', border: '1px solid rgba(255,255,255,.16)', borderRadius: 100, fontSize: 12.5, fontWeight: 500, marginBottom: 28 }}>
                            <AIcon name="sparkles" size={14} color={C.sky} />
                            Platform HRIS / HCM Multi-tenant
                        </div>
                        <div style={{ fontSize: 38, fontWeight: 700, lineHeight: 1.18, letterSpacing: '-.02em' }}>Satu platform untuk seluruh siklus karyawan Anda.</div>
                        <div style={{ fontSize: 15, lineHeight: 1.7, color: 'rgba(255,255,255,.7)', marginTop: 20 }}>
                            Dari rekrutmen, absensi berbasis GPS, pengajuan cuti, hingga payroll &amp; slip gaji — semua terintegrasi dan sesuai regulasi Indonesia.
                        </div>
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
        </>
    );
}
