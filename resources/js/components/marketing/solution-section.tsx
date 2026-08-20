import { CheckCircle2, ChevronRight, Cpu } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { SOLUTIONS } from './content';
import { Container, Reveal, SectionHeading } from './reveal';

/**
 * Section 14 — the six product areas, shown as a connected pipeline of
 * steps. Clicking a step swaps the deep-dive panel below it, so the section
 * reads as "one platform, six connected areas" rather than a static grid.
 */
export function SolutionSection() {
    const [active, setActive] = useState(2);
    const current = SOLUTIONS[active];

    return (
        <section
            id="solusi"
            className="scroll-mt-28 relative overflow-hidden border-y border-[#EDF1F8] bg-white py-20 lg:py-28"
        >
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 bg-[radial-gradient(#D8E0EF_1px,transparent_1px)] [background-size:24px_24px] opacity-40"
            />

            <Container className="relative">
                <SectionHeading
                    eyebrow="Cakupan Platform"
                    title={
                        <>
                            Dari Proses HR Menjadi Data.
                            <br className="hidden sm:inline" />
                            <span className="text-[#2F54C9]">
                                Dari Data Menjadi Insight.
                            </span>
                        </>
                    }
                    description="Enam area yang saling terhubung — data karyawan, layanan mandiri, payroll, talenta, analitik dan AI — dalam satu platform terpadu."
                />

                <Reveal className="mt-14" delay={0.05}>
                    <div className="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-6">
                        {SOLUTIONS.map((solution, idx) => {
                            const isActive = active === idx;

                            return (
                                <div
                                    key={solution.id}
                                    className="relative flex flex-col items-center"
                                >
                                    <button
                                        type="button"
                                        onClick={() => setActive(idx)}
                                        aria-pressed={isActive}
                                        className={cn(
                                            'w-full cursor-pointer rounded-2xl border p-4 text-left transition-all duration-300 sm:p-5',
                                            isActive
                                                ? 'z-10 scale-[1.03] border-[#0E1A3A] bg-[#0E1A3A] text-white shadow-lift'
                                                : 'border-[#E7ECF5] bg-[#F8FAFD] text-[#0E1A3A] hover:bg-[#EEF2FB]',
                                        )}
                                    >
                                        <div className="mb-3 flex items-center justify-between">
                                            <span
                                                className={cn(
                                                    'grid h-9 w-9 place-items-center rounded-xl',
                                                    isActive
                                                        ? 'bg-[#2F54C9] text-white'
                                                        : 'bg-white text-[#2F54C9] shadow-sm',
                                                )}
                                            >
                                                <solution.icon
                                                    className="h-5 w-5"
                                                    aria-hidden
                                                />
                                            </span>
                                            <span
                                                className={cn(
                                                    'rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase',
                                                    isActive
                                                        ? 'bg-white/20 text-white'
                                                        : 'bg-[#EEF2FB] text-[#0E1A3A]',
                                                )}
                                            >
                                                {solution.number}
                                            </span>
                                        </div>

                                        <div className="truncate text-[13.5px] font-bold">
                                            {solution.title}
                                        </div>
                                    </button>

                                    {idx < SOLUTIONS.length - 1 && (
                                        <div className="pointer-events-none absolute top-1/2 -right-3 z-20 hidden -translate-y-1/2 text-[#B6BFD2] md:flex">
                                            <ChevronRight
                                                className="h-5 w-5"
                                                aria-hidden
                                            />
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </Reveal>

                <Reveal delay={0.1} className="mt-8">
                    <div className="rounded-3xl border border-[#EDF1F8] bg-gradient-to-br from-[#F8FAFD] via-white to-[#EEF2FB] p-6 shadow-avana-card sm:p-10">
                        <div className="grid grid-cols-1 items-center gap-8 lg:grid-cols-12">
                            <div className="lg:col-span-7">
                                <div className="inline-flex items-center gap-2 rounded-full bg-[#2F54C9]/10 px-3 py-1 text-[12px] font-bold tracking-wide text-[#2F54C9] uppercase">
                                    <Cpu className="h-3.5 w-3.5" aria-hidden />
                                    <span>{current.title}</span>
                                </div>
                                <h3 className="mt-4 text-2xl font-bold text-[#0E1A3A] sm:text-3xl">
                                    {current.title}
                                </h3>
                                <p className="mt-3 text-[15px] leading-relaxed text-[#5B6478] sm:text-[17px]">
                                    {current.copy}
                                </p>
                            </div>

                            <div className="space-y-3 rounded-2xl border border-[#EDF1F8] bg-white p-5 shadow-sm lg:col-span-5 sm:p-6">
                                <div className="text-[12px] font-bold tracking-wider text-[#0E1A3A] uppercase">
                                    Yang tercakup:
                                </div>
                                <ul className="space-y-2.5 text-[14px] text-[#3B455C]">
                                    {current.highlights.map((item) => (
                                        <li
                                            key={item}
                                            className="flex items-start gap-2"
                                        >
                                            <CheckCircle2
                                                className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600"
                                                aria-hidden
                                            />
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </div>
                </Reveal>
            </Container>
        </section>
    );
}
