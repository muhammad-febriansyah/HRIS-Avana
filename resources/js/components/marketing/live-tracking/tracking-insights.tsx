import { Container, Reveal, SectionHeading } from '../reveal';
import { TRACKING_INSIGHTS } from './content';

/** What a tracked session actually tells HR — six short cards, nothing more. */
export function TrackingInsights() {
    return (
        <section className="py-20 lg:py-24">
            <Container>
                <SectionHeading
                    eyebrow="Insight"
                    title="Bukan Sekadar Titik di Map."
                />

                <ul className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {TRACKING_INSIGHTS.map((insight, i) => (
                        <Reveal
                            as="li"
                            key={insight.title}
                            delay={(i % 3) * 0.05}
                            className="flex gap-4 rounded-2xl border border-[#E7ECF5] bg-white p-5 transition-[border-color,box-shadow] duration-200 hover:border-[#C9D6F0] hover:shadow-lift"
                        >
                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#EEF2FB] text-[#2F54C9]">
                                <insight.icon
                                    className="h-4.5 w-4.5"
                                    aria-hidden
                                />
                            </span>
                            <div>
                                <h3 className="text-[15.5px] font-semibold text-[#0E1A3A]">
                                    {insight.title}
                                </h3>
                                <p className="mt-1 text-[13.5px] leading-relaxed text-[#5B6478]">
                                    {insight.body}
                                </p>
                            </div>
                        </Reveal>
                    ))}
                </ul>
            </Container>
        </section>
    );
}
