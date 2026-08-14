import { CONTROL_POINTS } from './content';
import { Container, Reveal, SectionHeading } from './reveal';

/** Control & flexibility — a minimal 2×2 grid of icon + short copy. */
export function ControlSection() {
    return (
        <section className="border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-24">
            <Container>
                <SectionHeading
                    eyebrow="Kontrol"
                    title="Tetap Fleksibel. Tetap Terkontrol."
                />

                <ul className="mt-12 grid gap-x-8 gap-y-8 sm:grid-cols-2">
                    {CONTROL_POINTS.map((point, i) => (
                        <Reveal
                            as="li"
                            key={point.title}
                            delay={(i % 2) * 0.06}
                            className="flex gap-4"
                        >
                            <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-[#E7ECF5] bg-white text-[#2F54C9] shadow-soft">
                                <point.icon className="h-5 w-5" aria-hidden />
                            </span>
                            <div>
                                <h3 className="text-[16px] font-semibold text-[#0E1A3A]">
                                    {point.title}
                                </h3>
                                <p className="mt-1.5 text-[14px] leading-relaxed text-[#5B6478]">
                                    {point.body}
                                </p>
                            </div>
                        </Reveal>
                    ))}
                </ul>
            </Container>
        </section>
    );
}
