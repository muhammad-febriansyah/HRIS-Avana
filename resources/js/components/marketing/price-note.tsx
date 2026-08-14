import { ArrowUpRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import { PRICE_NOTE } from './content';
import { useCtaTargets } from './use-cta';

/**
 * Starting-price micro-copy.
 *
 * Clicking it opens the WhatsApp conversation so a visitor who reacts to the
 * price lands on sales directly. When no WhatsApp number is configured it falls
 * back to the same destination as the demo CTA, so the line is never a dead
 * link and no contact channel is invented.
 */
export function PriceNote({
    tone = 'light',
    className,
}: {
    tone?: 'light' | 'dark';
    className?: string;
}) {
    const { whatsappWith, demo } = useCtaTargets();
    const whatsapp = whatsappWith(
        `Halo, saya ingin tahu paket harga AvanaHR (${PRICE_NOTE.prefix} ${PRICE_NOTE.amount} ${PRICE_NOTE.period}).`,
    );
    const href = whatsapp ?? demo.href;
    const external = Boolean(whatsapp) || demo.external;

    return (
        <a
            href={href}
            {...(external
                ? { target: '_blank', rel: 'noopener noreferrer' }
                : {})}
            className={cn(
                'group inline-flex items-center gap-1.5 rounded-full px-3.5 py-2 text-[13.5px] transition-colors duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 sm:text-[14px]',
                tone === 'light'
                    ? 'text-[#5B6478] hover:bg-[#F1F5FC] hover:text-[#2F54C9] focus-visible:outline-[#2F54C9]'
                    : 'text-white/70 hover:bg-white/10 hover:text-white focus-visible:outline-white',
                className,
            )}
        >
            <span>
                {PRICE_NOTE.prefix}{' '}
                <strong
                    className={cn(
                        'font-semibold',
                        tone === 'light' ? 'text-[#0E1A3A]' : 'text-white',
                    )}
                >
                    {PRICE_NOTE.amount}
                </strong>{' '}
                {PRICE_NOTE.period}
            </span>
            <ArrowUpRight
                className="h-3.5 w-3.5 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                aria-hidden
            />
            <span className="sr-only">— tanya harga lewat WhatsApp</span>
        </a>
    );
}
