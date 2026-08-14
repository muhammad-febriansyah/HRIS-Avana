import { Check } from 'lucide-react';
import { ANALYTICS_TOPICS } from './content';
import { Container, Reveal, SectionHeading } from './reveal';

/**
 * Analytics — reporting topics beside the real reporting screen. The
 * screenshot comes from the demo tenant with names, e-mails and the tenant
 * logo replaced (see `public/avana/landing/screenshots/`).
 */
export function AnalyticsSection() {
    return (
        <section
            id="analitik"
            className="scroll-mt-28 border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-28"
        >
            <Container>
                <div className="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] lg:gap-16">
                    <div>
                        <SectionHeading
                            align="left"
                            eyebrow="Analitik"
                            title="HR Bukan Lagi Sekadar Administrasi."
                            description="Gunakan laporan dan metrik HR untuk mendapatkan gambaran yang lebih jelas tentang kondisi people di perusahaan."
                        />
                        <Reveal delay={0.08}>
                            <ul className="mt-8 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                                {ANALYTICS_TOPICS.map((topic) => (
                                    <li
                                        key={topic}
                                        className="flex items-start gap-2.5 text-[14.5px] text-[#3B455C]"
                                    >
                                        <Check
                                            className="mt-0.5 h-4 w-4 shrink-0 text-[#2F54C9]"
                                            aria-hidden
                                        />
                                        {topic}
                                    </li>
                                ))}
                            </ul>
                        </Reveal>
                    </div>

                    <Reveal delay={0.12}>
                        <figure className="overflow-hidden rounded-2xl border border-[#E1E7F2] bg-white shadow-frame">
                            <div className="flex items-center gap-2 border-b border-[#F0F3F9] bg-[#FAFBFE] px-4 py-3">
                                {[0, 1, 2].map((dot) => (
                                    <span
                                        key={dot}
                                        aria-hidden
                                        className="h-2.5 w-2.5 rounded-full bg-[#E4E9F2]"
                                    />
                                ))}
                                <span className="mx-auto hidden h-6 w-full max-w-[240px] items-center justify-center rounded-md border border-[#EDF1F7] bg-white text-[10px] text-[#9CA3AF] sm:flex">
                                    Analitik HR
                                </span>
                            </div>
                            <img
                                src="/avana/landing/screenshots/analytics.png"
                                alt="Analitik HR di AvanaHR: attrition, karyawan per departemen, distribusi usia dan gaji"
                                width={1440}
                                height={900}
                                loading="lazy"
                                decoding="async"
                                className="block w-full"
                            />
                        </figure>
                        <figcaption className="mt-3 text-center text-[12px] text-[#8A93A6]">
                            Tangkapan layar aplikasi dengan data demo.
                        </figcaption>
                    </Reveal>
                </div>
            </Container>
        </section>
    );
}
