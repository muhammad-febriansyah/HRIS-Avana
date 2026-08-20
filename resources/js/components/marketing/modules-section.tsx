import { ArrowRight, Check } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { SOLUTIONS } from './content';
import { DemoButton } from './cta-buttons';
import { Container, Reveal, SectionHeading } from './reveal';

/**
 * Section 14 (platform overview) — the six product areas as a clickable
 * module grid with a detail panel below. Same `SOLUTIONS` data as the bento
 * grid further down the page, but presented as an interactive picker so a
 * visitor can dwell on one module at a time before scrolling on.
 */
export function ModulesSection() {
    const [active, setActive] = useState(0);
    const current = SOLUTIONS[active];
    const features = [...current.highlights, ...current.more];

    return (
        <section
            id="platform"
            className="scroll-mt-28 py-20 lg:py-28"
        >
            <Container>
                <SectionHeading
                    eyebrow="Fitur & modul"
                    title="Satu Platform untuk Proses HR yang Terhubung."
                    description="Kelola seluruh siklus karyawan — dari rekrutmen hingga payroll dan analitik — tanpa berpindah-pindah aplikasi."
                />

                <div className="mt-12 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-6">
                    {SOLUTIONS.map((solution, index) => {
                        const isActive = index === active;

                        return (
                            <Reveal key={solution.id} delay={(index % 3) * 0.05}>
                                <button
                                    type="button"
                                    onClick={() => setActive(index)}
                                    aria-pressed={isActive}
                                    className={cn(
                                        'flex h-full w-full cursor-pointer flex-col justify-between rounded-2xl border p-5 text-left transition-[background-color,border-color,box-shadow,transform] duration-200',
                                        isActive
                                            ? 'scale-[1.02] border-[#0E1A3A] bg-gradient-to-b from-[#16234A] to-[#0E1A3A] text-white shadow-avana-hover'
                                            : 'border-[#E7ECF5] bg-white text-[#0E1A3A] hover:border-[#C9D6F0] hover:shadow-lift',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'grid h-11 w-11 place-items-center rounded-xl transition-colors duration-200',
                                            isActive
                                                ? 'bg-[#2F54C9] text-white'
                                                : 'bg-[#EEF2FB] text-[#2F54C9]',
                                        )}
                                    >
                                        <solution.icon
                                            className="h-5 w-5"
                                            aria-hidden
                                        />
                                    </span>
                                    <span
                                        className={cn(
                                            'mt-4 text-[15px] font-semibold',
                                            isActive
                                                ? 'text-white'
                                                : 'text-[#0E1A3A]',
                                        )}
                                    >
                                        {solution.title}
                                    </span>
                                </button>
                            </Reveal>
                        );
                    })}
                </div>

                <Reveal delay={0.1} className="mt-6">
                    <div className="rounded-[28px] border border-[#E7ECF5] bg-[#F8FAFD] p-6 sm:p-10">
                        <div className="grid gap-8 lg:grid-cols-12 lg:items-center">
                            <div className="lg:col-span-7">
                                <span className="inline-flex items-center rounded-full border border-[#E2E9F6] bg-white px-3.5 py-1 text-[12px] font-semibold tracking-[0.08em] text-[#2F54C9] uppercase">
                                    Modul {current.number}
                                </span>
                                <h3 className="mt-4 text-2xl font-bold tracking-[-0.01em] text-[#0E1A3A] sm:text-[28px]">
                                    {current.title}
                                </h3>
                                <p className="mt-3 text-[15px] leading-relaxed text-[#5B6478] sm:text-[16px]">
                                    {current.copy}
                                </p>
                                <div className="mt-6">
                                    <DemoButton variant="primary" />
                                </div>
                            </div>

                            <div className="lg:col-span-5">
                                <ul className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-1">
                                    {features.map((feature) => (
                                        <li
                                            key={feature}
                                            className="flex items-center gap-2.5 rounded-xl border border-[#EDF1F8] bg-white px-3.5 py-2.5 text-[13.5px] font-medium text-[#3B455C]"
                                        >
                                            <span className="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#E7F6EE] text-[#1F9D55]">
                                                <Check
                                                    className="h-3 w-3"
                                                    aria-hidden
                                                />
                                            </span>
                                            {feature}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </div>
                </Reveal>

                <Reveal delay={0.15} className="mt-6 flex justify-center">
                    <a
                        href="#solusi"
                        className="inline-flex items-center gap-1.5 text-[13.5px] font-semibold text-[#2F54C9] hover:text-[#2546AD]"
                    >
                        Lihat detail semua modul
                        <ArrowRight className="h-3.5 w-3.5" aria-hidden />
                    </a>
                </Reveal>
            </Container>
        </section>
    );
}
