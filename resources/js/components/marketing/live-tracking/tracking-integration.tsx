import { useRef } from 'react';
import { AnimatedBeam } from '../animated-beam';
import { Container, Reveal, SectionHeading } from '../reveal';
import { INTEGRATION_STEPS } from './content';

/**
 * The main selling point: tracking is a stage of the attendance flow, not a
 * second app. The realtime note lives here too, so the page does not spend a
 * whole section on one sentence.
 */
export function TrackingIntegration() {
    // Written out one by one rather than collected in an array: the lint rule
    // for the React compiler forbids indexing refs during render.
    const chainRef = useRef<HTMLOListElement>(null);
    const stepOne = useRef<HTMLLIElement>(null);
    const stepTwo = useRef<HTMLLIElement>(null);
    const stepThree = useRef<HTMLLIElement>(null);
    const stepFour = useRef<HTMLLIElement>(null);

    return (
        <section className="py-20 lg:py-28">
            <Container>
                <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                    <div>
                        <SectionHeading
                            align="left"
                            eyebrow="Integrasi"
                            title="Terhubung dengan Attendance AvanaHR."
                            description="Live Tracking tidak berdiri sebagai aplikasi terpisah. Tracking menjadi bagian dari proses attendance AvanaHR."
                        />

                        <Reveal delay={0.1}>
                            <div className="mt-8 rounded-2xl border border-[#E7ECF5] bg-white p-5 shadow-soft">
                                <span className="inline-flex items-center gap-2 rounded-full bg-[#0E1A3A] px-3 py-1.5 text-[11.5px] font-semibold tracking-[0.06em] text-white uppercase">
                                    <span className="relative grid place-items-center">
                                        <span
                                            aria-hidden
                                            className="avn-ping absolute h-3 w-3 rounded-full bg-white/40"
                                        />
                                        <span
                                            aria-hidden
                                            className="relative h-1.5 w-1.5 rounded-full bg-white"
                                        />
                                    </span>
                                    Live
                                </span>
                                <p className="mt-4 text-[16px] font-semibold text-[#0E1A3A]">
                                    Update Lokasi Tanpa Refresh Manual.
                                </p>
                                <p className="mt-1.5 text-[14px] leading-relaxed text-[#5B6478]">
                                    Dashboard memperbarui posisi terakhir selama
                                    sesi tracking berjalan, tanpa perlu memuat
                                    ulang halaman.
                                </p>
                            </div>
                        </Reveal>
                    </div>

                    <Reveal delay={0.08}>
                        <ol ref={chainRef} className="relative space-y-6">
                            <IntegrationStep
                                step={INTEGRATION_STEPS[0]}
                                nodeRef={stepOne}
                            />
                            <IntegrationStep
                                step={INTEGRATION_STEPS[1]}
                                nodeRef={stepTwo}
                            />
                            <IntegrationStep
                                step={INTEGRATION_STEPS[2]}
                                nodeRef={stepThree}
                            />
                            <IntegrationStep
                                step={INTEGRATION_STEPS[3]}
                                nodeRef={stepFour}
                            />

                            <AnimatedBeam
                                containerRef={chainRef}
                                fromRef={stepOne}
                                toRef={stepTwo}
                                duration={3.4}
                            />
                            <AnimatedBeam
                                containerRef={chainRef}
                                fromRef={stepTwo}
                                toRef={stepThree}
                                delay={0.5}
                                duration={3.4}
                            />
                            <AnimatedBeam
                                containerRef={chainRef}
                                fromRef={stepThree}
                                toRef={stepFour}
                                delay={1}
                                duration={3.4}
                            />
                        </ol>
                    </Reveal>
                </div>
            </Container>
        </section>
    );
}

function IntegrationStep({
    step,
    nodeRef,
}: {
    step: (typeof INTEGRATION_STEPS)[number];
    nodeRef: React.RefObject<HTMLLIElement | null>;
}) {
    return (
        <li
            ref={nodeRef}
            className={
                'relative z-10 rounded-2xl border p-5 ' +
                (step.tone === 'brand'
                    ? 'border-[#2F54C9]/25 bg-[#2F54C9]/6 shadow-soft'
                    : 'border-[#E7ECF5] bg-white')
            }
        >
            <span
                className={
                    'text-[15px] font-semibold ' +
                    (step.tone === 'brand'
                        ? 'text-[#2F54C9]'
                        : 'text-[#0E1A3A]')
                }
            >
                {step.label}
            </span>
            {step.items && (
                <ul className="mt-3 flex flex-wrap gap-2">
                    {step.items.map((item) => (
                        <li
                            key={item}
                            className="rounded-lg border border-[#D8E1F5] bg-white px-2.5 py-1.5 text-[12.5px] font-medium text-[#3B455C]"
                        >
                            {item}
                        </li>
                    ))}
                </ul>
            )}
        </li>
    );
}
