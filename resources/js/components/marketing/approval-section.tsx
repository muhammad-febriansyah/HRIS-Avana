import { ArrowDown, Check } from 'lucide-react';
import { APPROVAL_STEPS, APPROVAL_TYPES } from './content';
import { Container, Reveal, SectionHeading } from './reveal';

/**
 * Approval center — the request types on the left, the shared approval path
 * rendered as one connected application-like card flow on the right.
 */
export function ApprovalSection() {
    return (
        <section className="border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-28">
            <Container>
                <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                    <div>
                        <SectionHeading
                            align="left"
                            eyebrow="Approval center"
                            title="Semua Pengajuan. Satu Alur Approval."
                            description="Karyawan mengajukan, atasan atau HR menyetujui, lalu status dan efek transaksi diperbarui di sistem."
                        />
                        <Reveal delay={0.08}>
                            <ul className="mt-8 flex flex-wrap gap-2">
                                {APPROVAL_TYPES.map((type) => (
                                    <li
                                        key={type}
                                        className="inline-flex items-center gap-1.5 rounded-full border border-[#E7ECF5] bg-white px-3.5 py-1.5 text-[13px] font-medium text-[#3B455C] shadow-soft"
                                    >
                                        <Check
                                            className="h-3.5 w-3.5 text-[#2F54C9]"
                                            aria-hidden
                                        />
                                        {type}
                                    </li>
                                ))}
                            </ul>
                        </Reveal>
                    </div>

                    <Reveal delay={0.1}>
                        <div className="rounded-3xl border border-[#E7ECF5] bg-white p-4 shadow-lift sm:p-5">
                            <div className="flex items-center justify-between border-b border-[#F0F3F9] px-2 pt-1 pb-4">
                                <span className="text-[13px] font-semibold text-[#0E1A3A]">
                                    Alur Persetujuan
                                </span>
                                <span className="rounded-full bg-[#EEF2FB] px-2.5 py-1 text-[11px] font-medium text-[#2F54C9]">
                                    Contoh: Cuti
                                </span>
                            </div>
                            <ol className="mt-4 space-y-1.5">
                                {APPROVAL_STEPS.map((step, i) => (
                                    <li key={step.label}>
                                        <div className="flex items-center gap-4 rounded-2xl border border-[#EDF1F8] bg-[#FAFBFE] p-4">
                                            <span
                                                className={
                                                    'grid h-9 w-9 shrink-0 place-items-center rounded-full text-[13px] font-semibold ' +
                                                    (i ===
                                                    APPROVAL_STEPS.length - 1
                                                        ? 'bg-[#16A34A]/10 text-[#16A34A]'
                                                        : 'bg-[#EEF2FB] text-[#2F54C9]')
                                                }
                                            >
                                                {i ===
                                                APPROVAL_STEPS.length - 1 ? (
                                                    <Check
                                                        className="h-4 w-4"
                                                        aria-hidden
                                                    />
                                                ) : (
                                                    i + 1
                                                )}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block text-[15px] font-semibold text-[#0E1A3A]">
                                                    {step.label}
                                                </span>
                                                <span className="block text-[13px] text-[#6B7280]">
                                                    {step.caption}
                                                </span>
                                            </span>
                                        </div>
                                        {i < APPROVAL_STEPS.length - 1 && (
                                            <div className="flex justify-center py-0.5">
                                                <ArrowDown
                                                    className="h-3.5 w-3.5 text-[#C4D2EF]"
                                                    aria-hidden
                                                />
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </Reveal>
                </div>
            </Container>
        </section>
    );
}
