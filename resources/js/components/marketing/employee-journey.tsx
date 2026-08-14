import { EMPLOYEE_JOURNEY } from './content';
import { Container, Reveal, SectionHeading } from './reveal';

/**
 * Employee journey — a connected flow, horizontal on desktop and a vertical
 * timeline on mobile so nothing has to scroll sideways. Step numbers make the
 * sequence readable even before the connector line is noticed.
 */
export function EmployeeJourney() {
    return (
        <section id="platform" className="scroll-mt-28 py-20 lg:py-28">
            <Container>
                <SectionHeading
                    eyebrow="Satu platform"
                    title="Satu Platform. Seluruh Employee Journey."
                    description="Kelola perjalanan karyawan dari recruitment, onboarding, kehadiran, payroll, performance, learning hingga pengembangan talent dalam satu platform."
                />

                {/* Desktop: two connected rows of four steps. */}
                <div className="mt-16 hidden lg:block">
                    <ol className="grid grid-cols-4 gap-x-4 gap-y-12">
                        {EMPLOYEE_JOURNEY.map((step, i) => (
                            <Reveal
                                as="li"
                                key={step.label}
                                delay={(i % 4) * 0.06}
                                className="group relative"
                            >
                                {i % 4 !== 3 && (
                                    <span
                                        aria-hidden
                                        className="absolute top-7 left-[calc(50%+32px)] h-px w-[calc(100%-64px)] bg-gradient-to-r from-[#B9CAEB] to-[#E6ECF6]"
                                    />
                                )}
                                <div className="flex flex-col items-center text-center">
                                    <span className="relative grid h-14 w-14 place-items-center rounded-2xl border border-[#E3E9F5] bg-white text-[#2F54C9] shadow-soft transition-[border-color,box-shadow,transform] duration-200 group-hover:-translate-y-0.5 group-hover:border-[#C9D6F0] group-hover:shadow-lift">
                                        <step.icon
                                            className="h-5 w-5"
                                            aria-hidden
                                        />
                                        <span className="absolute -top-2 -right-2 grid h-5 w-5 place-items-center rounded-full bg-[#0E1A3A] text-[10px] font-semibold text-white">
                                            {i + 1}
                                        </span>
                                    </span>
                                    <h3 className="mt-4 text-[15px] font-semibold text-[#0E1A3A]">
                                        {step.label}
                                    </h3>
                                    <p className="mt-1 text-[13px] text-[#6B7280]">
                                        {step.note}
                                    </p>
                                </div>
                            </Reveal>
                        ))}
                    </ol>
                </div>

                {/* Mobile & tablet: vertical timeline. */}
                <ol className="mt-12 space-y-3 lg:hidden">
                    {EMPLOYEE_JOURNEY.map((step, i) => (
                        <Reveal
                            as="li"
                            key={step.label}
                            delay={Math.min(i, 4) * 0.04}
                            className="relative flex gap-4"
                        >
                            <div className="relative flex flex-col items-center">
                                <span className="relative grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-[#E3E9F5] bg-white text-[#2F54C9] shadow-soft">
                                    <step.icon
                                        className="h-4.5 w-4.5"
                                        aria-hidden
                                    />
                                </span>
                                {i < EMPLOYEE_JOURNEY.length - 1 && (
                                    <span
                                        aria-hidden
                                        className="mt-1.5 w-px flex-1 bg-[#E3E9F5]"
                                    />
                                )}
                            </div>
                            <div className="flex-1 rounded-xl border border-[#EDF1F8] bg-white px-4 py-3">
                                <h3 className="text-[15px] font-semibold text-[#0E1A3A]">
                                    <span className="mr-2 text-[12px] font-semibold text-[#9AA7C7]">
                                        {String(i + 1).padStart(2, '0')}
                                    </span>
                                    {step.label}
                                </h3>
                                <p className="mt-0.5 text-[13px] text-[#6B7280]">
                                    {step.note}
                                </p>
                            </div>
                        </Reveal>
                    ))}
                </ol>
            </Container>
        </section>
    );
}
