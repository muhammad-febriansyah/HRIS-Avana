import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { useCtaTargets } from './use-cta';

const BASE =
    'inline-flex h-11 items-center justify-center gap-2 rounded-full px-7 text-[15px] font-semibold transition-[background-color,border-color,color,box-shadow,transform] duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9] sm:h-12';

const VARIANTS = {
    primary:
        'bg-[#2F54C9] text-white shadow-[0_10px_24px_-10px_rgba(47,84,201,0.55)] hover:bg-[#2546AD] hover:shadow-[0_14px_30px_-10px_rgba(47,84,201,0.6)]',
    secondary:
        'border border-[#D9E0EE] bg-white text-[#0E1A3A] hover:border-[#2F54C9] hover:text-[#2F54C9] hover:shadow-soft',
    inverse:
        'bg-white text-[#0E1A3A] shadow-[0_10px_28px_-12px_rgba(3,10,32,0.55)] hover:bg-[#EEF2FB]',
    ghostInverse:
        'border border-white/30 text-white hover:bg-white/10 hover:border-white/50',
} as const;

type Variant = keyof typeof VARIANTS;

function CtaLink({
    href,
    external,
    variant,
    className,
    children,
}: {
    href: string;
    external: boolean;
    variant: Variant;
    className?: string;
    children: ReactNode;
}) {
    const classes = cn(BASE, VARIANTS[variant], className);

    if (external || href.startsWith('#')) {
        return (
            <a
                href={href}
                className={classes}
                {...(external
                    ? { target: '_blank', rel: 'noopener noreferrer' }
                    : {})}
            >
                {children}
            </a>
        );
    }

    return (
        <Link href={href} className={classes}>
            {children}
        </Link>
    );
}

/** "Jadwalkan Demo" — the primary conversion action on the page. */
export function DemoButton({
    variant = 'primary',
    className,
    withArrow = true,
    children = 'Jadwalkan Demo',
}: {
    variant?: Variant;
    className?: string;
    withArrow?: boolean;
    /** Overrides the default "Jadwalkan Demo" label — the destination stays the same. */
    children?: ReactNode;
}) {
    const { demo } = useCtaTargets();

    return (
        <CtaLink
            href={demo.href}
            external={demo.external}
            variant={variant}
            className={className}
        >
            {children}
            {withArrow && <ArrowRight className="h-4 w-4" aria-hidden />}
        </CtaLink>
    );
}

/** "Coba Avana" — sends the visitor into the existing registration flow. */
export function TrialButton({
    variant = 'secondary',
    className,
}: {
    variant?: Variant;
    className?: string;
}) {
    const { trial } = useCtaTargets();

    return (
        <CtaLink
            href={trial}
            external={false}
            variant={variant}
            className={className}
        >
            Coba Avana
        </CtaLink>
    );
}
