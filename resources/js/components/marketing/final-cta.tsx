import { CheckCircle2, Sparkles } from 'lucide-react';
import type { ReactNode } from 'react';
import { DemoButton, TrialButton } from './cta-buttons';
import { PriceNote } from './price-note';
import { Container, Reveal } from './reveal';

const TRUST_POINTS = [
    'Sesi Demo Personal 30 Menit',
    'Tanpa Komitmen / Kartu Kredit',
    'Gratis Konsultasi Kebutuhan HR',
];

/**
 * Closing call to action. Defaults to the main landing copy; feature pages
 * pass their own headline and body while keeping the same treatment.
 */
export function FinalCta({
    title = (
        <>
            Siap Melihat Apa yang Bisa{' '}
            <span className="text-blue-400">AvanaHR Lakukan</span> untuk Tim
            Anda?
        </>
    ),
    body = 'Jadwalkan demo dan lihat bagaimana AvanaHR menghubungkan proses HR, workforce data, analytics, dan AI dalam satu platform terpadu.',
    supporting,
    showPrice = true,
    showTrustPoints = true,
}: {
    title?: ReactNode;
    body?: string;
    /** Extra line under the trust points — used by feature pages for a more specific closing note. */
    supporting?: string;
    showPrice?: boolean;
    showTrustPoints?: boolean;
} = {}) {
    return (
        <section className="py-20 lg:py-28">
            <Container>
                <Reveal>
                    <div className="relative overflow-hidden rounded-[28px] bg-avana-dark px-6 py-14 text-center shadow-avana-glow sm:px-12 lg:px-16 lg:py-20">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute top-1/2 left-1/2 h-[450px] w-[800px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-avana-blue/15 blur-3xl"
                        />
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-0 opacity-[0.06]"
                            style={{
                                backgroundImage:
                                    'linear-gradient(to right, #fff 1px, transparent 1px), linear-gradient(to bottom, #fff 1px, transparent 1px)',
                                backgroundSize: '52px 52px',
                            }}
                        />
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-white/30 to-transparent"
                        />

                        <div className="relative z-10">
                            <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-xs font-semibold tracking-wider text-blue-200 uppercase backdrop-blur-sm sm:text-sm">
                                <Sparkles className="h-4 w-4 text-blue-400" />
                                Transformasi HR Dimulai Hari Ini
                            </span>

                            <h2 className="mx-auto mt-6 max-w-3xl text-[26px] leading-[1.18] font-black tracking-[-0.02em] text-balance text-white sm:text-4xl lg:text-[42px]">
                                {title}
                            </h2>
                            <p className="mx-auto mt-5 max-w-2xl text-[15px] leading-relaxed text-pretty text-blue-100/85 sm:text-[17px]">
                                {body}
                            </p>
                            <div className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <DemoButton
                                    variant="inverse"
                                    className="w-full sm:w-auto"
                                />
                                <TrialButton
                                    variant="ghostInverse"
                                    className="w-full sm:w-auto"
                                />
                            </div>
                            {showPrice && (
                                <div className="mt-5 flex justify-center">
                                    <PriceNote tone="dark" />
                                </div>
                            )}
                            {showTrustPoints && (
                                <div className="mt-6 flex flex-wrap items-center justify-center gap-6 text-xs text-blue-200/70">
                                    {TRUST_POINTS.map((point) => (
                                        <div
                                            key={point}
                                            className="flex items-center gap-1.5"
                                        >
                                            <CheckCircle2
                                                className="h-4 w-4 text-emerald-400"
                                                aria-hidden
                                            />
                                            <span>{point}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {supporting && (
                                <p className="mt-4 text-[13.5px] text-blue-200/70">
                                    {supporting}
                                </p>
                            )}
                        </div>
                    </div>
                </Reveal>
            </Container>
        </section>
    );
}
