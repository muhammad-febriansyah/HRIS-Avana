import { Container, Reveal, SectionHeading } from '../reveal';
import { TRACKING_PROBLEMS } from './content';

/** Why Clock In alone leaves field teams hard to follow. */
export function TrackingProblem() {
    return (
        <section className="border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-24">
            <Container>
                <SectionHeading
                    eyebrow="Kondisi hari ini"
                    title="Sulit Mengetahui Aktivitas Tim di Lapangan?"
                    description="Untuk tim yang bekerja di luar kantor, Clock In saja belum selalu cukup untuk memberikan gambaran aktivitas kerja sepanjang hari."
                />

                <ul className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {TRACKING_PROBLEMS.map((problem, i) => (
                        <Reveal
                            as="li"
                            key={problem.title}
                            delay={(i % 4) * 0.05}
                            className="group rounded-2xl border border-[#E7ECF5] bg-white p-6 transition-[border-color,box-shadow] duration-200 hover:border-[#C9D6F0] hover:shadow-lift"
                        >
                            <span className="grid h-11 w-11 place-items-center rounded-xl bg-[#EEF2FB] text-[#2F54C9] transition-colors duration-200 group-hover:bg-[#2F54C9] group-hover:text-white">
                                <problem.icon className="h-5 w-5" aria-hidden />
                            </span>
                            <h3 className="mt-4 text-[16.5px] font-semibold text-[#0E1A3A]">
                                {problem.title}
                            </h3>
                            <p className="mt-2 text-[14px] leading-relaxed text-[#5B6478]">
                                {problem.body}
                            </p>
                        </Reveal>
                    ))}
                </ul>

                <Reveal delay={0.12}>
                    <p className="mt-9 text-center text-[16px] font-medium text-[#0E1A3A] sm:text-[18px]">
                        AvanaHR menghubungkan attendance dengan Live Tracking
                        dalam satu workflow.
                    </p>
                </Reveal>
            </Container>
        </section>
    );
}
