import { useState } from 'react';
import { TOUR_MODULES } from './content';
import { Container, Reveal, SectionHeading } from './reveal';
import { TabList, TabPanel } from './tabs';

/**
 * Product tour — seven modules behind a tab control so a visitor can pick the
 * one they care about. Each panel shows a screenshot of the real screen,
 * captured from the demo tenant with names, e-mails and the tenant logo
 * replaced (see `public/avana/landing/screenshots/`).
 */
export function ProductTour() {
    const [active, setActive] = useState(TOUR_MODULES[0].id);

    return (
        <section className="py-20 lg:py-28">
            <Container>
                <SectionHeading
                    eyebrow="Product tour"
                    title="Lihat AvanaHR Lebih Dekat."
                    description="Pilih modul untuk melihat layarnya di dalam aplikasi."
                />

                <div className="mt-12">
                    <TabList
                        items={TOUR_MODULES.map((module) => ({
                            id: module.id,
                            label: module.label,
                        }))}
                        active={active}
                        onChange={setActive}
                        idPrefix="tour"
                    />

                    <div className="mt-10">
                        {TOUR_MODULES.map((module, index) => (
                            <TabPanel
                                key={module.id}
                                id={module.id}
                                idPrefix="tour"
                                active={active}
                            >
                                <Reveal>
                                    <figure className="overflow-hidden rounded-2xl border border-[#E1E7F2] bg-white shadow-frame">
                                        {/* Browser chrome, so the screenshot
                                         * reads as an application window. */}
                                        <div className="flex items-center gap-2 border-b border-[#F0F3F9] bg-[#FAFBFE] px-4 py-3">
                                            {[0, 1, 2].map((dot) => (
                                                <span
                                                    key={dot}
                                                    aria-hidden
                                                    className="h-2.5 w-2.5 rounded-full bg-[#E4E9F2]"
                                                />
                                            ))}
                                            <span className="mx-auto hidden h-6 w-full max-w-[280px] items-center justify-center rounded-md border border-[#EDF1F7] bg-white text-[10px] text-[#9CA3AF] sm:flex">
                                                {module.label}
                                            </span>
                                        </div>
                                        <img
                                            src={module.image}
                                            alt={`Tampilan ${module.label} di AvanaHR`}
                                            width={1440}
                                            height={900}
                                            loading={
                                                index === 0 ? 'eager' : 'lazy'
                                            }
                                            decoding="async"
                                            className="block w-full"
                                        />
                                    </figure>
                                    <p className="mt-4 text-center text-[13.5px] text-[#5B6478]">
                                        {module.caption}
                                    </p>
                                </Reveal>
                            </TabPanel>
                        ))}
                    </div>

                    <Reveal delay={0.08}>
                        <p className="mt-4 text-center text-[12px] text-[#8A93A6]">
                            Tangkapan layar aplikasi dengan data demo — nama,
                            email dan nominal bukan data pengguna.
                        </p>
                    </Reveal>
                </div>
            </Container>
        </section>
    );
}
