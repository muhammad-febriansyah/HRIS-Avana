import { DemoButton } from './cta-buttons';
import { Container, Reveal } from './reveal';

/**
 * Slim gradient CTA strip that sits directly above the footer — a last
 * nudge for visitors who scrolled past every other CTA on the page.
 */
export function BottomCta() {
    return (
        <section className="pt-10 pb-16">
            <Container>
                <Reveal>
                    <div className="relative flex flex-col items-center justify-between gap-8 overflow-hidden rounded-3xl bg-gradient-to-r from-avana-navy to-avana-blue p-8 shadow-avana-hover md:flex-row md:p-12">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute -top-16 -right-16 h-56 w-56 rounded-full bg-white/10 blur-3xl"
                        />

                        <div className="relative z-10 max-w-2xl text-center md:text-left">
                            <h2 className="mb-3 text-2xl font-extrabold text-white md:text-3xl">
                                Satu solusi untuk semua kebutuhan HR
                            </h2>
                            <p className="text-sm text-blue-100 md:text-base">
                                Optimalkan pengelolaan operasi HR Anda dengan
                                bantuan solusi terintegrasi dari AvanaHR.
                            </p>
                        </div>

                        <div className="relative z-10 w-full shrink-0 md:w-auto">
                            <DemoButton
                                variant="inverse"
                                className="w-full md:w-auto"
                            />
                        </div>
                    </div>
                </Reveal>
            </Container>
        </section>
    );
}
