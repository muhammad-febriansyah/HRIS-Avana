import { CheckCircle2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Container, Reveal, SectionHeading } from '../reveal';

/**
 * Shared shape for most of the Keamanan page sections: an eyebrow + title +
 * description, a checklist of concrete controls, and — when the section has
 * one — an illustration alternating left/right down the page.
 *
 * Kept as one parameterized component instead of nine near-identical files:
 * every numbered section on this page is the same "claim + proof list" shape,
 * only the copy and the artwork change.
 */
export function FeatureSection({
    id,
    eyebrow,
    title,
    description,
    image,
    imageAlt,
    imageSide = 'right',
    points,
    tone = 'default',
    children,
}: {
    id?: string;
    eyebrow: string;
    title: ReactNode;
    description?: ReactNode;
    image?: string;
    imageAlt?: string;
    imageSide?: 'left' | 'right';
    points: string[];
    tone?: 'default' | 'muted';
    /** Extra content rendered above the checklist (badges, stat rows, etc). */
    children?: ReactNode;
}) {
    const checklist = (
        <ul className="grid gap-3 sm:grid-cols-2">
            {points.map((point) => (
                <li
                    key={point}
                    className="flex items-start gap-2.5 rounded-xl border border-[#E7ECF5] bg-white p-4 text-[14px] leading-relaxed text-[#3B455C]"
                >
                    <CheckCircle2
                        className="mt-0.5 h-4.5 w-4.5 shrink-0 text-[#16A34A]"
                        aria-hidden
                    />
                    {point}
                </li>
            ))}
        </ul>
    );

    return (
        <section
            id={id}
            className={
                'scroll-mt-24 py-16 lg:py-20 ' +
                (tone === 'muted' ? 'bg-[#F8FAFD]' : '')
            }
        >
            <Container>
                {image ? (
                    <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                        <div
                            className={
                                imageSide === 'left'
                                    ? 'order-1 lg:order-2'
                                    : 'order-1'
                            }
                        >
                            <SectionHeading
                                align="left"
                                eyebrow={eyebrow}
                                title={title}
                                description={description}
                            />
                            {children && <div className="mt-6">{children}</div>}
                            <Reveal delay={0.08} className="mt-8">
                                {checklist}
                            </Reveal>
                        </div>

                        <Reveal
                            delay={0.1}
                            className={
                                'relative ' +
                                (imageSide === 'left'
                                    ? 'order-2 lg:order-1'
                                    : 'order-2')
                            }
                        >
                            <div
                                aria-hidden
                                className="pointer-events-none absolute inset-0 rounded-full bg-[radial-gradient(circle,rgba(49,95,212,0.14)_0%,transparent_70%)]"
                            />
                            <img
                                src={image}
                                alt={imageAlt ?? ''}
                                loading="lazy"
                                className="relative z-10 mx-auto w-full max-w-[600px] object-contain select-none"
                                draggable={false}
                            />
                        </Reveal>
                    </div>
                ) : (
                    <>
                        <SectionHeading
                            eyebrow={eyebrow}
                            title={title}
                            description={description}
                        />
                        {children && (
                            <Reveal delay={0.06} className="mt-10">
                                {children}
                            </Reveal>
                        )}
                        <Reveal delay={0.1} className="mt-10">
                            {checklist}
                        </Reveal>
                    </>
                )}
            </Container>
        </section>
    );
}
