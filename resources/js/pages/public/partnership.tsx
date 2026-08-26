import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Handshake,
    LayoutDashboard,
    PiggyBank,
    Share2,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { Container, Reveal, SectionHeading } from '@/components/marketing/reveal';
import { FaqSection } from '@/components/marketing/faq-section';
import partnerRegistration from '@/routes/partner-registration';
import { SiteFooter } from '@/components/marketing/site-footer';
import { SiteNavbar } from '@/components/marketing/site-navbar';
import { WhatsAppFab } from '@/components/marketing/whatsapp-fab';
import { cn } from '@/lib/utils';

type PartnershipProps = {
    faqs: { q: string; a: string }[];
};

const DESCRIPTION =
    'Gabung jadi Partner AvanaHR. Rekomendasikan solusi HR, Absensi & Payroll dan dapatkan reward atas setiap referral yang berhasil.';

const FEATURES = [
    {
        icon: PiggyBank,
        title: 'Reward Transparan',
        body: 'Skema komisi dijelaskan kepada partner dan dapat dipantau melalui dashboard setelah akun disetujui.',
    },
    {
        icon: LayoutDashboard,
        title: 'Dashboard Real-time',
        body: 'Lihat referral, status trial, customer, dan komisi Anda dalam satu tempat.',
    },
    {
        icon: Handshake,
        title: 'Dukungan AvanaHR',
        body: 'Tim AvanaHR membantu demo, follow-up, dan proses onboarding customer referral Anda.',
    },
];

const STEPS = [
    { number: '01', title: 'Daftar', body: 'Ajukan diri sebagai Partner AvanaHR.' },
    {
        number: '02',
        title: 'Dapatkan Link',
        body: 'Dapatkan kode dan link referral unik.',
    },
    {
        number: '03',
        title: 'Bagikan',
        body: 'Kirim ke perusahaan atau jaringan Anda.',
    },
    {
        number: '04',
        title: 'Customer Berlangganan',
        body: 'AvanaHR membantu proses demo sampai subscription.',
    },
    {
        number: '05',
        title: 'Dapatkan Reward',
        body: 'Referral berhasil akan tercatat di dashboard.',
    },
];

const COMMISSION_ROWS = [
    { label: 'Referral berhasil', value: 'Reward tercatat' },
    { label: 'Customer aktif', value: 'Komisi dihitung' },
    { label: 'Komisi tersedia', value: 'Dapat diajukan' },
    { label: 'Riwayat payout', value: 'Transparan' },
];

export default function Partnership({ faqs }: PartnershipProps) {
    const { website } = usePage().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';
    const title = `Program Partner — ${brand}`;

    return (
        <>
            <Head title={title}>
                <meta name="description" content={DESCRIPTION} />
                <meta property="og:type" content="website" />
                <meta property="og:title" content={title} />
                <meta property="og:description" content={DESCRIPTION} />
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content={title} />
                <meta name="twitter:description" content={DESCRIPTION} />
            </Head>

            <div
                id="top"
                className="min-h-dvh overflow-x-clip bg-white font-sans text-[#1A2333] antialiased"
            >
                <SiteNavbar
                    brand={brand}
                    logo={logo}
                    anchorPrefix="/"
                    activePage="partnership"
                />

                <main>
                    {/* ── Hero ── */}
                    <section className="relative overflow-hidden bg-[#F4F7FF] pt-16 pb-20 sm:pt-20 sm:pb-28">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute top-0 right-0 h-[500px] w-[500px] translate-x-1/3 -translate-y-1/4 rounded-full bg-[#2F54C9]/8 blur-[120px]"
                        />
                        <div
                            aria-hidden
                            className="pointer-events-none absolute bottom-0 left-0 h-[400px] w-[400px] -translate-x-1/3 translate-y-1/4 rounded-full bg-[#2F54C9]/6 blur-[100px]"
                        />

                        <Container className="relative">
                            <div className="mx-auto max-w-3xl text-center">
                                <Reveal>
                                    <span className="inline-flex items-center gap-2 rounded-full border border-[#E2E9F6] bg-white px-4 py-1.5 text-[12px] font-bold tracking-[0.08em] text-[#2F54C9] uppercase shadow-sm">
                                        <Users className="h-3.5 w-3.5" />
                                        AvanaHR Partner Program
                                    </span>
                                </Reveal>

                                <Reveal delay={0.05}>
                                    <h1 className="mt-6 text-[36px] leading-[1.05] font-extrabold tracking-[-0.03em] text-[#0E1A3A] sm:text-[52px] lg:text-[64px]">
                                        Bangun Penghasilan{' '}
                                        <span className="text-[#2F54C9]">
                                            Bersama AvanaHR
                                        </span>
                                    </h1>
                                </Reveal>

                                <Reveal delay={0.1}>
                                    <p className="mx-auto mt-5 max-w-xl text-[16px] leading-relaxed text-[#5B6478] sm:text-[18px]">
                                        Rekomendasikan AvanaHR kepada perusahaan
                                        yang membutuhkan solusi HR, Absensi &amp;
                                        Payroll. Anda membantu mereka bekerja
                                        lebih mudah, kami memberikan reward atas
                                        referral yang berhasil.
                                    </p>
                                </Reveal>

                                <Reveal delay={0.15}>
                                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                                        <a
                                            href="#join"
                                            className="inline-flex h-12 items-center gap-2 rounded-full bg-[#2F54C9] px-7 text-[14px] font-bold text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#2348B0] hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9]"
                                        >
                                            Gabung Jadi Partner
                                            <ArrowRight className="h-4 w-4" />
                                        </a>
                                        <a
                                            href="#cara"
                                            className="inline-flex h-12 items-center gap-2 rounded-full border border-[#D2D9E8] bg-white px-7 text-[14px] font-bold text-[#0E1A3A] transition-all duration-200 hover:-translate-y-0.5 hover:border-[#2F54C9]/30 hover:bg-[#F4F7FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9]"
                                        >
                                            Pelajari Cara Kerja
                                        </a>
                                    </div>
                                </Reveal>
                            </div>
                        </Container>
                    </section>

                    {/* ── Kenapa jadi partner ── */}
                    <section className="py-20 lg:py-28">
                        <Container>
                            <SectionHeading
                                eyebrow="Kenapa Jadi Partner?"
                                title="Lebih dari sekadar program referral."
                                description="AvanaHR membantu Anda mengubah jaringan bisnis menjadi peluang jangka panjang dengan proses yang transparan dan mudah dipantau."
                            />

                            <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {FEATURES.map((f, i) => (
                                    <Reveal
                                        key={f.title}
                                        delay={0.06 * i}
                                    >
                                        <div className="group relative h-full rounded-2xl border border-[#E7ECF5] bg-white p-7 transition-all duration-300 hover:-translate-y-1 hover:border-[#2F54C9]/20 hover:shadow-xl">
                                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EEF2FB] text-[#2F54C9] transition-colors group-hover:bg-[#2F54C9] group-hover:text-white">
                                                <f.icon className="h-5 w-5" />
                                            </div>
                                            <h3 className="mt-5 text-[17px] font-bold text-[#0E1A3A]">
                                                {f.title}
                                            </h3>
                                            <p className="mt-2 text-[14.5px] leading-relaxed text-[#5B6478]">
                                                {f.body}
                                            </p>
                                        </div>
                                    </Reveal>
                                ))}
                            </div>
                        </Container>
                    </section>

                    {/* ── Cara kerja ── */}
                    <section
                        id="cara"
                        className="scroll-mt-28 border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-28"
                    >
                        <Container>
                            <SectionHeading
                                eyebrow="Cara Kerja"
                                title="Semudah membagikan rekomendasi."
                                description="Anda tidak perlu menangani seluruh proses penjualan. Cukup kenalkan AvanaHR kepada perusahaan yang tepat."
                            />

                            <div className="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5">
                                {STEPS.map((step, i) => (
                                    <Reveal
                                        key={step.number}
                                        delay={0.06 * i}
                                    >
                                        <div className="relative h-full rounded-2xl border border-[#E7ECF5] bg-white p-6 transition-all duration-300 hover:-translate-y-1 hover:border-[#2F54C9]/20 hover:shadow-lg">
                                            <span className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-[#EEF2FB] text-[13px] font-extrabold text-[#2F54C9]">
                                                {step.number}
                                            </span>
                                            <h3 className="mt-4 text-[16px] font-bold text-[#0E1A3A]">
                                                {step.title}
                                            </h3>
                                            <p className="mt-1.5 text-[14px] leading-relaxed text-[#5B6478]">
                                                {step.body}
                                            </p>

                                            {/* connector line — hidden on last item and mobile */}
                                            {i < STEPS.length - 1 && (
                                                <div
                                                    aria-hidden
                                                    className="pointer-events-none absolute top-10 -right-2.5 hidden h-px w-5 border-t-2 border-dashed border-[#2F54C9]/20 lg:block"
                                                />
                                            )}
                                        </div>
                                    </Reveal>
                                ))}
                            </div>
                        </Container>
                    </section>

                    {/* ── Skema komisi ── */}
                    <section className="py-20 lg:py-28">
                        <Container>
                            <div className="overflow-hidden rounded-[28px] border border-[#D9E4FA] bg-[#F3F7FF]">
                                <div className="grid grid-cols-1 lg:grid-cols-2">
                                    <div className="p-8 sm:p-12 lg:p-14">
                                        <Reveal>
                                            <span className="inline-flex items-center rounded-full border border-[#E2E9F6] bg-white px-3.5 py-1 text-[12px] font-bold tracking-[0.08em] text-[#2F54C9] uppercase">
                                                Skema Partnership
                                            </span>
                                        </Reveal>

                                        <Reveal delay={0.05}>
                                            <h2 className="mt-5 text-[26px] leading-[1.15] font-bold tracking-[-0.02em] text-[#0E1A3A] sm:text-[32px] lg:text-[36px]">
                                                Komisi transparan, detailnya ada
                                                di dashboard partner.
                                            </h2>
                                        </Reveal>

                                        <Reveal delay={0.1}>
                                            <p className="mt-4 text-[15px] leading-relaxed text-[#5B6478]">
                                                Kami menampilkan informasi program
                                                secara terbuka kepada partner yang
                                                sudah disetujui. Besaran komisi
                                                mengikuti program yang berlaku dan
                                                dapat diperbarui oleh AvanaHR.
                                            </p>
                                        </Reveal>

                                        <Reveal delay={0.15}>
                                            <a
                                                href="#join"
                                                className="mt-7 inline-flex h-12 items-center gap-2 rounded-full bg-[#2F54C9] px-7 text-[14px] font-bold text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#2348B0] hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9]"
                                            >
                                                Daftar Jadi Partner
                                                <ArrowRight className="h-4 w-4" />
                                            </a>
                                        </Reveal>
                                    </div>

                                    <div className="flex flex-col justify-center gap-3 border-t border-[#D9E4FA] bg-white/60 p-8 sm:p-12 lg:border-l lg:border-t-0">
                                        {COMMISSION_ROWS.map((row, i) => (
                                            <Reveal
                                                key={row.label}
                                                delay={0.06 * i}
                                            >
                                                <div className="flex items-center justify-between rounded-xl border border-[#E7ECF5] bg-white px-5 py-4 transition-all duration-200 hover:border-[#2F54C9]/20 hover:shadow-sm">
                                                    <span className="text-[14.5px] font-medium text-[#5B6478]">
                                                        {row.label}
                                                    </span>
                                                    <span className="text-[14.5px] font-bold text-[#2F54C9]">
                                                        {row.value}
                                                    </span>
                                                </div>
                                            </Reveal>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </Container>
                    </section>

                    <FaqSection
                        items={faqs}
                        eyebrow="Pusat Bantuan Partner"
                        title="Jawaban untuk memulai dengan yakin."
                    />

                    {/* ── CTA ── */}
                    <section
                        id="join"
                        className="scroll-mt-28 relative overflow-hidden bg-gradient-to-br from-[#0E1A3A] to-[#2F54C9] py-20 lg:py-28"
                    >
                        <div
                            aria-hidden
                            className="pointer-events-none absolute top-0 right-0 h-[400px] w-[400px] translate-x-1/3 -translate-y-1/4 rounded-full bg-white/5 blur-[120px]"
                        />
                        <div
                            aria-hidden
                            className="pointer-events-none absolute bottom-0 left-0 h-[300px] w-[300px] -translate-x-1/3 translate-y-1/4 rounded-full bg-white/5 blur-[100px]"
                        />

                        <Container className="relative">
                            <div className="mx-auto max-w-2xl text-center">
                                <Reveal>
                                    <span className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-[12px] font-bold tracking-[0.08em] text-blue-200 uppercase backdrop-blur-sm">
                                        <ShieldCheck className="h-3.5 w-3.5" />
                                        AvanaHR Partner
                                    </span>
                                </Reveal>

                                <Reveal delay={0.05}>
                                    <h2 className="mt-5 text-[28px] leading-[1.1] font-bold tracking-[-0.02em] text-white sm:text-[40px] lg:text-[44px]">
                                        Siap membangun peluang bersama AvanaHR?
                                    </h2>
                                </Reveal>

                                <Reveal delay={0.1}>
                                    <p className="mx-auto mt-4 max-w-lg text-[16px] leading-relaxed text-blue-100/80">
                                        Gabung sebagai partner dan mulai
                                        rekomendasikan solusi HR kepada jaringan
                                        Anda.
                                    </p>
                                </Reveal>

                                <Reveal delay={0.15}>
                                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                                        <Link
                                            href={partnerRegistration.create().url}
                                            className="inline-flex h-12 items-center gap-2 rounded-full bg-white px-7 text-[14px] font-bold text-[#0E1A3A] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-50 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                                        >
                                            Gabung Jadi Partner
                                            <ArrowRight className="h-4 w-4" />
                                        </Link>
                                    </div>
                                </Reveal>
                            </div>
                        </Container>
                    </section>
                </main>

                <SiteFooter brand={brand} logo={logo} anchorPrefix="/" />
                <WhatsAppFab />
            </div>
        </>
    );
}
