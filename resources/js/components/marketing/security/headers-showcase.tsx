import { ShieldHalf } from 'lucide-react';
import { Container, Reveal, SectionHeading } from '../reveal';
import { HEADERS_SECTION } from './content';

/**
 * Section 2 — Proteksi Koneksi & Security Headers. Centered showcase instead
 * of the split image+checklist shape: the illustration already spells out
 * every header, so the header list here reads as badges, not a repeat.
 */
export function HeadersShowcase() {
    return (
        <section id="koneksi" className="scroll-mt-24 py-16 lg:py-20">
            <Container>
                <SectionHeading
                    eyebrow="Proteksi Koneksi"
                    title="Security Headers pada Setiap Koneksi."
                    description="HTTP Security Headers mengurangi risiko clickjacking, MIME sniffing, dan akses cross-origin yang tidak diinginkan."
                />

                <Reveal delay={0.1} className="relative mx-auto mt-10 max-w-2xl">
                    <div
                        aria-hidden
                        className="pointer-events-none absolute inset-0 rounded-full bg-[radial-gradient(circle,rgba(49,95,212,0.14)_0%,transparent_70%)]"
                    />
                    <img
                        src={HEADERS_SECTION.image}
                        alt={HEADERS_SECTION.imageAlt}
                        loading="lazy"
                        className="relative z-10 mx-auto w-full max-w-[440px] object-contain select-none"
                        draggable={false}
                    />
                </Reveal>

                <Reveal delay={0.14}>
                    <ul className="mx-auto mt-8 flex max-w-3xl flex-wrap items-center justify-center gap-2.5">
                        {HEADERS_SECTION.headers.map((header) => (
                            <li
                                key={header}
                                className="rounded-lg border border-[#D8E1F5] bg-white px-3 py-1.5 text-[12.5px] font-medium text-[#3B455C]"
                            >
                                {header}
                            </li>
                        ))}
                    </ul>
                </Reveal>

                <Reveal delay={0.18}>
                    <div className="mx-auto mt-8 flex max-w-2xl items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-left">
                        <ShieldHalf
                            className="mt-0.5 h-5 w-5 shrink-0 text-amber-600"
                            aria-hidden
                        />
                        <p className="text-[13.5px] leading-relaxed text-amber-900">
                            <strong className="font-semibold">
                                Content Security Policy saat ini diterapkan
                                dalam mode monitoring/report-only
                            </strong>{' '}
                            sebelum enforcement penuh diaktifkan pada
                            production.
                        </p>
                    </div>
                </Reveal>
            </Container>
        </section>
    );
}
