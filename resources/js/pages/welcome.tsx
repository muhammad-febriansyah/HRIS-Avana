import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    CheckCircle2,
    Facebook,
    Fingerprint,
    Instagram,
    LayoutDashboard,
    Linkedin,
    Mail,
    MapPin,
    Music2,
    Phone,
    ShieldCheck,
    Sparkles,
    Target,
    TreePalm,
    UserPlus,
    Users,
    Wallet,
    Youtube,
} from 'lucide-react';
import { motion } from 'motion/react';
import type { ComponentType, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import {
    MobileNav,
    MobileNavHeader,
    MobileNavMenu,
    MobileNavToggle,
    NavBody,
    Navbar,
    NavbarButton,
    NavItems,
} from '@/components/ui/resizable-navbar';
import { dashboard, login, register } from '@/routes';

const NAV_ITEMS = [
    { name: 'Fitur', link: '#fitur' },
    { name: 'Solusi', link: '#solusi' },
    { name: 'Harga', link: '#harga' },
    { name: 'Kontak', link: '#kontak' },
];

const FEATURES: { icon: ComponentType<{ className?: string }>; title: string; desc: string }[] = [
    { icon: Users, title: 'Data Karyawan & Kontrak', desc: 'Kelola profil, kontrak, mutasi, dan dokumen karyawan dalam satu tempat.' },
    { icon: Fingerprint, title: 'Absensi GPS & Roster', desc: 'Presensi berbasis lokasi, jadwal shift, tukar shift, dan timesheet.' },
    { icon: TreePalm, title: 'Cuti, Lembur & Approval', desc: 'Pengajuan cuti/lembur dengan alur persetujuan berjenjang & delegasi.' },
    { icon: Wallet, title: 'Payroll & Slip Gaji', desc: 'Hitung gaji, BPJS, dan PPh21 TER otomatis. Slip gaji siap kirim.' },
    { icon: UserPlus, title: 'Rekrutmen & Onboarding', desc: 'Lowongan, pelamar, hingga onboarding karyawan baru yang terstruktur.' },
    { icon: Target, title: 'Kinerja, OKR & LMS', desc: 'Penilaian kinerja, goal setting, kompetensi, dan pembelajaran.' },
    { icon: BarChart3, title: 'HR Analytics & Laporan', desc: 'Dashboard analitik dan laporan dinamis untuk keputusan berbasis data.' },
    { icon: Sparkles, title: 'AI Assistant', desc: 'Asisten pintar untuk tanya-jawab data HR dan otomasi tugas rutin.' },
    { icon: ShieldCheck, title: 'Hak Akses & Audit', desc: 'Kontrol akses berbasis peran dan jejak audit setiap aktivitas.' },
];

const STATS = [
    { value: '12.400+', label: 'Karyawan dikelola' },
    { value: '320+', label: 'Perusahaan' },
    { value: '98,7%', label: 'Akurasi payroll' },
    { value: '99,9%', label: 'Uptime layanan' },
];

const COMPLIANCE = [
    'Perhitungan BPJS Kesehatan & Ketenagakerjaan',
    'PPh21 skema TER terbaru (PMK 168/2023)',
    'THR, bonus, dan pesangon otomatis',
    'Multi-tenant dengan akses berbasis peran',
];

const CLIENTS = ['Nusantara Group', 'Bahari Tbk', 'Sentosa Karya', 'Meridian Co', 'Cahaya Abadi', 'Prima Logistik'];

const PRICING = [
    {
        name: 'Starter',
        price: 'Rp15rb',
        unit: '/karyawan/bln',
        desc: 'Untuk tim kecil yang baru mulai.',
        features: ['Data karyawan & kontrak', 'Absensi GPS', 'Cuti & lembur', 'Slip gaji dasar'],
        highlighted: false,
    },
    {
        name: 'Growth',
        price: 'Rp25rb',
        unit: '/karyawan/bln',
        desc: 'Paling populer untuk perusahaan berkembang.',
        features: ['Semua fitur Starter', 'Payroll penuh + BPJS/PPh21', 'Rekrutmen & onboarding', 'Kinerja & OKR', 'HR Analytics'],
        highlighted: true,
    },
    {
        name: 'Enterprise',
        price: 'Kustom',
        unit: '',
        desc: 'Skala besar, kebutuhan khusus.',
        features: ['Semua fitur Growth', 'AI Assistant', 'LMS & suksesi', 'SSO & audit lanjutan', 'Dukungan prioritas'],
        highlighted: false,
    },
];

/**
 * Fade-and-rise wrapper. Animates on mount (SSR-safe — content is always
 * visible after hydration, never gated behind an intersection observer that
 * may fail to fire for off-screen or reduced-motion users).
 */
function Reveal({ children, delay = 0, className }: { children: ReactNode; delay?: number; className?: string }) {
    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay, ease: 'easeOut' }}
            className={className}
        >
            {children}
        </motion.div>
    );
}

export default function Welcome() {
    const { auth, website } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const [activeSection, setActiveSection] = useState('');

    // Scrollspy: mark the nav item whose section is currently in view as active.
    useEffect(() => {
        const ids = NAV_ITEMS.map((i) => i.link.slice(1));
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setActiveSection(`#${entry.target.id}`);
                    }
                });
            },
            { rootMargin: '-45% 0px -50% 0px' },
        );
        ids.forEach((id) => {
            const el = document.getElementById(id);

            if (el) {
                observer.observe(el);
            }
        });

        return () => observer.disconnect();
    }, []);

    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';

    const socials = [
        { href: website.social.facebook, icon: Facebook, label: 'Facebook' },
        { href: website.social.instagram, icon: Instagram, label: 'Instagram' },
        { href: website.social.linkedin, icon: Linkedin, label: 'LinkedIn' },
        { href: website.social.youtube, icon: Youtube, label: 'YouTube' },
        { href: website.social.tiktok, icon: Music2, label: 'TikTok' },
    ].filter((s) => s.href);

    return (
        <>
            <Head title={`${brand} — Platform HRIS & Payroll Indonesia`} />

            <div className="relative min-h-dvh overflow-x-clip bg-white font-sans text-[#1A2333]">
                {/* Hero backdrop — spans behind the navbar so the top never shows a plain white band */}
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-x-0 top-0 h-[860px] [mask-image:linear-gradient(to_bottom,black,transparent_82%)]"
                    style={{
                        backgroundImage:
                            'linear-gradient(to right, rgba(14,26,58,0.045) 1px, transparent 1px), linear-gradient(to bottom, rgba(14,26,58,0.045) 1px, transparent 1px)',
                        backgroundSize: '44px 44px',
                    }}
                />
                <div className="pointer-events-none absolute top-0 left-1/2 h-[540px] w-[860px] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(47,84,201,0.12),transparent)]" />

                {/* NAVBAR */}
                <Navbar>
                    <NavBody>
                        <Link href="/" className="relative z-20 flex items-center gap-2 px-2 py-1">
                            <img src={logo} alt={brand} className="h-8 w-auto object-contain" />
                        </Link>
                        <NavItems items={NAV_ITEMS} activeLink={activeSection} />
                        <div className="relative z-20 flex items-center gap-2">
                            {auth.user ? (
                                <NavbarButton as={Link} href={dashboard().url} variant="primary">
                                    Dashboard
                                </NavbarButton>
                            ) : (
                                <>
                                    <NavbarButton
                                        as={Link}
                                        href={login().url}
                                        variant="secondary"
                                        className="border border-[#E5E9F2] hover:border-[#2F54C9]"
                                    >
                                        Masuk
                                    </NavbarButton>
                                    <NavbarButton as={Link} href={register().url} variant="primary">
                                        Coba Gratis
                                    </NavbarButton>
                                </>
                            )}
                        </div>
                    </NavBody>

                    <MobileNav>
                        <MobileNavHeader>
                            <Link href="/" className="flex items-center gap-2 px-2 py-1">
                                <img src={logo} alt={brand} className="h-7 w-auto object-contain" />
                            </Link>
                            <MobileNavToggle isOpen={mobileOpen} onClick={() => setMobileOpen((v) => !v)} />
                        </MobileNavHeader>
                        <MobileNavMenu isOpen={mobileOpen} onClose={() => setMobileOpen(false)}>
                            {NAV_ITEMS.map((item) => (
                                <a
                                    key={item.link}
                                    href={item.link}
                                    onClick={() => setMobileOpen(false)}
                                    className={
                                        'w-full rounded-lg px-2 py-2 text-[15px] font-medium hover:bg-[#F4F6FB] ' +
                                        (activeSection === item.link ? 'bg-[#2F54C9]/10 text-[#2F54C9]' : 'text-[#1A2333]')
                                    }
                                >
                                    {item.name}
                                </a>
                            ))}
                            <div className="mt-2 flex w-full flex-col gap-2">
                                {auth.user ? (
                                    <NavbarButton as={Link} href={dashboard().url} variant="primary" className="w-full">
                                        Dashboard
                                    </NavbarButton>
                                ) : (
                                    <>
                                        <NavbarButton as={Link} href={login().url} variant="secondary" className="w-full border border-[#E5E9F2]">
                                            Masuk
                                        </NavbarButton>
                                        <NavbarButton as={Link} href={register().url} variant="primary" className="w-full">
                                            Coba Gratis
                                        </NavbarButton>
                                    </>
                                )}
                            </div>
                        </MobileNavMenu>
                    </MobileNav>
                </Navbar>

                {/* HERO */}
                <section className="relative">
                    <div className="mx-auto max-w-7xl px-6 pt-28 pb-16 text-center lg:pt-36">
                        <Reveal className="flex justify-center">
                            <span className="inline-flex items-center gap-2 rounded-full border border-[#E5E9F2] bg-white/70 px-3.5 py-1.5 text-xs font-medium text-[#2F54C9] shadow-sm backdrop-blur">
                                <Sparkles className="h-3.5 w-3.5" />
                                Platform HRIS / HCM Multi-tenant
                            </span>
                        </Reveal>
                        <Reveal delay={0.05}>
                            <h1 className="mx-auto mt-6 max-w-4xl text-4xl font-bold leading-[1.1] tracking-tight text-[#0E1A3A] sm:text-5xl lg:text-6xl">
                                Kelola seluruh{' '}
                                <span className="bg-gradient-to-r from-[#2F54C9] to-[#6E9BE6] bg-clip-text text-transparent">siklus karyawan</span> dalam
                                satu platform.
                            </h1>
                        </Reveal>
                        <Reveal delay={0.1}>
                            <p className="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-[#6B7280] sm:text-lg">
                                {website.tagline ??
                                    'Dari rekrutmen, absensi berbasis GPS, pengajuan cuti, hingga payroll & slip gaji — terintegrasi dan sesuai regulasi Indonesia.'}
                            </p>
                        </Reveal>
                        <Reveal delay={0.15}>
                            <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <Link
                                    href={register().url}
                                    className="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-[#2F54C9] px-7 text-[15px] font-semibold text-white shadow-[0_10px_24px_-8px_rgba(47,84,201,0.6)] transition hover:-translate-y-0.5 hover:bg-[#2546ad]"
                                >
                                    Coba Gratis 14 Hari
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                                <a
                                    href="#kontak"
                                    className="inline-flex h-12 items-center justify-center rounded-full border border-[#E5E9F2] bg-white px-7 text-[15px] font-semibold text-[#1A2333] transition hover:-translate-y-0.5 hover:border-[#2F54C9]"
                                >
                                    Hubungi Sales
                                </a>
                            </div>
                        </Reveal>
                        <Reveal delay={0.2}>
                            <div className="mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-[13px] text-[#6B7280]">
                                {['Tanpa kartu kredit', 'Sesuai regulasi Indonesia', 'Data terenkripsi'].map((t) => (
                                    <span key={t} className="inline-flex items-center gap-1.5">
                                        <CheckCircle2 className="h-4 w-4 text-[#16A34A]" />
                                        {t}
                                    </span>
                                ))}
                            </div>
                        </Reveal>

                        {/* Product preview */}
                        <Reveal delay={0.25}>
                            <div className="relative mx-auto mt-16 max-w-5xl">
                                <div className="pointer-events-none absolute -inset-x-8 -top-8 bottom-0 rounded-[2.5rem] bg-[radial-gradient(55%_45%_at_50%_0%,rgba(47,84,201,0.18),transparent)]" />
                                <div className="relative overflow-hidden rounded-2xl border border-[#E5E9F2] bg-white shadow-[0_40px_100px_-40px_rgba(14,26,58,0.5)]">
                                    {/* browser chrome */}
                                    <div className="flex items-center gap-2 border-b border-[#F1F3F9] bg-[#FBFCFE] px-4 py-3">
                                        <span className="h-3 w-3 rounded-full bg-[#FF5F57]" />
                                        <span className="h-3 w-3 rounded-full bg-[#FEBC2E]" />
                                        <span className="h-3 w-3 rounded-full bg-[#28C840]" />
                                        <div className="mx-auto hidden h-6 w-full max-w-xs items-center justify-center rounded-md border border-[#EDF1F7] bg-white px-3 text-[11px] text-[#9CA3AF] sm:flex">
                                            app.avanahr.co.id/dashboard
                                        </div>
                                    </div>
                                    {/* dashboard body */}
                                    <div className="flex text-left">
                                        <aside className="hidden w-52 shrink-0 border-r border-[#F1F3F9] bg-white p-4 lg:block">
                                            <div className="flex items-center gap-2 px-1 pb-4">
                                                <img src={logo} alt={brand} className="h-6 w-auto object-contain" />
                                            </div>
                                            {[
                                                { icon: LayoutDashboard, label: 'Dashboard', active: true },
                                                { icon: Users, label: 'Karyawan', active: false },
                                                { icon: Fingerprint, label: 'Absensi', active: false },
                                                { icon: Wallet, label: 'Payroll', active: false },
                                                { icon: Target, label: 'Kinerja', active: false },
                                            ].map((n) => (
                                                <div
                                                    key={n.label}
                                                    className={
                                                        'mb-1 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm ' +
                                                        (n.active ? 'bg-[#2F54C9]/10 font-medium text-[#2F54C9]' : 'text-[#6B7280]')
                                                    }
                                                >
                                                    <n.icon className="h-4 w-4" />
                                                    {n.label}
                                                </div>
                                            ))}
                                        </aside>
                                        <div className="flex-1 bg-[#F9FAFC] p-5 sm:p-6">
                                            <div className="mb-5 flex items-center justify-between">
                                                <div>
                                                    <div className="text-base font-semibold text-[#0E1A3A]">Dashboard HR</div>
                                                    <div className="text-xs text-[#9CA3AF]">Ringkasan Juli 2026</div>
                                                </div>
                                                <span className="hidden rounded-full bg-[#16A34A]/10 px-3 py-1 text-xs font-medium text-[#16A34A] sm:inline">
                                                    Payroll selesai
                                                </span>
                                            </div>
                                            <div className="grid grid-cols-3 gap-3">
                                                {[
                                                    { k: 'Karyawan Aktif', v: '1.284', icon: Users },
                                                    { k: 'Kehadiran', v: '96,4%', icon: Fingerprint },
                                                    { k: 'Payroll Bulan Ini', v: 'Rp 4,82 M', icon: Wallet },
                                                ].map((c) => (
                                                    <div key={c.k} className="rounded-xl border border-[#EDF1F7] bg-white p-4">
                                                        <c.icon className="h-4 w-4 text-[#2F54C9]" />
                                                        <div className="mt-3 text-lg font-bold text-[#0E1A3A] tabular-nums sm:text-xl">{c.v}</div>
                                                        <div className="text-[11px] text-[#9CA3AF]">{c.k}</div>
                                                    </div>
                                                ))}
                                            </div>
                                            <div className="mt-3 grid gap-3 lg:grid-cols-5">
                                                <div className="rounded-xl border border-[#EDF1F7] bg-white p-4 lg:col-span-3">
                                                    <div className="mb-3 flex items-center justify-between">
                                                        <span className="text-sm font-medium text-[#1A2333]">Tren Kehadiran</span>
                                                        <span className="text-[11px] text-[#9CA3AF]">7 hari</span>
                                                    </div>
                                                    <div className="flex h-28 items-end gap-2">
                                                        {[62, 78, 54, 88, 72, 95, 84].map((h, i) => (
                                                            <div key={i} className="flex-1 rounded-t-md bg-gradient-to-t from-[#2F54C9]/30 to-[#2F54C9]" style={{ height: `${h}%` }} />
                                                        ))}
                                                    </div>
                                                </div>
                                                <div className="rounded-xl bg-gradient-to-br from-[#0E1A3A] to-[#2F54C9] p-4 text-white lg:col-span-2">
                                                    <div className="text-[11px] text-white/60">Payroll Juli 2026</div>
                                                    <div className="mt-1 text-xl font-bold tabular-nums">Rp 4,82 M</div>
                                                    <div className="mt-4 space-y-2">
                                                        {[
                                                            ['Gaji pokok', '82%'],
                                                            ['Tunjangan', '13%'],
                                                            ['BPJS & PPh21', '5%'],
                                                        ].map(([k, v]) => (
                                                            <div key={k}>
                                                                <div className="flex justify-between text-[11px] text-white/70">
                                                                    <span>{k}</span>
                                                                    <span>{v}</span>
                                                                </div>
                                                                <div className="mt-1 h-1.5 rounded-full bg-white/15">
                                                                    <div className="h-full rounded-full bg-white/70" style={{ width: v }} />
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <motion.div
                                    initial={{ opacity: 0, y: 12 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ delay: 0.6 }}
                                    className="absolute -top-5 -right-3 hidden rounded-xl border border-[#E5E9F2] bg-white px-4 py-3 shadow-[0_16px_40px_-16px_rgba(14,26,58,0.4)] sm:block"
                                >
                                    <div className="flex items-center gap-2.5">
                                        <span className="grid h-9 w-9 place-items-center rounded-lg bg-[#16A34A]/10 text-[#16A34A]">
                                            <CheckCircle2 className="h-5 w-5" />
                                        </span>
                                        <div className="text-left">
                                            <div className="text-xs text-[#6B7280]">Slip gaji terkirim</div>
                                            <div className="text-sm font-semibold">1.284 karyawan</div>
                                        </div>
                                    </div>
                                </motion.div>
                            </div>
                        </Reveal>
                    </div>
                </section>

                {/* CLIENT MARQUEE */}
                <section className="border-y border-[#F1F3F9] bg-[#F4F6FB] py-10">
                    <p className="mb-6 text-center text-xs font-medium tracking-wide text-[#9CA3AF] uppercase">
                        Dipercaya oleh 320+ perusahaan di Indonesia
                    </p>
                    <div className="group relative flex overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_12%,black_88%,transparent)]">
                        <div className="flex shrink-0 animate-[marquee_28s_linear_infinite] items-center gap-14 pr-14 group-hover:[animation-play-state:paused]">
                            {[...CLIENTS, ...CLIENTS].map((c, i) => (
                                <span key={i} className="text-lg font-semibold whitespace-nowrap text-[#9CA3AF]">
                                    {c}
                                </span>
                            ))}
                        </div>
                        <div
                            aria-hidden
                            className="flex shrink-0 animate-[marquee_28s_linear_infinite] items-center gap-14 pr-14 group-hover:[animation-play-state:paused]"
                        >
                            {[...CLIENTS, ...CLIENTS].map((c, i) => (
                                <span key={i} className="text-lg font-semibold whitespace-nowrap text-[#9CA3AF]">
                                    {c}
                                </span>
                            ))}
                        </div>
                    </div>
                </section>

                {/* STATS */}
                <section className="mx-auto max-w-7xl px-6 py-16">
                    <div className="grid grid-cols-2 gap-y-8 rounded-2xl border border-[#E5E9F2] bg-white px-6 py-10 shadow-[0_20px_50px_-30px_rgba(14,26,58,0.25)] lg:grid-cols-4 lg:gap-y-0">
                        {STATS.map((s, i) => (
                            <Reveal
                                key={s.label}
                                delay={i * 0.05}
                                className="text-center lg:border-l lg:border-[#E5E9F2] lg:first:border-l-0"
                            >
                                <div className="bg-gradient-to-b from-[#0E1A3A] to-[#2F54C9] bg-clip-text text-3xl font-bold text-transparent tabular-nums sm:text-4xl">
                                    {s.value}
                                </div>
                                <div className="mt-1.5 text-sm text-[#6B7280]">{s.label}</div>
                            </Reveal>
                        ))}
                    </div>
                </section>

                {/* FEATURES */}
                <section id="fitur" className="mx-auto max-w-7xl scroll-mt-28 px-6 py-20">
                    <Reveal>
                        <div className="mx-auto max-w-2xl text-center">
                            <span className="text-sm font-semibold text-[#2F54C9]">Fitur Lengkap</span>
                            <h2 className="mt-2 text-3xl font-bold tracking-tight text-[#0E1A3A] sm:text-4xl">
                                Semua yang tim HR Anda butuhkan
                            </h2>
                            <p className="mt-3 text-[#6B7280]">Satu sistem terintegrasi menggantikan puluhan spreadsheet dan aplikasi terpisah.</p>
                        </div>
                    </Reveal>
                    <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {FEATURES.map((f, i) => (
                            <Reveal key={f.title} delay={(i % 3) * 0.05}>
                                <div className="group h-full rounded-2xl border border-[#E5E9F2] bg-white p-6 transition hover:-translate-y-1 hover:border-[#2F54C9]/40 hover:shadow-[0_20px_50px_-24px_rgba(14,26,58,0.3)]">
                                    <span className="grid h-11 w-11 place-items-center rounded-xl bg-[#2F54C9]/10 text-[#2F54C9] transition group-hover:bg-[#2F54C9] group-hover:text-white">
                                        <f.icon className="h-5 w-5" />
                                    </span>
                                    <h3 className="mt-4 text-lg font-semibold text-[#0E1A3A]">{f.title}</h3>
                                    <p className="mt-1.5 text-sm leading-relaxed text-[#6B7280]">{f.desc}</p>
                                </div>
                            </Reveal>
                        ))}
                    </div>
                </section>

                {/* SOLUSI / COMPLIANCE */}
                <section id="solusi" className="scroll-mt-28 bg-[#F4F6FB] py-20">
                    <div className="mx-auto grid max-w-7xl items-center gap-14 px-6 lg:grid-cols-2">
                        <Reveal>
                            <div>
                                <span className="text-sm font-semibold text-[#2F54C9]">Sesuai Regulasi Indonesia</span>
                                <h2 className="mt-2 text-3xl font-bold tracking-tight text-[#0E1A3A] sm:text-4xl">
                                    Payroll akurat, patuh, dan tanpa pusing
                                </h2>
                                <p className="mt-3 text-[#6B7280]">
                                    Perhitungan otomatis mengikuti aturan ketenagakerjaan dan perpajakan terbaru — hemat waktu, minim kesalahan.
                                </p>
                                <ul className="mt-6 space-y-3">
                                    {COMPLIANCE.map((c) => (
                                        <li key={c} className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-[#16A34A]" />
                                            <span className="text-[15px] text-[#1A2333]">{c}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </Reveal>
                        <Reveal delay={0.1}>
                            <div className="grid grid-cols-2 gap-4">
                                {[
                                    { icon: Wallet, k: 'BPJS & PPh21', v: 'Otomatis' },
                                    { icon: Fingerprint, k: 'Absensi', v: 'Real-time' },
                                    { icon: ShieldCheck, k: 'Keamanan', v: 'Terenkripsi' },
                                    { icon: BarChart3, k: 'Laporan', v: 'Instan' },
                                ].map((b) => (
                                    <div key={b.k} className="rounded-2xl border border-[#E5E9F2] bg-white p-5">
                                        <span className="grid h-10 w-10 place-items-center rounded-lg bg-[#2F54C9]/10 text-[#2F54C9]">
                                            <b.icon className="h-5 w-5" />
                                        </span>
                                        <div className="mt-3 text-sm text-[#6B7280]">{b.k}</div>
                                        <div className="text-lg font-semibold text-[#0E1A3A]">{b.v}</div>
                                    </div>
                                ))}
                            </div>
                        </Reveal>
                    </div>
                </section>

                {/* PRICING */}
                <section id="harga" className="mx-auto max-w-7xl scroll-mt-28 px-6 py-20">
                    <Reveal>
                        <div className="mx-auto max-w-2xl text-center">
                            <span className="text-sm font-semibold text-[#2F54C9]">Harga Transparan</span>
                            <h2 className="mt-2 text-3xl font-bold tracking-tight text-[#0E1A3A] sm:text-4xl">Pilih paket sesuai skala Anda</h2>
                            <p className="mt-3 text-[#6B7280]">Bayar per karyawan aktif. Tanpa biaya tersembunyi.</p>
                        </div>
                    </Reveal>
                    <div className="mt-12 grid gap-6 lg:grid-cols-3">
                        {PRICING.map((p, i) => (
                            <Reveal key={p.name} delay={i * 0.07}>
                                <div
                                    className={
                                        'flex h-full flex-col rounded-2xl border p-7 ' +
                                        (p.highlighted
                                            ? 'border-[#2F54C9] bg-[#0E1A3A] text-white shadow-[0_30px_70px_-30px_rgba(14,26,58,0.6)]'
                                            : 'border-[#E5E9F2] bg-white')
                                    }
                                >
                                    {p.highlighted && (
                                        <span className="mb-3 inline-flex w-fit rounded-full bg-[#2F54C9] px-3 py-1 text-xs font-semibold text-white">
                                            Paling Populer
                                        </span>
                                    )}
                                    <h3 className={'text-lg font-semibold ' + (p.highlighted ? 'text-white' : 'text-[#0E1A3A]')}>{p.name}</h3>
                                    <div className="mt-3 flex items-end gap-1">
                                        <span className={'text-3xl font-bold ' + (p.highlighted ? 'text-white' : 'text-[#0E1A3A]')}>{p.price}</span>
                                        <span className={'pb-1 text-sm ' + (p.highlighted ? 'text-white/60' : 'text-[#9CA3AF]')}>{p.unit}</span>
                                    </div>
                                    <p className={'mt-2 text-sm ' + (p.highlighted ? 'text-white/70' : 'text-[#6B7280]')}>{p.desc}</p>
                                    <ul className="mt-5 flex-1 space-y-2.5">
                                        {p.features.map((f) => (
                                            <li key={f} className="flex items-start gap-2.5 text-sm">
                                                <CheckCircle2 className={'mt-0.5 h-4 w-4 shrink-0 ' + (p.highlighted ? 'text-[#6E9BE6]' : 'text-[#16A34A]')} />
                                                <span className={p.highlighted ? 'text-white/90' : 'text-[#1A2333]'}>{f}</span>
                                            </li>
                                        ))}
                                    </ul>
                                    <Link
                                        href={register().url}
                                        className={
                                            'mt-7 inline-flex h-11 items-center justify-center rounded-full text-sm font-semibold transition hover:-translate-y-0.5 ' +
                                            (p.highlighted
                                                ? 'bg-[#2F54C9] text-white hover:bg-[#2546ad]'
                                                : 'border border-[#E5E9F2] text-[#1A2333] hover:border-[#2F54C9]')
                                        }
                                    >
                                        {p.name === 'Enterprise' ? 'Hubungi Sales' : 'Mulai Sekarang'}
                                    </Link>
                                </div>
                            </Reveal>
                        ))}
                    </div>
                </section>

                {/* CTA */}
                <section className="mx-auto max-w-7xl px-6 pb-20">
                    <Reveal>
                        <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0E1A3A] via-[#1c3175] to-[#2F54C9] px-8 py-16 text-center sm:px-16">
                            <div className="pointer-events-none absolute -top-16 -right-10 h-64 w-64 rounded-full bg-white/10" />
                            <div className="pointer-events-none absolute -bottom-20 -left-10 h-72 w-72 rounded-full bg-white/5" />
                            <h2 className="relative text-3xl font-bold tracking-tight text-white sm:text-4xl">Siap modernkan HR perusahaan Anda?</h2>
                            <p className="relative mx-auto mt-3 max-w-xl text-white/70">
                                Coba {brand} gratis 14 hari. Tanpa kartu kredit, bisa langsung pakai.
                            </p>
                            <div className="relative mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                                <Link
                                    href={register().url}
                                    className="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-white px-8 text-[15px] font-semibold text-[#0E1A3A] transition hover:-translate-y-0.5"
                                >
                                    Coba Gratis
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                                <Link
                                    href={login().url}
                                    className="inline-flex h-12 items-center justify-center rounded-full border border-white/25 px-8 text-[15px] font-semibold text-white transition hover:-translate-y-0.5 hover:bg-white/10"
                                >
                                    Masuk
                                </Link>
                            </div>
                        </div>
                    </Reveal>
                </section>

                {/* FOOTER */}
                <footer id="kontak" className="border-t border-[#F1F3F9] bg-[#F4F6FB]">
                    <div className="mx-auto grid max-w-7xl gap-10 px-6 py-14 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="lg:col-span-2">
                            <img src={logo} alt={brand} className="h-9 w-auto object-contain" />
                            <p className="mt-4 max-w-xs text-sm leading-relaxed text-[#6B7280]">
                                {website.tagline ?? 'Platform HRIS/HCM multi-tenant untuk mengelola seluruh siklus karyawan Anda.'}
                            </p>
                            {socials.length > 0 && (
                                <div className="mt-5 flex gap-2.5">
                                    {socials.map((s) => (
                                        <a
                                            key={s.label}
                                            href={s.href ?? '#'}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label={s.label}
                                            className="grid h-9 w-9 place-items-center rounded-lg border border-[#E5E9F2] bg-white text-[#6B7280] transition hover:border-[#2F54C9] hover:text-[#2F54C9]"
                                        >
                                            <s.icon className="h-4 w-4" />
                                        </a>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div>
                            <div className="text-sm font-semibold text-[#0E1A3A]">Produk</div>
                            <ul className="mt-4 space-y-2.5 text-sm text-[#6B7280]">
                                <li><a href="#fitur" className="hover:text-[#2F54C9]">Fitur</a></li>
                                <li><a href="#solusi" className="hover:text-[#2F54C9]">Solusi</a></li>
                                <li><a href="#harga" className="hover:text-[#2F54C9]">Harga</a></li>
                                <li><Link href={login().url} className="hover:text-[#2F54C9]">Masuk</Link></li>
                            </ul>
                        </div>

                        <div>
                            <div className="text-sm font-semibold text-[#0E1A3A]">Kontak</div>
                            <ul className="mt-4 space-y-3 text-sm text-[#6B7280]">
                                {website.contact.email && (
                                    <li className="flex items-center gap-2.5">
                                        <Mail className="h-4 w-4 shrink-0 text-[#2F54C9]" />
                                        <a href={`mailto:${website.contact.email}`} className="hover:text-[#2F54C9]">{website.contact.email}</a>
                                    </li>
                                )}
                                {website.contact.phone && (
                                    <li className="flex items-center gap-2.5">
                                        <Phone className="h-4 w-4 shrink-0 text-[#2F54C9]" />
                                        <a href={`tel:${website.contact.phone}`} className="hover:text-[#2F54C9]">{website.contact.phone}</a>
                                    </li>
                                )}
                                {website.contact.address && (
                                    <li className="flex items-start gap-2.5">
                                        <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-[#2F54C9]" />
                                        <span>{website.contact.address}</span>
                                    </li>
                                )}
                            </ul>
                        </div>
                    </div>
                    <div className="border-t border-[#E5E9F2]">
                        <div className="mx-auto max-w-7xl px-6 py-5 text-center text-[13px] text-[#9CA3AF]">
                            © 2026 {brand} · Advancing People, Empowering Growth
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
