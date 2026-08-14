import { Container, Reveal, SectionHeading } from '../reveal';
import { USE_CASES } from './content';

/** Who the feature is for. Four teams, no claims beyond what tracking does. */
export function TrackingUseCases() {
    return (
        <section className="border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-24">
            <Container>
                <SectionHeading
                    eyebrow="Use case"
                    title="Cocok untuk Tim yang Banyak Bergerak."
                />

                <ul className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {USE_CASES.map((useCase, i) => (
                        <Reveal
                            as="li"
                            key={useCase.title}
                            delay={(i % 4) * 0.05}
                            className="group rounded-2xl border border-[#E7ECF5] bg-white p-6 transition-[border-color,box-shadow] duration-200 hover:border-[#C9D6F0] hover:shadow-lift"
                        >
                            <span className="grid h-11 w-11 place-items-center rounded-xl bg-[#EEF2FB] text-[#2F54C9] transition-colors duration-200 group-hover:bg-[#2F54C9] group-hover:text-white">
                                <useCase.icon className="h-5 w-5" aria-hidden />
                            </span>
                            <h3 className="mt-4 text-[16.5px] font-semibold text-[#0E1A3A]">
                                {useCase.title}
                            </h3>
                            <p className="mt-2 text-[14px] leading-relaxed text-[#5B6478]">
                                {useCase.body}
                            </p>
                        </Reveal>
                    ))}
                </ul>
            </Container>
        </section>
    );
}
