import { CheckCircle2, Sparkles, Star, TrendingUp } from 'lucide-react';
import { motion } from 'motion/react';
import { DemoButton } from './cta-buttons';
import { Container, Reveal } from './reveal';
import { useCtaTargets } from './use-cta';
import { WhatsAppIcon } from './whatsapp-fab';

const FEATURE_PILLS = [
    'PPh 21 TER 2024 & BPJS',
    'Live GPS Attendance',
    'AI Workforce Intelligence',
    'Multi-Branch Ready',
];

const REVIEWER_AVATARS = [
    'https://ik.imagekit.io/b8izy6rik/hrd2.png',
    'https://ik.imagekit.io/b8izy6rik/hrd3.png',
    'https://ik.imagekit.io/b8izy6rik/hrd4.png',
    'https://ik.imagekit.io/b8izy6rik/hrd1.png',
];

/**
 * Hero — copy on the left, a large product/people visual on the right with
 * floating stat badges. Ported 1:1 from the AvanaHR reference site (copy,
 * gradient wash, floating cards); CTAs stay wired to the real product
 * destinations (DemoButton, WhatsApp).
 */
export function HeroSection() {
    const { whatsappWith } = useCtaTargets();
    const whatsappHref = whatsappWith(
        'Halo AvanaHR, saya ingin mengetahui lebih lanjut mengenai platform HRIS AvanaHR.',
    );

    return (
        <section
            id="hero"
            className="relative -mt-[68px] overflow-hidden pt-[68px] lg:-mt-20 lg:pt-20"
        >
            {/* Backdrop: gradient wash + soft radial blobs, all decorative. */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(135deg,#EAF0FF_0%,#F4F7FC_45%,#DDEAFF_100%)]"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute top-0 right-0 -z-10 h-[800px] w-[800px] -translate-y-[30%] translate-x-[30%] rounded-full bg-[radial-gradient(circle,rgba(49,95,212,0.12)_0%,transparent_70%)]"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute bottom-0 left-0 -z-10 h-[600px] w-[600px] -translate-x-[30%] translate-y-[30%] rounded-full bg-[radial-gradient(circle,rgba(16,42,92,0.06)_0%,transparent_70%)]"
            />

            <Container className="relative pt-12 pb-14 sm:pt-16 lg:pt-20 lg:pb-20">
                <div className="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-14">
                    <div className="text-center lg:text-left">
                        <Reveal>
                            <p className="inline-flex items-center gap-2 rounded-full border border-[#DDE5F5] bg-white/80 px-4 py-2 text-xs font-semibold tracking-wide text-avana-navy shadow-avana-subtle backdrop-blur sm:text-sm">
                                <Sparkles
                                    className="h-4 w-4 shrink-0 text-avana-blue"
                                    aria-hidden
                                />
                                <span>AI-NATIVE HR &amp; WORKFORCE PLATFORM</span>
                            </p>
                        </Reveal>

                        <Reveal delay={0.05}>
                            <h1 className="mt-5 text-4xl leading-[1.08] font-bold tracking-tight text-avana-navy sm:text-5xl lg:text-[62px]">
                                HR Tidak Cukup <br />
                                <span className="text-avana-blue">
                                    Hanya Punya Data.
                                </span>
                            </h1>
                        </Reveal>

                        <Reveal delay={0.1}>
                            <p className="mx-auto mt-6 max-w-xl text-justify text-lg leading-relaxed text-avana-text sm:text-xl lg:mx-0">
                                AvanaHR adalah platform HRIS dan payroll
                                terintegrasi untuk perusahaan Indonesia.
                                Platform ini menghubungkan data karyawan,
                                absensi GPS dan shift, cuti, penggajian, PPh 21
                                TER, kinerja, rekrutmen, workforce analytics,
                                dan AI Assistant dalam satu sistem. Tim HR dapat
                                mengurangi pekerjaan administratif, menjaga data
                                tetap terpusat, dan memperoleh insight untuk
                                keputusan workforce yang lebih baik.
                            </p>
                        </Reveal>

                        <Reveal delay={0.13}>
                            <div className="mt-6 flex flex-wrap justify-center gap-2 text-xs font-semibold lg:justify-start">
                                {FEATURE_PILLS.map((feature) => (
                                    <span
                                        key={feature}
                                        className="inline-flex items-center gap-1.5 rounded-full border border-avana-border bg-white/80 px-3 py-1.5 text-avana-navy shadow-avana-subtle"
                                    >
                                        <CheckCircle2
                                            className="h-3.5 w-3.5 shrink-0 text-emerald-500"
                                            aria-hidden
                                        />
                                        {feature}
                                    </span>
                                ))}
                            </div>
                        </Reveal>

                        <Reveal delay={0.18}>
                            <div className="mt-6 flex w-full flex-col items-center justify-center gap-3 sm:w-auto sm:flex-row lg:justify-start">
                                <DemoButton
                                    className="w-full sm:w-auto"
                                    withArrow
                                >
                                    Lihat Demo AvanaHR
                                </DemoButton>
                                {whatsappHref && (
                                    <a
                                        href={whatsappHref}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex h-11 w-full items-center justify-center gap-2.5 rounded-full border border-avana-border bg-white/90 px-7 text-[15px] font-semibold text-avana-navy shadow-avana-subtle backdrop-blur transition-colors duration-200 hover:border-[#25D366] hover:bg-white sm:h-12 sm:w-auto"
                                    >
                                        <WhatsAppIcon className="h-5 w-5 text-[#25D366]" />
                                        Chat dengan AvanaHR
                                    </a>
                                )}
                            </div>
                        </Reveal>

                        <Reveal delay={0.22}>
                            <div className="mt-6 flex flex-col items-center gap-4 border-t border-avana-border/40 pt-4 sm:flex-row sm:justify-center lg:justify-start">
                                <div className="flex -space-x-2">
                                    {REVIEWER_AVATARS.map((src, i) => (
                                        <img
                                            key={src}
                                            src={src}
                                            alt={`HR Profesional Indonesia ${i + 1}`}
                                            loading="lazy"
                                            className="inline-block h-9 w-9 rounded-full object-cover ring-2 ring-white"
                                        />
                                    ))}
                                </div>
                                <div className="flex flex-col items-center sm:items-start">
                                    <div className="flex items-center gap-0.5 text-amber-400">
                                        {Array.from({ length: 5 }).map(
                                            (_, i) => (
                                                <Star
                                                    key={i}
                                                    className="h-3.5 w-3.5 fill-current"
                                                    aria-hidden
                                                />
                                            ),
                                        )}
                                        <span className="ml-2 text-xs font-bold text-avana-navy">
                                            Platform HR Modern Indonesia
                                        </span>
                                    </div>
                                    <p className="mt-0.5 text-[11px] text-avana-muted">
                                        Dirancang khusus untuk manajemen &amp;
                                        tim HR di Indonesia
                                    </p>
                                </div>
                            </div>
                        </Reveal>
                    </div>

                    <motion.div
                        data-reveal
                        className="relative flex items-end justify-center lg:-mr-10 lg:self-end xl:-mr-16"
                        initial={{ opacity: 0, y: 28 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{
                            duration: 0.7,
                            delay: 0.15,
                            ease: [0.22, 1, 0.36, 1],
                        }}
                    >
                        <div
                            aria-hidden
                            className="pointer-events-none absolute bottom-0 left-1/2 h-[420px] w-[420px] -translate-x-1/2 rounded-full bg-[radial-gradient(circle,rgba(49,95,212,0.15)_0%,transparent_70%)] sm:h-[520px] sm:w-[520px]"
                        />

                        <img
                            src="/avana/landing/images/22.png"
                            alt="Tim Profesional HR Indonesia — AvanaHR"
                            className="relative z-10 block w-full max-w-[520px] object-contain object-bottom select-none sm:max-w-[620px]"
                            style={{ maxHeight: '80vh', minHeight: '260px' }}
                            fetchPriority="high"
                            decoding="async"
                            draggable={false}
                        />

                        <div className="absolute top-16 right-0 z-20 flex max-w-[210px] items-center gap-3 rounded-2xl border border-avana-border bg-white/95 p-3 shadow-avana-hover backdrop-blur-sm animate-float sm:p-4 xl:-right-20">
                            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-avana-light text-avana-blue">
                                <TrendingUp className="h-5 w-5" aria-hidden />
                            </div>
                            <div>
                                <div className="text-xs font-bold text-avana-navy">
                                    Workforce Analytics
                                </div>
                                <div className="text-[10px] text-avana-muted">
                                    Data terhubung 1 platform
                                </div>
                            </div>
                        </div>

                        <div className="absolute top-[35%] left-0 z-20 flex max-w-[210px] -translate-y-1/2 items-center gap-3 rounded-2xl border border-avana-border bg-white/95 p-3 shadow-avana-hover backdrop-blur-sm animate-float-slow sm:p-4 xl:-left-8">
                            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-avana-blue text-white">
                                <Sparkles className="h-5 w-5" aria-hidden />
                            </div>
                            <div>
                                <div className="text-xs font-bold text-avana-navy">
                                    AI + Insight
                                </div>
                                <div className="text-[10px] text-avana-muted">
                                    Bantu HR baca kondisi tim
                                </div>
                            </div>
                        </div>

                        <div className="absolute right-2 bottom-4 z-20 flex items-center gap-3 rounded-2xl border border-avana-border bg-white/95 px-4 py-3 shadow-avana-hover backdrop-blur-sm xl:-right-2">
                            <span className="h-2.5 w-2.5 shrink-0 animate-pulse rounded-full bg-emerald-500" />
                            <div>
                                <div className="text-xs font-extrabold text-avana-navy">
                                    Payroll &amp; TER PPh 21
                                </div>
                                <div className="text-[10px] font-semibold text-emerald-600">
                                    100% Otomatis &amp; Akurat
                                </div>
                            </div>
                        </div>
                    </motion.div>
                </div>
            </Container>
        </section>
    );
}
