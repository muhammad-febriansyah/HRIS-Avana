import { Check } from 'lucide-react';
import { useState } from 'react';
import { TALENT_FEATURES } from './content';
import { Container, Reveal, SectionHeading } from './reveal';
import { TabList, TabPanel } from './tabs';

/**
 * Talent & performance — a feature list on the left, the selected feature
 * explained on the right. Features that have a screenshot show it inside the
 * same card, so the section keeps one column of content instead of three.
 */
export function TalentSection() {
    const [active, setActive] = useState(TALENT_FEATURES[0].id);
    const items = TALENT_FEATURES.map((feature) => ({
        id: feature.id,
        label: feature.title,
    }));

    return (
        <section id="talenta" className="scroll-mt-28 py-20 lg:py-28">
            <Container>
                <SectionHeading
                    eyebrow="Talenta"
                    title="Dari Performance ke Development"
                    description="Kelola recruitment, onboarding, performance, target kerja, learning dan talent mapping untuk mendukung pengembangan karyawan."
                />

                <div className="mt-12 grid gap-6 lg:mt-14 lg:grid-cols-[236px_minmax(0,1fr)] lg:gap-10">
                    <div className="lg:sticky lg:top-28 lg:self-start">
                        <TabList
                            items={items}
                            active={active}
                            onChange={setActive}
                            idPrefix="talenta"
                            className="lg:hidden"
                        />
                        <TabList
                            items={items}
                            active={active}
                            onChange={setActive}
                            idPrefix="talenta-desktop"
                            orientation="vertical"
                            className="hidden lg:flex"
                        />
                    </div>

                    <div>
                        {TALENT_FEATURES.map((feature) => (
                            <TabPanel
                                key={feature.id}
                                id={feature.id}
                                idPrefix="talenta"
                                active={active}
                                className="lg:hidden"
                            >
                                <TalentDetail feature={feature} />
                            </TabPanel>
                        ))}
                        {TALENT_FEATURES.map((feature) => (
                            <TabPanel
                                key={feature.id}
                                id={feature.id}
                                idPrefix="talenta-desktop"
                                active={active}
                                className="hidden lg:block"
                            >
                                <TalentDetail feature={feature} />
                            </TabPanel>
                        ))}
                    </div>
                </div>
            </Container>
        </section>
    );
}

function TalentDetail({
    feature,
}: {
    feature: (typeof TALENT_FEATURES)[number];
}) {
    return (
        <Reveal className="overflow-hidden rounded-2xl border border-[#E7ECF5] bg-white shadow-soft">
            <div className="p-7 lg:p-8">
                <div className="flex items-start gap-4">
                    <span className="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-[#EEF2FB] text-[#2F54C9]">
                        <feature.icon className="h-5 w-5" aria-hidden />
                    </span>
                    <div>
                        <h3 className="text-[21px] font-semibold text-[#0E1A3A]">
                            {feature.title}
                        </h3>
                        <p className="mt-1.5 text-[15px] leading-relaxed text-[#5B6478]">
                            {feature.body}
                        </p>
                    </div>
                </div>

                <ul className="mt-6 grid gap-3 sm:grid-cols-3">
                    {feature.points.map((point) => (
                        <li
                            key={point}
                            className="flex items-start gap-2.5 rounded-xl border border-[#EDF1F8] bg-[#F8FAFD] px-3.5 py-3"
                        >
                            <Check
                                className="mt-0.5 h-4 w-4 shrink-0 text-[#2F54C9]"
                                aria-hidden
                            />
                            <span className="text-[14px] leading-snug text-[#3B455C]">
                                {point}
                            </span>
                        </li>
                    ))}
                </ul>
            </div>

            {feature.image && (
                <figure className="border-t border-[#EDF1F8] bg-[#F8FAFD] px-4 pt-4 pb-5 sm:px-7">
                    <img
                        src={feature.image}
                        alt={`Tampilan ${feature.title} di AvanaHR`}
                        width={1440}
                        height={900}
                        loading="lazy"
                        decoding="async"
                        className="block w-full rounded-xl border border-[#E1E7F2] shadow-soft"
                    />
                    <figcaption className="mt-3 text-center text-[12px] text-[#8A93A6]">
                        Tangkapan layar aplikasi dengan data demo.
                    </figcaption>
                </figure>
            )}
        </Reveal>
    );
}
