import { ChevronDown } from 'lucide-react';
import type { Faq } from './content';
import { FAQS } from './content';
import { Container, Reveal, SectionHeading } from './reveal';

/**
 * FAQ. Built on native `<details>` so it is keyboard- and screen-reader
 * friendly without any extra ARIA wiring, and works before hydration.
 *
 * Defaults to the main landing questions; feature pages pass their own set.
 */
export function FaqSection({
    items = FAQS,
    eyebrow = 'FAQ',
    title = 'Pertanyaan yang Sering Diajukan',
}: {
    items?: readonly Faq[];
    eyebrow?: string;
    title?: string;
} = {}) {
    return (
        <section
            id="faq"
            className="scroll-mt-28 border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-28"
        >
            <Container>
                <SectionHeading eyebrow={eyebrow} title={title} />

                <Reveal className="mx-auto mt-12 max-w-3xl">
                    <div className="divide-y divide-[#EDF1F8] overflow-hidden rounded-2xl border border-[#E7ECF5] bg-white shadow-soft">
                        {items.map((faq) => (
                            <details
                                key={faq.q}
                                className="group px-6 transition-colors open:bg-[#FAFBFE]"
                            >
                                <summary className="flex cursor-pointer list-none items-center justify-between gap-4 py-5 text-left text-[16px] font-semibold text-[#0E1A3A] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9]">
                                    {faq.q}
                                    <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[#EEF2FB] text-[#2F54C9] transition-colors group-open:bg-[#2F54C9] group-open:text-white">
                                        <ChevronDown
                                            className="h-4 w-4 transition-transform duration-200 group-open:rotate-180"
                                            aria-hidden
                                        />
                                    </span>
                                </summary>
                                <p className="pr-10 pb-5 text-[15px] leading-relaxed text-[#5B6478]">
                                    {faq.a}
                                </p>
                            </details>
                        ))}
                    </div>
                </Reveal>
            </Container>
        </section>
    );
}
