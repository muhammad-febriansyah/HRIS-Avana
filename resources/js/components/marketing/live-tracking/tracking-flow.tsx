import { Container, Reveal, SectionHeading } from '../reveal';
import { TRACKING_FLOW } from './content';

/**
 * How it works — the session lifecycle. Horizontal stepper on desktop, vertical
 * timeline on mobile; the connecting line is static so nothing animates
 * forever.
 */
export function TrackingFlow() {
    return (
        <section id="cara-kerja" className="scroll-mt-28 py-20 lg:py-28">
            <Container>
                <SectionHeading
                    eyebrow="Cara kerja"
                    title="Dari Clock In Sampai Clock Out."
                    description="Tracking dimulai setelah karyawan melakukan Clock In dan berhenti ketika Clock Out."
                />

                {/* Desktop */}
                <div className="relative mt-16 hidden lg:block">
                    <span
                        aria-hidden
                        className="absolute top-[27px] right-12 left-12 h-px bg-gradient-to-r from-[#E3E9F5] via-[#B9CAEB] to-[#E3E9F5]"
                    />
                    <ol className="grid grid-cols-7 gap-3">
                        {TRACKING_FLOW.map((step, i) => (
                            <Reveal
                                as="li"
                                key={step.label}
                                delay={Math.min(i, 6) * 0.05}
                                className="relative flex flex-col items-center text-center"
                            >
                                <span className="relative grid h-14 w-14 place-items-center rounded-2xl border border-[#E3E9F5] bg-white text-[13px] font-semibold text-[#2F54C9] shadow-soft">
                                    {String(i + 1).padStart(2, '0')}
                                </span>
                                <span className="mt-3 text-[12.5px] leading-tight font-semibold text-[#0E1A3A]">
                                    {step.label}
                                </span>
                                <span className="mt-1 text-[11.5px] leading-snug text-[#8A93A6]">
                                    {step.note}
                                </span>
                            </Reveal>
                        ))}
                    </ol>
                </div>

                {/* Mobile & tablet */}
                <ol className="mt-12 space-y-3 lg:hidden">
                    {TRACKING_FLOW.map((step, i) => (
                        <Reveal
                            as="li"
                            key={step.label}
                            delay={Math.min(i, 4) * 0.04}
                            className="flex items-stretch gap-4"
                        >
                            <div className="flex flex-col items-center">
                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-[#E3E9F5] bg-white text-[12px] font-semibold text-[#2F54C9] shadow-soft">
                                    {String(i + 1).padStart(2, '0')}
                                </span>
                                {i < TRACKING_FLOW.length - 1 && (
                                    <span
                                        aria-hidden
                                        className="mt-1.5 w-px flex-1 bg-[#E3E9F5]"
                                    />
                                )}
                            </div>
                            <div className="flex-1 rounded-xl border border-[#EDF1F8] bg-white px-4 py-3">
                                <span className="block text-[14px] font-semibold text-[#0E1A3A]">
                                    {step.label}
                                </span>
                                <span className="block text-[12.5px] text-[#8A93A6]">
                                    {step.note}
                                </span>
                            </div>
                        </Reveal>
                    ))}
                </ol>
            </Container>
        </section>
    );
}
