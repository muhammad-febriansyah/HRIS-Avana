import { DemoButton, TrialButton } from './cta-buttons';
import { PriceNote } from './price-note';
import { Container, Reveal } from './reveal';

/**
 * Closing call to action. Defaults to the main landing copy; feature pages
 * pass their own headline and body while keeping the same treatment.
 */
export function FinalCta({
    title = 'Saatnya Mengubah Cara Perusahaan Mengelola People.',
    body = 'Mulai dari HR Administration sampai Payroll dan Talent Management — kelola proses HR dalam satu platform bersama AvanaHR.',
    supporting = 'Lihat langsung bagaimana AvanaHR dapat disesuaikan dengan proses HR perusahaan Anda.',
    showPrice = true,
}: {
    title?: string;
    body?: string;
    supporting?: string;
    showPrice?: boolean;
} = {}) {
    return (
        <section className="py-20 lg:py-28">
            <Container>
                <Reveal>
                    <div className="relative overflow-hidden rounded-[28px] bg-[#0E1A3A] px-6 py-14 text-center shadow-lift sm:px-12 lg:px-16 lg:py-20">
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
                            className="pointer-events-none absolute -top-24 left-1/2 h-72 w-[560px] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(47,84,201,0.5),transparent)]"
                        />
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-[#6E9BE6]/50 to-transparent"
                        />

                        <div className="relative">
                            <h2 className="mx-auto max-w-3xl text-[26px] leading-[1.18] font-bold tracking-[-0.02em] text-balance text-white sm:text-4xl lg:text-[42px]">
                                {title}
                            </h2>
                            <p className="mx-auto mt-5 max-w-2xl text-[15px] leading-relaxed text-pretty text-white/70 sm:text-[17px]">
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
                            <p className="mt-4 text-[13.5px] text-white/55">
                                {supporting}
                            </p>
                        </div>
                    </div>
                </Reveal>
            </Container>
        </section>
    );
}
