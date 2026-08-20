import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { TOUR_MODULES } from './content';
import { DemoButton } from './cta-buttons';
import { Container, Reveal, SectionHeading } from './reveal';

/**
 * Product demo — a tabbed screenshot showcase of the real application.
 * Modules come from `TOUR_MODULES` (shared with the product tour copy), each
 * rendered inside a browser-chrome frame so it reads as a live app window.
 * Screenshots are captured from the demo tenant.
 */
export function ProductDemoSection() {
    const [activeId, setActiveId] = useState(TOUR_MODULES[0].id);
    const active =
        TOUR_MODULES.find((module) => module.id === activeId) ??
        TOUR_MODULES[0];

    return (
        <section className="py-20 lg:py-28">
            <Container>
                <SectionHeading
                    eyebrow="Product Experience"
                    title={
                        <>
                            Lihat AvanaHR Bekerja
                            <br className="hidden sm:inline" />
                            <span className="text-avana-blue">
                                {' '}
                                dalam Satu Platform
                            </span>
                        </>
                    }
                    description="Interface modern, intuitif, dan responsif yang dirancang untuk kecepatan tim HR serta kemudahan seluruh karyawan."
                />

                {/* Module switcher */}
                <Reveal delay={0.05}>
                    <div className="mt-10 flex flex-wrap items-center justify-center gap-2 sm:gap-3">
                        {TOUR_MODULES.map((module) => {
                            const isActive = module.id === active.id;

                            return (
                                <button
                                    key={module.id}
                                    type="button"
                                    onClick={() => setActiveId(module.id)}
                                    aria-pressed={isActive}
                                    className={`inline-flex cursor-pointer items-center gap-1.5 rounded-full px-4 py-2.5 text-[13px] font-semibold transition-all duration-200 sm:px-5 sm:text-sm ${
                                        isActive
                                            ? 'bg-avana-navy text-white shadow-avana-card'
                                            : 'bg-avana-soft text-avana-text hover:bg-avana-light hover:text-avana-navy'
                                    }`}
                                >
                                    <module.icon
                                        className="h-4 w-4"
                                        aria-hidden
                                    />
                                    {module.label}
                                </button>
                            );
                        })}
                    </div>
                </Reveal>

                {/* Screenshot frame */}
                <Reveal delay={0.1}>
                    <div className="mt-10 rounded-avana-hero border border-avana-border bg-gradient-to-b from-avana-soft to-white p-3 shadow-avana-card sm:p-5">
                        {/* Browser chrome */}
                        <div className="mb-3 flex items-center justify-between rounded-xl border border-avana-border bg-white px-4 py-2">
                            <div className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full bg-red-400" />
                                <span className="h-3 w-3 rounded-full bg-amber-400" />
                                <span className="h-3 w-3 rounded-full bg-emerald-400" />
                            </div>
                            <div className="hidden text-xs font-medium text-avana-muted sm:block">
                                app.avanahr.id/system/{active.id}
                            </div>
                            <div className="flex items-center gap-1 text-xs font-semibold text-emerald-600">
                                <ShieldCheck
                                    className="h-3.5 w-3.5"
                                    aria-hidden
                                />
                                <span className="hidden sm:inline">
                                    SSL 256-Bit
                                </span>
                            </div>
                        </div>

                        {/* Screenshot */}
                        <div className="flex min-h-[320px] items-center justify-center overflow-hidden rounded-avana-card border border-avana-border bg-white sm:min-h-[480px]">
                            <img
                                src={active.image}
                                alt={`Tampilan ${active.label} di AvanaHR`}
                                width={1440}
                                height={900}
                                loading="lazy"
                                decoding="async"
                                className="h-auto w-full object-contain"
                            />
                        </div>

                        {/* Caption + CTA */}
                        <div className="mt-4 flex flex-col items-center justify-between gap-4 rounded-xl border border-avana-border bg-white px-4 py-3 sm:flex-row">
                            <p className="text-center text-[13px] font-medium text-avana-text sm:text-left sm:text-sm">
                                {active.caption}
                            </p>
                            <DemoButton
                                withArrow
                                className="h-10 shrink-0 px-5 text-[13px] sm:h-11 sm:text-sm"
                            />
                        </div>
                    </div>
                </Reveal>

                <Reveal delay={0.15}>
                    <p className="mt-4 text-center text-[12px] text-avana-muted">
                        Tangkapan layar aplikasi dengan data demo — nama,
                        email dan nominal bukan data pengguna.
                    </p>
                </Reveal>
            </Container>
        </section>
    );
}
