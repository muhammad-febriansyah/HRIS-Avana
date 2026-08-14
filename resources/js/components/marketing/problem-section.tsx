import {
    Database,
    Fingerprint,
    GraduationCap,
    MessagesSquare,
    Table2,
    UserPlus,
    Wallet,
} from 'lucide-react';
import { useRef } from 'react';
import { cn } from '@/lib/utils';
import { AnimatedBeam } from './animated-beam';
import type { Icon } from './content';
import { LEGACY_STACK, PAIN_POINTS } from './content';
import { Container, Reveal, SectionHeading } from './reveal';

/** The scattered tools, drawn as circular nodes around the hub. */
const TOOL_ICONS: Record<string, Icon> = {
    HRIS: Database,
    Spreadsheet: Table2,
    Attendance: Fingerprint,
    Payroll: Wallet,
    LMS: GraduationCap,
    Recruitment: UserPlus,
    'Komunikasi manual': MessagesSquare,
};

const LEFT_TOOLS = ['HRIS', 'Spreadsheet', 'Attendance'];
const RIGHT_TOOLS = ['Payroll', 'LMS', 'Recruitment'];

function ToolNode({
    label,
    nodeRef,
}: {
    label: string;
    nodeRef: React.RefObject<HTMLDivElement | null>;
}) {
    const IconMark = TOOL_ICONS[label] ?? Database;

    return (
        <div className="z-10 flex w-24 flex-col items-center gap-2">
            <div
                ref={nodeRef}
                className="grid size-14 place-items-center rounded-full border border-[#E7ECF5] bg-white text-[#6B7280] shadow-soft"
            >
                <IconMark className="h-5 w-5" aria-hidden />
            </div>
            <span className="text-center text-[11.5px] leading-tight text-[#6B7280]">
                {label}
            </span>
        </div>
    );
}

const BEAM = {
    pathColor: '#DCE3F1',
    pathWidth: 1.6,
} as const;

/**
 * Problem framing: the scattered-tooling status quo converging on AvanaHR.
 *
 * Desktop draws the tools as circular nodes either side of the hub, joined by
 * animated beams. Below `lg` the same tools fall back to a chip list, because
 * beams need horizontal room to read as connections.
 */
export function ProblemSection() {
    const containerRef = useRef<HTMLDivElement>(null);
    const hubRef = useRef<HTMLDivElement>(null);
    // One ref per node — the lint rule for the React compiler forbids indexing
    // refs during render, so they are written out instead of held in an array.
    const leftOne = useRef<HTMLDivElement>(null);
    const leftTwo = useRef<HTMLDivElement>(null);
    const leftThree = useRef<HTMLDivElement>(null);
    const rightOne = useRef<HTMLDivElement>(null);
    const rightTwo = useRef<HTMLDivElement>(null);
    const rightThree = useRef<HTMLDivElement>(null);

    return (
        <section className="border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-28">
            <Container>
                <SectionHeading
                    eyebrow="Kondisi hari ini"
                    title="Masih Mengelola HR dengan Banyak Aplikasi?"
                />

                <ul className="mt-12 grid gap-4 sm:grid-cols-2 lg:mt-14">
                    {PAIN_POINTS.map((point, i) => (
                        <Reveal
                            as="li"
                            key={point.title}
                            delay={(i % 2) * 0.06}
                            className="group rounded-2xl border border-[#E7ECF5] bg-white p-6 transition-[border-color,box-shadow] duration-200 hover:border-[#C9D6F0] hover:shadow-lift"
                        >
                            <div className="flex items-start gap-4">
                                <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#EEF2FB] text-[#2F54C9] transition-colors duration-200 group-hover:bg-[#2F54C9] group-hover:text-white">
                                    <point.icon
                                        className="h-5 w-5"
                                        aria-hidden
                                    />
                                </span>
                                <div>
                                    <h3 className="text-[17px] font-semibold text-[#0E1A3A]">
                                        {point.title}
                                    </h3>
                                    <p className="mt-1.5 text-[14px] leading-relaxed text-[#5B6478]">
                                        {point.body}
                                    </p>
                                </div>
                            </div>
                        </Reveal>
                    ))}
                </ul>

                <Reveal delay={0.1}>
                    <div className="relative mt-12 overflow-hidden rounded-3xl border border-[#E7ECF5] bg-white p-7 shadow-soft lg:p-10">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[#2F54C9]/40 to-transparent"
                        />

                        <p className="text-center text-[12px] font-semibold tracking-[0.08em] text-[#8A93A6] uppercase">
                            Sebelum — terpisah-pisah
                        </p>

                        {/* Desktop: nodes wired into the hub. */}
                        <div
                            ref={containerRef}
                            className="relative mx-auto mt-6 hidden h-[320px] w-full max-w-3xl items-center justify-center lg:flex"
                        >
                            <div className="flex h-full w-full items-stretch justify-between">
                                <div className="flex flex-col justify-between py-1">
                                    <ToolNode
                                        label={LEFT_TOOLS[0]}
                                        nodeRef={leftOne}
                                    />
                                    <ToolNode
                                        label={LEFT_TOOLS[1]}
                                        nodeRef={leftTwo}
                                    />
                                    <ToolNode
                                        label={LEFT_TOOLS[2]}
                                        nodeRef={leftThree}
                                    />
                                </div>

                                <div className="flex flex-col justify-center">
                                    <div
                                        ref={hubRef}
                                        className="z-10 grid size-24 place-items-center rounded-full border border-[#E1E7F2] bg-white p-5 shadow-lift"
                                    >
                                        <img
                                            src="/avana/logo-mark.png"
                                            alt="AvanaHR"
                                            width={380}
                                            height={300}
                                            loading="lazy"
                                            className="h-full w-full object-contain"
                                        />
                                    </div>
                                </div>

                                <div className="flex flex-col justify-between py-1">
                                    <ToolNode
                                        label={RIGHT_TOOLS[0]}
                                        nodeRef={rightOne}
                                    />
                                    <ToolNode
                                        label={RIGHT_TOOLS[1]}
                                        nodeRef={rightTwo}
                                    />
                                    <ToolNode
                                        label={RIGHT_TOOLS[2]}
                                        nodeRef={rightThree}
                                    />
                                </div>
                            </div>

                            <AnimatedBeam
                                {...BEAM}
                                containerRef={containerRef}
                                fromRef={leftOne}
                                toRef={hubRef}
                                curvature={-70}
                                duration={4.5}
                            />
                            <AnimatedBeam
                                {...BEAM}
                                containerRef={containerRef}
                                fromRef={leftTwo}
                                toRef={hubRef}
                                delay={0.35}
                                duration={4.5}
                            />
                            <AnimatedBeam
                                {...BEAM}
                                containerRef={containerRef}
                                fromRef={leftThree}
                                toRef={hubRef}
                                curvature={70}
                                delay={0.7}
                                duration={4.5}
                            />
                            <AnimatedBeam
                                {...BEAM}
                                containerRef={containerRef}
                                fromRef={rightOne}
                                toRef={hubRef}
                                curvature={-70}
                                delay={0.2}
                                duration={4.5}
                                reverse
                            />
                            <AnimatedBeam
                                {...BEAM}
                                containerRef={containerRef}
                                fromRef={rightTwo}
                                toRef={hubRef}
                                delay={0.55}
                                duration={4.5}
                                reverse
                            />
                            <AnimatedBeam
                                {...BEAM}
                                containerRef={containerRef}
                                fromRef={rightThree}
                                toRef={hubRef}
                                curvature={70}
                                delay={0.9}
                                duration={4.5}
                                reverse
                            />
                        </div>

                        {/* Mobile & tablet: same story, no beams. */}
                        <ul
                            className={cn(
                                'mt-6 flex flex-wrap justify-center gap-2 lg:hidden',
                            )}
                        >
                            {LEGACY_STACK.map((item) => (
                                <li
                                    key={item}
                                    className="rounded-lg border border-dashed border-[#D8E0EE] bg-[#FAFBFE] px-3 py-1.5 text-[13px] text-[#6B7280]"
                                >
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </div>
                </Reveal>

                <Reveal delay={0.15}>
                    <p className="mt-9 text-center text-[16px] font-medium text-[#0E1A3A] sm:text-[18px]">
                        AvanaHR menyatukan proses tersebut dalam satu ekosistem
                        HR.
                    </p>
                </Reveal>
            </Container>
        </section>
    );
}
