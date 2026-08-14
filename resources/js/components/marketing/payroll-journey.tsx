import { PAYROLL_FLOW } from './content';
import { Container, Reveal, SectionHeading } from './reveal';

/**
 * Payroll journey — attendance through to the payslip, drawn as one connected
 * process so payroll reads as part of the HR flow rather than a separate tool.
 * Horizontal stepper on desktop, vertical timeline on mobile.
 */
export function PayrollJourney() {
    return (
        <section id="payroll" className="scroll-mt-28 py-20 lg:py-28">
            <Container>
                <SectionHeading
                    eyebrow="Payroll"
                    title="Dari Kehadiran Sampai Take Home Pay"
                    description="Data absensi menjadi bagian dari proses payroll saat cut-off. Payroll kemudian dapat melalui proses hitung, review, approval, pembayaran dan penerbitan slip."
                />

                {/* Desktop: horizontal stepper. */}
                <div className="relative mt-16 hidden lg:block">
                    <span
                        aria-hidden
                        className="absolute top-[27px] right-10 left-10 h-px bg-gradient-to-r from-[#E3E9F5] via-[#B9CAEB] to-[#E3E9F5]"
                    />
                    <ol className="grid grid-cols-9 gap-2">
                        {PAYROLL_FLOW.map((step, i) => (
                            <Reveal
                                as="li"
                                key={step.label}
                                delay={Math.min(i, 6) * 0.05}
                                className="group relative flex flex-col items-center text-center"
                            >
                                <span className="relative grid h-14 w-14 place-items-center rounded-2xl border border-[#E3E9F5] bg-white text-[#2F54C9] shadow-soft transition-[border-color,box-shadow,transform] duration-200 group-hover:-translate-y-0.5 group-hover:border-[#C9D6F0] group-hover:shadow-lift">
                                    <step.icon
                                        className="h-5 w-5"
                                        aria-hidden
                                    />
                                    <span className="absolute -top-2 -right-2 grid h-5 w-5 place-items-center rounded-full bg-[#0E1A3A] text-[10px] font-semibold text-white">
                                        {i + 1}
                                    </span>
                                </span>
                                <span className="mt-3 text-[12.5px] leading-tight font-medium text-[#0E1A3A]">
                                    {step.label}
                                </span>
                            </Reveal>
                        ))}
                    </ol>
                </div>

                {/* Mobile & tablet: vertical timeline. */}
                <ol className="mt-12 space-y-3 lg:hidden">
                    {PAYROLL_FLOW.map((step, i) => (
                        <Reveal
                            as="li"
                            key={step.label}
                            delay={Math.min(i, 4) * 0.04}
                            className="relative flex items-center gap-4"
                        >
                            <div className="relative flex flex-col items-center self-stretch">
                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-[#E3E9F5] bg-white text-[#2F54C9] shadow-soft">
                                    <step.icon
                                        className="h-4 w-4"
                                        aria-hidden
                                    />
                                </span>
                                {i < PAYROLL_FLOW.length - 1 && (
                                    <span
                                        aria-hidden
                                        className="mt-1.5 w-px flex-1 bg-[#E3E9F5]"
                                    />
                                )}
                            </div>
                            <div className="flex flex-1 items-center gap-3 rounded-xl border border-[#EDF1F8] bg-white px-4 py-3">
                                <span className="text-[12px] font-semibold text-[#9AA7C7]">
                                    {String(i + 1).padStart(2, '0')}
                                </span>
                                <span className="text-[14px] font-medium text-[#0E1A3A]">
                                    {step.label}
                                </span>
                            </div>
                        </Reveal>
                    ))}
                </ol>

                <Reveal delay={0.1}>
                    <p className="mt-12 rounded-2xl border border-[#E7ECF5] bg-[#F8FAFD] p-6 text-center text-[15px] leading-relaxed text-[#3B455C] shadow-soft">
                        Karena kehadiran, lembur dan komponen gaji berada di
                        platform yang sama, cut-off payroll memakai data yang
                        sudah tercatat — bukan data yang harus dikumpulkan ulang
                        dari sistem lain.
                    </p>
                </Reveal>
            </Container>
        </section>
    );
}
