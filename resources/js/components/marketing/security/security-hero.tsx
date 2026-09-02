import { ShieldCheck } from 'lucide-react';
import { motion } from 'motion/react';
import { DemoButton } from '../cta-buttons';
import { Container, Reveal } from '../reveal';
import { HERO_HIGHLIGHTS, HERO_IMAGE } from './content';

/**
 * Hero for the Keamanan (Security) page — copy on the left, the layered
 * security illustration on the right. Same left-right grid as the main
 * landing hero, so the page reads as AvanaHR's own rather than a stacked
 * feature-page hero.
 */
export function SecurityHero() {
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
                <div className="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-14">
                    <div className="text-center lg:text-left">
                        <Reveal>
                            <span className="inline-flex items-center gap-2 rounded-full border border-[#E2E9F6] bg-[#F4F7FD] px-3.5 py-1 text-[12px] font-semibold tracking-[0.08em] text-[#2F54C9] uppercase">
                                <ShieldCheck
                                    className="h-3.5 w-3.5"
                                    aria-hidden
                                />
                                Keamanan Avana HR
                            </span>
                        </Reveal>

                        <Reveal delay={0.05}>
                            <h1 className="mt-5 text-[32px] leading-[1.12] font-bold tracking-[-0.025em] text-[#0E1A3A] sm:text-[42px] lg:text-[48px]">
                                Perlindungan Berlapis untuk{' '}
                                <span className="text-[#2F54C9]">
                                    Data dan Proses HR
                                </span>{' '}
                                Perusahaan Anda.
                            </h1>
                        </Reveal>

                        <Reveal delay={0.1}>
                            <p className="mx-auto mt-6 max-w-xl text-[15.5px] leading-relaxed text-pretty text-[#5B6478] sm:text-[17px] lg:mx-0">
                                Autentikasi, enkripsi, hak akses, hingga audit
                                trail — dibangun dengan prinsip{' '}
                                <strong className="font-semibold text-[#0E1A3A]">
                                    security by design
                                </strong>
                                .
                            </p>
                        </Reveal>

                        <Reveal delay={0.15}>
                            <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row lg:justify-start">
                                <DemoButton className="w-full sm:w-auto" />
                                <a
                                    href="#akun-sesi"
                                    className="inline-flex h-12 w-full items-center justify-center rounded-full border border-[#D9E0EE] bg-white px-7 text-[15px] font-semibold text-[#0E1A3A] transition-colors duration-200 hover:border-[#2F54C9] hover:text-[#2F54C9] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9] sm:w-auto"
                                >
                                    Lihat Lapisan Keamanannya
                                </a>
                            </div>
                        </Reveal>

                        <Reveal delay={0.2}>
                            <ul className="mt-8 flex flex-wrap items-center justify-center gap-x-2.5 gap-y-2 lg:justify-start">
                                {HERO_HIGHLIGHTS.map((item) => (
                                    <li
                                        key={item.label}
                                        className="inline-flex items-center gap-1.5 rounded-full border border-[#E4EAF5] bg-white px-3 py-1.5 text-[12.5px] font-medium text-[#3B455C]"
                                    >
                                        <item.icon
                                            className="h-3.5 w-3.5 text-[#2F54C9]"
                                            aria-hidden
                                        />
                                        {item.label}
                                    </li>
                                ))}
                            </ul>
                        </Reveal>
                    </div>

                    <motion.div
                        data-reveal
                        className="relative mx-auto flex max-w-md items-center justify-center lg:max-w-none"
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
                            className="pointer-events-none absolute inset-0 rounded-full bg-[radial-gradient(circle,rgba(49,95,212,0.16)_0%,transparent_70%)]"
                        />
                        <img
                            src={HERO_IMAGE}
                            alt="Ilustrasi lapisan keamanan Avana HR — login, verifikasi, dan manajemen perangkat"
                            className="relative z-10 block w-full max-w-[560px] object-contain select-none"
                            fetchPriority="high"
                            decoding="async"
                            draggable={false}
                        />
                    </motion.div>
                </div>
            </Container>
        </section>
    );
}
