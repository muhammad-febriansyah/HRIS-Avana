import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import { dashboard, liveTracking } from '@/routes';
import { BrandLogo } from './brand-logo';
import { NAV_ITEMS } from './content';
import { useCtaTargets } from './use-cta';

/**
 * Sticky marketing navbar, drawn as a floating pill.
 *
 * The pill starts transparent over the hero, then picks up a hairline border,
 * a blurred background and a very soft shadow once the page scrolls. The width
 * never animates, so the six anchors and the two actions never collide on
 * narrow desktops.
 */
export function SiteNavbar({
    brand,
    logo,
    anchorPrefix = '',
    activePage,
}: {
    brand: string;
    logo: string;
    /**
     * Prefix for the section anchors. Feature pages pass "/" so the anchors
     * jump back to the landing page instead of looking for sections that only
     * exist there.
     */
    anchorPrefix?: string;
    /** Marks a standalone marketing page as the current one. */
    activePage?: 'live-tracking';
}) {
    const { auth } = usePage().props;
    const { demo, login: loginUrl } = useCtaTargets();
    const [scrolled, setScrolled] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [activeSection, setActiveSection] = useState('');

    useEffect(() => {
        const onScroll = () => {
            setScrolled(window.scrollY > 24);

            // Back at the top of the page, "Home" is the current item again.
            if (window.scrollY < 300) {
                setActiveSection('#top');
            }
        };

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    // Scrollspy: highlight the nav item whose section is currently in view.
    // Only meaningful when the anchors belong to this page.
    useEffect(() => {
        if (anchorPrefix) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setActiveSection(`#${entry.target.id}`);
                    }
                });
            },
            { rootMargin: '-45% 0px -50% 0px' },
        );

        // "#top" wraps the whole page, so observing it would keep it in view
        // forever; the scroll handler above owns that item instead.
        NAV_ITEMS.filter((item) => item.link !== '#top').forEach((item) => {
            const el = document.getElementById(item.link.slice(1));

            if (el) {
                observer.observe(el);
            }
        });

        return () => observer.disconnect();
    }, [anchorPrefix]);

    const trackingUrl = liveTracking().url;
    const items = [
        ...NAV_ITEMS.map((item) => ({
            name: item.name,
            href: `${anchorPrefix}${item.link}`,
            isActive: !anchorPrefix && activeSection === item.link,
            isRoute: false,
        })),
        {
            name: 'Live Tracking',
            href: trackingUrl,
            isActive: activePage === 'live-tracking',
            isRoute: true,
        },
    ];

    const demoProps = demo.external
        ? {
              href: demo.href,
              target: '_blank' as const,
              rel: 'noopener noreferrer',
          }
        : { href: demo.href };

    return (
        <header className="sticky top-0 z-50 px-4 pt-3 sm:px-6 sm:pt-4">
            <nav
                aria-label="Navigasi utama"
                className={cn(
                    'relative mx-auto flex h-14 w-full max-w-[1080px] items-center justify-between gap-3 rounded-full border pr-2 pl-5 transition-[background-color,box-shadow,border-color] duration-300 lg:h-16',
                    scrolled || mobileOpen
                        ? 'border-[#E7ECF5] bg-white/85 shadow-soft backdrop-blur-xl'
                        : 'border-transparent bg-transparent',
                )}
            >
                <Link
                    href="/"
                    className="flex shrink-0 items-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#2F54C9]"
                >
                    <BrandLogo
                        src={logo}
                        alt={`${brand} — beranda`}
                        className="h-7 w-auto object-contain lg:h-8"
                    />
                </Link>

                <ul className="absolute left-1/2 hidden -translate-x-1/2 items-center gap-0.5 lg:flex">
                    {items.map((item) => {
                        const className = cn(
                            'rounded-full px-3 py-2 text-[13.5px] font-medium transition-colors duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9]',
                            item.isActive
                                ? 'bg-[#2F54C9]/10 text-[#2F54C9]'
                                : 'text-[#3B455C] hover:bg-[#F4F6FB] hover:text-[#0E1A3A]',
                        );

                        return (
                            <li key={item.href}>
                                {item.isRoute ? (
                                    <Link
                                        href={item.href}
                                        aria-current={
                                            item.isActive ? 'page' : undefined
                                        }
                                        className={className}
                                    >
                                        {item.name}
                                    </Link>
                                ) : (
                                    <a
                                        href={item.href}
                                        aria-current={
                                            item.isActive ? 'true' : undefined
                                        }
                                        className={className}
                                    >
                                        {item.name}
                                    </a>
                                )}
                            </li>
                        );
                    })}
                </ul>

                <div className="hidden shrink-0 items-center gap-2 lg:flex">
                    {auth.user ? (
                        <Link
                            href={dashboard().url}
                            className="inline-flex h-10 items-center rounded-full bg-[#2F54C9] px-5 text-[14px] font-semibold text-white shadow-[0_8px_20px_-8px_rgba(47,84,201,0.55)] transition-colors hover:bg-[#2546AD] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9]"
                        >
                            Dashboard
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={loginUrl}
                                className="inline-flex h-10 items-center rounded-full border border-[#DDE5F5] bg-white px-4 text-[14px] font-semibold text-[#0E1A3A] transition-colors hover:border-[#2F54C9] hover:text-[#2F54C9] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9]"
                            >
                                Masuk
                            </Link>
                            <a
                                {...demoProps}
                                className="inline-flex h-10 items-center rounded-full bg-[#2F54C9] px-5 text-[14px] font-semibold text-white shadow-[0_8px_20px_-8px_rgba(47,84,201,0.55)] transition-colors hover:bg-[#2546AD] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9]"
                            >
                                Jadwalkan Demo
                            </a>
                        </>
                    )}
                </div>

                <button
                    type="button"
                    onClick={() => setMobileOpen((open) => !open)}
                    aria-expanded={mobileOpen}
                    aria-controls="menu-mobile"
                    aria-label={mobileOpen ? 'Tutup menu' : 'Buka menu'}
                    className={cn(
                        'ml-auto grid h-10 w-10 cursor-pointer place-items-center rounded-full text-[#0E1A3A] transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9] lg:hidden',
                        mobileOpen ? 'bg-[#EEF2FB]' : 'hover:bg-[#F4F6FB]',
                    )}
                >
                    {mobileOpen ? (
                        <X className="h-5 w-5" aria-hidden />
                    ) : (
                        <Menu className="h-5 w-5" aria-hidden />
                    )}
                </button>
            </nav>

            {mobileOpen && (
                <div
                    id="menu-mobile"
                    className="mx-auto mt-2 w-full max-w-[1080px] rounded-3xl border border-[#E7ECF5] bg-white/95 p-3 shadow-lift backdrop-blur-xl lg:hidden"
                >
                    <ul className="space-y-0.5">
                        {items.map((item) => {
                            const className = cn(
                                'block rounded-xl px-4 py-3 text-[15px] font-medium',
                                item.isActive
                                    ? 'bg-[#2F54C9]/10 text-[#2F54C9]'
                                    : 'text-[#1A2333] hover:bg-[#F4F6FB]',
                            );

                            return (
                                <li key={item.href}>
                                    {item.isRoute ? (
                                        <Link
                                            href={item.href}
                                            onClick={() => setMobileOpen(false)}
                                            aria-current={
                                                item.isActive
                                                    ? 'page'
                                                    : undefined
                                            }
                                            className={className}
                                        >
                                            {item.name}
                                        </Link>
                                    ) : (
                                        <a
                                            href={item.href}
                                            onClick={() => setMobileOpen(false)}
                                            className={className}
                                        >
                                            {item.name}
                                        </a>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                    <div className="mt-3 flex flex-col gap-2 border-t border-[#EDF1F8] pt-3">
                        {auth.user ? (
                            <Link
                                href={dashboard().url}
                                className="inline-flex h-11 items-center justify-center rounded-full bg-[#2F54C9] text-[15px] font-semibold text-white"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={loginUrl}
                                    className="inline-flex h-11 items-center justify-center rounded-full border border-[#DFE6F4] text-[15px] font-semibold text-[#0E1A3A]"
                                >
                                    Masuk
                                </Link>
                                <a
                                    {...demoProps}
                                    className="inline-flex h-11 items-center justify-center rounded-full bg-[#2F54C9] text-[15px] font-semibold text-white"
                                >
                                    Jadwalkan Demo
                                </a>
                            </>
                        )}
                    </div>
                </div>
            )}
        </header>
    );
}
