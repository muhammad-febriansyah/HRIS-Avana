import { Container, Reveal, SectionHeading } from '../reveal';
import { ENCRYPTION_POINTS, MASKING_EXAMPLES } from './content';

/**
 * Section 3 — Enkripsi & Perlindungan Data Pribadi. No dedicated illustration
 * was supplied for this one, so it leans on the masking examples themselves
 * as the visual — same icon-card rhythm as the other sections.
 */
export function EncryptionSection() {
    return (
        <section
            id="enkripsi"
            className="scroll-mt-24 bg-[#F8FAFD] py-16 lg:py-20"
        >
            <Container>
                <SectionHeading
                    eyebrow="Enkripsi & Data Pribadi"
                    title="Data Sensitif Tidak Disimpan Begitu Saja."
                    description="NIK, NPWP, dan nomor rekening karyawan disimpan terenkripsi — sesuai UU Perlindungan Data Pribadi No. 27/2022."
                />

                <Reveal delay={0.08}>
                    <div className="mx-auto mt-10 flex max-w-xl flex-col items-center gap-3 sm:flex-row sm:justify-center">
                        <span className="text-[12.5px] font-semibold tracking-wide text-[#5B6478] uppercase">
                            Contoh masking
                        </span>
                        {MASKING_EXAMPLES.map((example) => (
                            <code
                                key={example}
                                className="rounded-lg border border-[#D8E1F5] bg-white px-3.5 py-2 font-mono text-[13.5px] text-[#0E1A3A]"
                            >
                                {example}
                            </code>
                        ))}
                    </div>
                </Reveal>

                <ul className="mt-10 grid gap-4 sm:grid-cols-2">
                    {ENCRYPTION_POINTS.map((point, i) => (
                        <Reveal
                            as="li"
                            key={point.title}
                            delay={(i % 2) * 0.06}
                            className="flex gap-4 rounded-2xl border border-[#E7ECF5] bg-white p-6"
                        >
                            <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#EEF2FB] text-[#2F54C9]">
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
