import { Sparkles } from 'lucide-react';
import { motion } from 'motion/react';
import { DemoButton, TrialButton } from './cta-buttons';
import { PriceNote } from './price-note';
import { Container, Reveal } from './reveal';

/**
 * Hero — copy on the left, product visual on the right.
 *
 * The visual is a screenshot of the real dashboard, captured from the demo
 * tenant with names, e-mails and the tenant logo replaced (see
 * `public/avana/landing/screenshots/`). It ships with its own browser chrome
 * here so it reads as an application window.
 */
export function HeroSection() {
    return (
        <section className="relative -mt-[68px] overflow-hidden pt-[68px] lg:-mt-20 lg:pt-20">
            {/* Backdrop: soft brand wash + faint grid, both decorative. */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 -top-24 h-[780px] bg-[radial-gradient(70%_60%_at_12%_18%,rgba(47,84,201,0.10),transparent_70%),radial-gradient(48%_46%_at_88%_8%,rgba(110,155,230,0.16),transparent_70%)]"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 -top-20 h-[720px] [mask-image:linear-gradient(to_bottom,black,transparent_78%)]"
                style={{
                    backgroundImage:
                        'linear-gradient(to right, rgba(14,26,58,0.035) 1px, transparent 1px), linear-gradient(to bottom, rgba(14,26,58,0.035) 1px, transparent 1px)',
                    backgroundSize: '52px 52px',
                }}
            />

            <Container className="relative pt-12 pb-14 sm:pt-16 lg:pt-20 lg:pb-20">
                <div className="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-14">
                    <div className="text-center lg:text-left">
                        <Reveal>
                            <p className="inline-flex items-center gap-2 rounded-full border border-[#DDE5F5] bg-white/80 py-1.5 pr-4 pl-2 text-[12.5px] font-semibold text-[#2F54C9] shadow-soft backdrop-blur sm:text-[13px]">
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[#2F54C9] px-2 py-1 text-[10.5px] font-bold tracking-[0.06em] text-white uppercase">
                                    <Sparkles className="h-3 w-3" aria-hidden />
                                    AvanaHR
                                </span>
                                Manage People. Simplify HR. Empower Your People.
                            </p>
                        </Reveal>

                        <Reveal delay={0.05}>
                            <h1 className="mt-5 text-[32px] leading-[1.14] font-bold tracking-[-0.025em] text-[#0E1A3A] sm:text-[38px] lg:text-[36px] xl:text-[40px]">
                                <span className="block">
                                    HR Lebih Sederhana.
                                </span>
                                <span className="block">
                                    Karyawan Lebih Terlayani.
                                </span>
                                <span className="block text-[#2F54C9]">
                                    Bisnis Lebih Terkendali.
                                </span>
                            </h1>
                        </Reveal>

                        <Reveal delay={0.1}>
                            <p className="mx-auto mt-6 max-w-xl text-[15.5px] leading-relaxed text-pretty text-[#5B6478] sm:text-[17px] lg:mx-0">
                                AvanaHR menyatukan HR Management, Attendance,
                                Payroll, Finance, Talent Management, Employee
                                Self Service, hingga Analytics dalam satu
                                platform.
                            </p>
                        </Reveal>

                        <Reveal delay={0.15}>
                            <div className="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start">
                                <DemoButton className="w-full sm:w-auto" />
                                <TrialButton className="w-full sm:w-auto" />
                            </div>
                        </Reveal>

                        <Reveal delay={0.2}>
                            <div className="mt-4 flex justify-center lg:-ml-3.5 lg:justify-start">
                                <PriceNote />
                            </div>
                        </Reveal>
                    </div>

                    <motion.div
                        data-reveal
                        className="relative lg:-mr-10 xl:-mr-20"
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
                            className="pointer-events-none absolute inset-x-8 top-10 bottom-8 rounded-[2rem] bg-[radial-gradient(60%_50%_at_50%_50%,rgba(47,84,201,0.16),transparent)] blur-2xl"
                        />
                        <figure className="overflow-hidden rounded-2xl border border-[#E1E7F2] bg-white shadow-frame">
                            <div className="flex items-center gap-2 border-b border-[#F0F3F9] bg-[#FAFBFE] px-4 py-3">
                                {[0, 1, 2].map((dot) => (
                                    <span
                                        key={dot}
                                        aria-hidden
                                        className="h-2.5 w-2.5 rounded-full bg-[#E4E9F2]"
                                    />
                                ))}
                                <span className="mx-auto hidden h-6 w-full max-w-[240px] items-center justify-center rounded-md border border-[#EDF1F7] bg-white text-[10px] text-[#9CA3AF] sm:flex">
                                    Dashboard HR
                                </span>
                            </div>
                            <img
                                src="/avana/landing/screenshots/dashboard-hero.png"
                                alt="Dashboard HR AvanaHR: total karyawan, kehadiran, pending approval, payroll berjalan dan antrean persetujuan"
                                width={1180}
                                height={780}
                                fetchPriority="high"
                                decoding="async"
                                className="block w-full"
                            />
                        </figure>
                        <p className="mt-4 text-center text-[12px] text-[#8A93A6]">
                            Tangkapan layar aplikasi dengan data demo.
                        </p>
                    </motion.div>
                </div>
            </Container>
        </section>
    );
}
