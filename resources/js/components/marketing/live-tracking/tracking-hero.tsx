import { Clock } from 'lucide-react';
import { motion } from 'motion/react';
import { DemoButton } from '../cta-buttons';
import { Container, Reveal } from '../reveal';
import { VALUE_STRIP } from './content';
import { TrackingDashboard } from './tracking-dashboard';

/**
 * Hero for the Live Tracking page — copy, both CTAs, then the product mockup
 * as the focal point, with the short value strip beneath it.
 */
export function TrackingHero() {
    return (
        <section className="relative -mt-[68px] overflow-hidden pt-[68px] lg:-mt-20 lg:pt-20">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 -top-24 h-[720px] bg-[radial-gradient(65%_55%_at_50%_12%,rgba(47,84,201,0.12),transparent_70%),radial-gradient(40%_40%_at_88%_6%,rgba(22,163,74,0.10),transparent_70%)]"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 -top-20 h-[680px] [mask-image:linear-gradient(to_bottom,black,transparent_80%)]"
                style={{
                    backgroundImage:
                        'linear-gradient(to right, rgba(14,26,58,0.035) 1px, transparent 1px), linear-gradient(to bottom, rgba(14,26,58,0.035) 1px, transparent 1px)',
                    backgroundSize: '52px 52px',
                }}
            />

            <Container className="relative pt-12 pb-16 sm:pt-16 lg:pt-20 lg:pb-24">
                <div className="mx-auto max-w-3xl text-center">
                    <Reveal>
                        <p className="text-[13px] font-semibold text-[#2F54C9] sm:text-[14px]">
                            AvanaHR Live Tracking
                        </p>
                    </Reveal>

                    <Reveal delay={0.05}>
                        <h1 className="mt-5 text-[32px] leading-[1.12] font-bold tracking-[-0.025em] text-[#0E1A3A] sm:text-[42px] lg:text-[50px]">
                            <span className="block">
                                Pantau Aktivitas Karyawan Lapangan
                            </span>
                            <span className="block text-[#2F54C9]">
                                Secara Real-Time.
                            </span>
                        </h1>
                    </Reveal>

                    <Reveal delay={0.1}>
                        <p className="mx-auto mt-6 max-w-2xl text-[15.5px] leading-relaxed text-pretty text-[#5B6478] sm:text-[17px]">
                            Lihat posisi karyawan selama jam kerja, pantau
                            perjalanan, jarak tempuh, dan riwayat aktivitas
                            lapangan dalam satu dashboard AvanaHR.
                        </p>
                    </Reveal>

                    <Reveal delay={0.15}>
                        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <DemoButton className="w-full sm:w-auto" />
                            <a
                                href="#cara-kerja"
                                className="inline-flex h-12 w-full items-center justify-center rounded-full border border-[#D9E0EE] bg-white px-7 text-[15px] font-semibold text-[#0E1A3A] transition-colors duration-200 hover:border-[#2F54C9] hover:text-[#2F54C9] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9] sm:w-auto"
                            >
                                Lihat Cara Kerjanya
                            </a>
                        </div>
                    </Reveal>

                    <Reveal delay={0.2}>
                        <p className="mt-5 inline-flex items-center gap-2 rounded-full border border-[#E2E9F6] bg-white/80 px-3.5 py-1.5 text-[12.5px] text-[#5B6478]">
                            <Clock
                                className="h-3.5 w-3.5 text-[#2F54C9]"
                                aria-hidden
                            />
                            Tracking aktif selama sesi kerja dari Clock In
                            hingga Clock Out.
                        </p>
                    </Reveal>
                </div>

                <motion.div
                    data-reveal
                    className="relative mx-auto mt-12 max-w-5xl lg:mt-16"
                    initial={{ opacity: 0, y: 28 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{
                        duration: 0.7,
                        delay: 0.15,
                        ease: [0.22, 1, 0.36, 1],
                    }}
                >
                    <TrackingDashboard />
                    <p className="mt-4 text-center text-[12px] text-[#8A93A6]">
                        Ilustrasi antarmuka Live Tracking — nama dan angka pada
                        contoh ini bukan data pengguna.
                    </p>
                </motion.div>

                <Reveal delay={0.1}>
                    <ul className="mt-10 flex flex-wrap items-center justify-center gap-x-3 gap-y-2.5">
                        {VALUE_STRIP.map((item) => (
                            <li
                                key={item.label}
                                className="inline-flex items-center gap-2 rounded-full border border-[#E4EAF5] bg-white px-3.5 py-2 text-[13px] font-medium text-[#3B455C]"
                            >
                                <item.icon
                                    className="h-4 w-4 text-[#2F54C9]"
                                    aria-hidden
                                />
                                {item.label}
                            </li>
                        ))}
                    </ul>
                </Reveal>
            </Container>
        </section>
    );
}
