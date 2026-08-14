import { Check } from 'lucide-react';
import { useState } from 'react';
import { ROLES } from './content';
import { Container, Reveal, SectionHeading } from './reveal';
import { TabList, TabPanel } from './tabs';

/**
 * Role-based value — the same platform described from five points of view.
 * The panel is split so the role reads on the left and what it gets on the
 * right, instead of one wide card with three lonely chips inside it.
 */
export function RoleSection() {
    const [active, setActive] = useState(ROLES[0].id);

    return (
        <section className="border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-24">
            <Container>
                <SectionHeading
                    eyebrow="Peran"
                    title="Satu Platform untuk Seluruh Organisasi."
                    description="Setiap peran melihat bagian yang relevan dengan pekerjaannya."
                />

                <div className="mt-10">
                    <TabList
                        items={ROLES.map((role) => ({
                            id: role.id,
                            label: role.label,
                        }))}
                        active={active}
                        onChange={setActive}
                        idPrefix="peran"
                    />

                    <div className="mt-8">
                        {ROLES.map((role) => (
                            <TabPanel
                                key={role.id}
                                id={role.id}
                                idPrefix="peran"
                                active={active}
                            >
                                <Reveal className="mx-auto grid max-w-4xl items-center gap-8 rounded-2xl border border-[#E7ECF5] bg-white p-7 shadow-soft sm:grid-cols-[minmax(0,0.85fr)_minmax(0,1fr)] lg:gap-12 lg:p-9">
                                    <div className="flex gap-4">
                                        <span className="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-[#EEF2FB] text-[#2F54C9]">
                                            <role.icon
                                                className="h-5 w-5"
                                                aria-hidden
                                            />
                                        </span>
                                        <div>
                                            <h3 className="text-[20px] font-semibold text-[#0E1A3A]">
                                                {role.label}
                                            </h3>
                                            <p className="mt-1.5 text-[15px] leading-relaxed text-[#5B6478]">
                                                {role.copy}
                                            </p>
                                        </div>
                                    </div>

                                    <ul className="space-y-2.5 border-t border-[#EDF1F8] pt-6 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-8">
                                        {role.points.map((point) => (
                                            <li
                                                key={point}
                                                className="flex items-start gap-2.5 text-[14.5px] text-[#3B455C]"
                                            >
                                                <Check
                                                    className="mt-0.5 h-4 w-4 shrink-0 text-[#2F54C9]"
                                                    aria-hidden
                                                />
                                                {point}
                                            </li>
                                        ))}
                                    </ul>
                                </Reveal>
                            </TabPanel>
                        ))}
                    </div>
                </div>
            </Container>
        </section>
    );
}
