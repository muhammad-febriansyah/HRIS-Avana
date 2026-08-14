import { Plus } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import type { Solution } from './content';
import { SOLUTIONS } from './content';
import { Container, Reveal, SectionHeading } from './reveal';

/**
 * One bento cell. Only three to five highlights are shown up front; the rest
 * stay behind a disclosure button so the grid never turns into a menu dump.
 */
function SolutionCard({
    solution,
    featured = false,
    className,
}: {
    solution: Solution;
    featured?: boolean;
    className?: string;
}) {
    const [expanded, setExpanded] = useState(false);
    const panelId = `solusi-${solution.id}-lainnya`;

    return (
        <div
            className={cn(
                'group relative flex h-full flex-col overflow-hidden rounded-2xl border border-[#E7ECF5] bg-white p-6 transition-[border-color,box-shadow] duration-200 hover:border-[#C9D6F0] hover:shadow-lift lg:p-7',
                className,
            )}
        >
            {featured && (
                <div
                    aria-hidden
                    className="pointer-events-none absolute -top-24 -right-24 h-48 w-48 rounded-full bg-[radial-gradient(closest-side,rgba(47,84,201,0.10),transparent)]"
                />
            )}
            <div className="relative flex items-center gap-3">
                <span className="grid h-11 w-11 place-items-center rounded-xl bg-[#EEF2FB] text-[#2F54C9] transition-colors duration-200 group-hover:bg-[#2F54C9] group-hover:text-white">
                    <solution.icon className="h-5 w-5" aria-hidden />
                </span>
                <span className="text-[12px] font-semibold tracking-widest text-[#B6BFD2]">
                    {solution.number}
                </span>
            </div>

            <h3 className="relative mt-5 text-[19px] font-semibold text-[#0E1A3A]">
                {solution.title}
            </h3>
            <p className="relative mt-2 text-[14px] leading-relaxed text-[#5B6478]">
                {solution.copy}
            </p>

            <ul className="relative mt-5 flex flex-wrap gap-2">
                {solution.highlights.map((item) => (
                    <li
                        key={item}
                        className="rounded-lg border border-[#EDF1F8] bg-[#F8FAFD] px-2.5 py-1.5 text-[12.5px] font-medium text-[#3B455C]"
                    >
                        {item}
                    </li>
                ))}
                {expanded &&
                    solution.more.map((item) => (
                        <li
                            key={item}
                            className="rounded-lg border border-[#EDF1F8] bg-[#F8FAFD] px-2.5 py-1.5 text-[12.5px] font-medium text-[#3B455C]"
                        >
                            {item}
                        </li>
                    ))}
            </ul>

            {solution.more.length > 0 && (
                <button
                    type="button"
                    aria-expanded={expanded}
                    aria-controls={panelId}
                    onClick={() => setExpanded((open) => !open)}
                    className="relative mt-auto inline-flex w-fit cursor-pointer items-center gap-1.5 rounded-md pt-5 text-[13px] font-semibold text-[#2F54C9] transition-colors hover:text-[#2546AD] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9]"
                >
                    <Plus
                        className={cn(
                            'h-3.5 w-3.5 transition-transform duration-200',
                            expanded && 'rotate-45',
                        )}
                        aria-hidden
                    />
                    {expanded
                        ? 'Tampilkan lebih sedikit'
                        : `Lihat selengkapnya (${solution.more.length})`}
                </button>
            )}
            <span id={panelId} className="sr-only">
                {solution.more.join(', ')}
            </span>
        </div>
    );
}

/** Section 14 — the six product areas as a bento grid. */
export function SolutionBento() {
    return (
        <section
            id="solusi"
            className="scroll-mt-28 border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-28"
        >
            <Container>
                <SectionHeading
                    eyebrow="Cakupan platform"
                    title="Semua yang HR Butuhkan. Dalam Satu Platform."
                    description="Enam area yang saling terhubung — data karyawan, layanan mandiri, payroll, talenta, analitik dan AI."
                />

                <div className="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    {SOLUTIONS.map((solution, i) => (
                        <Reveal
                            key={solution.id}
                            delay={(i % 3) * 0.05}
                            className={cn(
                                'lg:col-span-2',
                                // First and last cells run wider so the grid
                                // reads as a bento instead of six identical
                                // cards.
                                i === 0 && 'lg:col-span-4',
                                i === 5 && 'sm:col-span-2 lg:col-span-6',
                            )}
                        >
                            <SolutionCard
                                solution={solution}
                                featured={i === 0 || i === 5}
                            />
                        </Reveal>
                    ))}
                </div>
            </Container>
        </section>
    );
}
