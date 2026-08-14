import { useRef } from 'react';
import { AnimatedBeam } from './animated-beam';
import { BrandLogo } from './brand-logo';
import { LEGACY_STACK } from './content';
import { Container, Reveal, SectionHeading } from './reveal';

/** The six modules drawn around the hub; the rest ride along as plain chips. */
const HUB_LEFT = ['HR Management', 'Attendance', 'ESS'];
const HUB_RIGHT = ['Payroll', 'Talent', 'Analytics'];
const HUB_REST = ['Finance', 'Learning', 'AI Assistant'];

function ModuleNode({
    label,
    nodeRef,
    align = 'left',
}: {
    label: string;
    nodeRef: React.RefObject<HTMLDivElement | null>;
    align?: 'left' | 'right';
}) {
    return (
        <div
            ref={nodeRef}
            className={
                'z-10 w-fit rounded-lg border border-white/15 bg-white/10 px-3.5 py-2 text-[13px] font-medium text-white ' +
                (align === 'right' ? 'text-right' : '')
            }
        >
            {label}
        </div>
    );
}

/**
 * Before / with AvanaHR. The left column stays deliberately loose and dashed;
 * the right one is a connected hub — every module joined to AvanaHR by a beam,
 * so the layout itself makes the argument.
 */
export function PlatformComparison({
    brand = 'AvanaHR',
    logo = '/avana/logo-full.png',
}: {
    brand?: string;
    logo?: string;
}) {
    const hubContainerRef = useRef<HTMLDivElement>(null);
    const hubRef = useRef<HTMLDivElement>(null);
    const leftOne = useRef<HTMLDivElement>(null);
    const leftTwo = useRef<HTMLDivElement>(null);
    const leftThree = useRef<HTMLDivElement>(null);
    const rightOne = useRef<HTMLDivElement>(null);
    const rightTwo = useRef<HTMLDivElement>(null);
    const rightThree = useRef<HTMLDivElement>(null);

    return (
        <section className="py-20 lg:py-28">
            <Container>
                <SectionHeading
                    eyebrow="Perbandingan"
                    title="Lebih Sedikit Sistem. Lebih Banyak Keterhubungan."
                />

                <div className="mt-14 grid gap-6 lg:grid-cols-2 lg:gap-8">
                    <Reveal className="rounded-2xl border border-[#E7ECF5] bg-[#F8FAFD] p-7 lg:p-8">
                        <h3 className="text-[13px] font-semibold tracking-widest text-[#8A93A6] uppercase">
                            Sebelum
                        </h3>
                        <ul className="mt-6 flex flex-wrap gap-2.5">
                            {LEGACY_STACK.map((item) => (
                                <li
                                    key={item}
                                    className="rounded-lg border border-dashed border-[#D4DDEC] bg-white px-3.5 py-2 text-[14px] text-[#6B7280]"
                                >
                                    {item}
                                </li>
                            ))}
                        </ul>
                        <p className="mt-6 text-[14px] text-[#8A93A6]">
                            Setiap kotak berdiri sendiri — datanya harus
                            disatukan secara manual.
                        </p>
                    </Reveal>

                    <Reveal
                        delay={0.08}
                        className="overflow-hidden rounded-2xl bg-[#0E1A3A] p-7 lg:p-8"
                    >
                        <h3 className="text-[13px] font-semibold tracking-widest text-white/50 uppercase">
                            Dengan AvanaHR
                        </h3>

                        {/* Desktop: modules wired into the hub. */}
                        <div
                            ref={hubContainerRef}
                            className="relative mt-8 hidden items-center justify-between gap-6 sm:flex"
                        >
                            <div className="flex flex-col items-start gap-3.5">
                                <ModuleNode
                                    label={HUB_LEFT[0]}
                                    nodeRef={leftOne}
                                />
                                <ModuleNode
                                    label={HUB_LEFT[1]}
                                    nodeRef={leftTwo}
                                />
                                <ModuleNode
                                    label={HUB_LEFT[2]}
                                    nodeRef={leftThree}
                                />
                            </div>

                            <div
                                ref={hubRef}
                                className="z-10 grid shrink-0 place-items-center rounded-2xl border border-white/20 bg-white px-6 py-4 shadow-[0_16px_40px_-16px_rgba(47,84,201,0.8)]"
                            >
                                <BrandLogo
                                    src={logo}
                                    alt={brand}
                                    className="h-9 w-auto object-contain"
                                />
                            </div>

                            <div className="flex flex-col items-end gap-3.5">
                                <ModuleNode
                                    label={HUB_RIGHT[0]}
                                    nodeRef={rightOne}
                                    align="right"
                                />
                                <ModuleNode
                                    label={HUB_RIGHT[1]}
                                    nodeRef={rightTwo}
                                    align="right"
                                />
                                <ModuleNode
                                    label={HUB_RIGHT[2]}
                                    nodeRef={rightThree}
                                    align="right"
                                />
                            </div>

                            <AnimatedBeam
                                containerRef={hubContainerRef}
                                fromRef={hubRef}
                                toRef={leftOne}
                                curvature={26}
                                duration={4}
                                pathColor="rgba(255,255,255,0.18)"
                                gradientStartColor="#6E9BE6"
                                gradientStopColor="#FFFFFF"
                            />
                            <AnimatedBeam
                                containerRef={hubContainerRef}
                                fromRef={hubRef}
                                toRef={leftTwo}
                                curvature={0}
                                delay={0.4}
                                duration={4}
                                pathColor="rgba(255,255,255,0.18)"
                                gradientStartColor="#6E9BE6"
                                gradientStopColor="#FFFFFF"
                            />
                            <AnimatedBeam
                                containerRef={hubContainerRef}
                                fromRef={hubRef}
                                toRef={leftThree}
                                curvature={-26}
                                delay={0.8}
                                duration={4}
                                pathColor="rgba(255,255,255,0.18)"
                                gradientStartColor="#6E9BE6"
                                gradientStopColor="#FFFFFF"
                            />
                            <AnimatedBeam
                                containerRef={hubContainerRef}
                                fromRef={hubRef}
                                toRef={rightOne}
                                curvature={26}
                                delay={0.2}
                                duration={4}
                                pathColor="rgba(255,255,255,0.18)"
                                gradientStartColor="#6E9BE6"
                                gradientStopColor="#FFFFFF"
                            />
                            <AnimatedBeam
                                containerRef={hubContainerRef}
                                fromRef={hubRef}
                                toRef={rightTwo}
                                curvature={0}
                                delay={0.6}
                                duration={4}
                                pathColor="rgba(255,255,255,0.18)"
                                gradientStartColor="#6E9BE6"
                                gradientStopColor="#FFFFFF"
                            />
                            <AnimatedBeam
                                containerRef={hubContainerRef}
                                fromRef={hubRef}
                                toRef={rightThree}
                                curvature={-26}
                                delay={1}
                                duration={4}
                                pathColor="rgba(255,255,255,0.18)"
                                gradientStartColor="#6E9BE6"
                                gradientStopColor="#FFFFFF"
                            />
                        </div>

                        {/* Mobile: the same modules, no beams. */}
                        <ul className="mt-6 flex flex-wrap gap-2.5 sm:hidden">
                            {[...HUB_LEFT, ...HUB_RIGHT, ...HUB_REST].map(
                                (item) => (
                                    <li
                                        key={item}
                                        className="rounded-lg border border-white/15 bg-white/10 px-3.5 py-2 text-[14px] font-medium text-white"
                                    >
                                        {item}
                                    </li>
                                ),
                            )}
                        </ul>

                        <ul className="mt-7 hidden flex-wrap justify-center gap-2.5 sm:flex">
                            {HUB_REST.map((item) => (
                                <li
                                    key={item}
                                    className="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-[12.5px] text-white/70"
                                >
                                    {item}
                                </li>
                            ))}
                        </ul>

                        <p className="mt-6 text-[14px] text-white/60">
                            Semuanya berada di satu platform dan memakai data
                            karyawan yang sama.
                        </p>
                    </Reveal>
                </div>
            </Container>
        </section>
    );
}
