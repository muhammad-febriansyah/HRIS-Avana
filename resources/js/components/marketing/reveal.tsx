import { motion } from 'motion/react';
import type { ReactNode } from 'react';

/**
 * Scroll-triggered fade-and-rise wrapper for marketing sections.
 *
 * The animation runs once, when the element first enters the viewport. Reduced
 * motion is handled in CSS (`[data-reveal]` in `app.css`) rather than by
 * branching in JavaScript: the page is server-rendered, so a branch on a
 * client-only media query would hydrate into different markup.
 */
export function Reveal({
    children,
    delay = 0,
    className,
    as = 'div',
}: {
    children: ReactNode;
    delay?: number;
    className?: string;
    as?: 'div' | 'li' | 'section';
}) {
    const Component = motion[as];

    return (
        <Component
            data-reveal
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, amount: 0.2, margin: '0px 0px -80px 0px' }}
            transition={{ duration: 0.55, delay, ease: [0.22, 1, 0.36, 1] }}
            className={className}
        >
            {children}
        </Component>
    );
}

/**
 * Eyebrow + heading + supporting paragraph, centred by default. Every section
 * uses this so the vertical rhythm and type scale stay identical across the
 * page.
 */
export function SectionHeading({
    eyebrow,
    title,
    description,
    align = 'center',
    id,
    tone = 'default',
    size = 'default',
}: {
    eyebrow?: string;
    title: ReactNode;
    description?: ReactNode;
    align?: 'center' | 'left';
    id?: string;
    tone?: 'default' | 'dark';
    size?: 'default' | 'compact';
}) {
    const isDark = tone === 'dark';
    const isCompact = size === 'compact';

    return (
        <Reveal
            className={
                align === 'center'
                    ? 'mx-auto max-w-2xl text-center'
                    : 'max-w-2xl'
            }
        >
            {eyebrow && (
                <span
                    className={`inline-flex items-center rounded-full border px-3.5 py-1 text-[12px] font-semibold tracking-[0.08em] uppercase ${isDark ? 'border-white/15 bg-white/10 text-blue-200' : 'border-[#E2E9F6] bg-[#F4F7FD] text-[#2F54C9]'}`}
                >
                    {eyebrow}
                </span>
            )}
            <h2
                id={id}
                className={`mt-5 font-bold tracking-[-0.02em] text-balance ${isCompact ? 'text-[26px] leading-[1.28] sm:text-3xl sm:leading-[1.25] lg:text-[38px] lg:leading-[1.22]' : 'text-[28px] leading-[1.2] sm:text-4xl sm:leading-[1.18] lg:text-[42px] lg:leading-[1.18]'} ${isDark ? 'text-white' : 'text-[#0E1A3A]'}`}
            >
                {title}
            </h2>
            {description && (
                <p
                    className={`mt-6 text-pretty ${isCompact ? 'text-[14px] leading-[1.75] sm:text-base' : 'text-[15px] leading-[1.75] sm:text-[17px]'} ${isDark ? 'text-blue-100/80' : 'text-[#5B6478]'}`}
                >
                    {description}
                </p>
            )}
        </Reveal>
    );
}

/** Consistent page gutter + max content width for every marketing section. */
export function Container({
    children,
    className = '',
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={`mx-auto w-full max-w-[1200px] px-5 sm:px-8 ${className}`}
        >
            {children}
        </div>
    );
}
