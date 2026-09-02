import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Menu, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import { dashboard, liveTracking, login, partnership, security } from '@/routes';
import { BrandLogo } from './brand-logo';
import { NAV_ITEMS } from './content';
import {
    FeaturesMegaMenuPanel,
    NATIVE_GROUP_TITLE,
    NavDropdown,
    PRODUCT_MENU_GROUPS,
    SOLUTION_MENU_ITEMS,
} from './nav-mega-menu';
import { useCtaTargets } from './use-cta';

/** Which top-nav items open a dropdown/mega menu instead of jumping straight
 * to their anchor — keyed by `NAV_ITEMS[].name`. "Fitur" groups what used to
 * be three separate items (Produk & Modul, Solusi Terpadu, AI & Analytics)
 * into a single mega menu. */
const MENU_BY_NAME: Record<string, 'features' | undefined> = {
    Fitur: 'features',
};

/**
 * Sticky marketing navbar, drawn as a full-width fixed bar.
 *
 * It starts transparent over the hero, then picks up a hairline border, a
 * blurred white background and a soft shadow once the page scrolls — the
 * same shell language as the rest of the site (avana-navy text, avana-blue
 * accents). The bar's height never animates, so the anchors and the actions
 * never collide on narrow desktops.
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
    activePage?: 'live-tracking' | 'partnership' | 'security';
}) {
    const { auth } = usePage().props;
    const { trial: trialUrl, trialExternal } = useCtaTargets();
    const [scrolled, setScrolled] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [mobileExpanded, setMobileExpanded] = useState<string | null>(null);
    const [activeSection, setActiveSection] = useState('');
    const [infoBarOpen, setInfoBarOpen] = useState(true);

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
    const securityUrl = security().url;
    const partnershipUrl = partnership().url;
    const items = [
        ...NAV_ITEMS.map((item) => ({
            name: item.name,
            badge: item.badge,
            href: `${anchorPrefix}${item.link}`,
            isActive: !anchorPrefix && activeSection === item.link,
            isRoute: false,
            menu: MENU_BY_NAME[item.name],
        })),
        {
            name: 'Live Tracking',
            badge: undefined as string | undefined,
            href: trackingUrl,
            isActive: activePage === 'live-tracking',
            isRoute: true,
            menu: undefined,
        },
        {
            name: 'Keamanan',
            badge: undefined as string | undefined,
            href: securityUrl,
            isActive: activePage === 'security',
            isRoute: true,
            menu: undefined,
        },
        {
            name: 'Daftar Partner',
            badge: undefined as string | undefined,
            href: partnershipUrl,
            isActive: activePage === 'partnership',
            isRoute: true,
            menu: undefined,
        },
    ];

    return (
        <header
            className={cn(
                'sticky top-0 z-50 transition-[background-color,box-shadow,border-color] duration-300',
                scrolled || mobileOpen
                    ? 'border-b border-avana-border/60 bg-white/95 shadow-avana-subtle backdrop-blur-md'
                    : 'border-b border-transparent bg-white/80 backdrop-blur-sm',
            )}
        >
            {infoBarOpen && (
                <div className="relative flex items-center justify-between gap-2 border-b border-avana-blue/30 bg-avana-navy px-4 py-2.5 text-[11px] text-white shadow-sm sm:text-xs">
                    <div className="flex flex-1 flex-col items-center justify-center gap-1.5 pr-6 text-center sm:flex-row sm:gap-3">
                        <p className="font-medium text-blue-100/90">
                            Berhenti buang waktu rekap manual! Transformasi HR
                            Anda sekarang &amp; nikmati{' '}
                            <strong className="text-white">
                                Gratis Ujicoba 3 Bulan
                            </strong>
                            .
                        </p>
                        <a
                            href="#harga"
                            className="font-bold text-white underline hover:text-blue-200"
                        >
                            Selengkapnya
                        </a>
                    </div>
                    <button
                        type="button"
                        onClick={() => setInfoBarOpen(false)}
                        aria-label="Tutup"
                        className="absolute top-1/2 right-2 -translate-y-1/2 rounded-full p-1 hover:bg-white/20 sm:right-4"
                    >
                        <X className="h-4 w-4" aria-hidden />
                    </button>
                </div>
            )}

            <nav
                aria-label="Navigasi utama"
                className="mx-auto flex h-16 w-full max-w-[1200px] items-center justify-between gap-3 px-5 sm:px-8 lg:h-[72px]"
            >
                <Link
                    href="/"
                    className="flex shrink-0 items-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-avana-blue"
                >
                    <BrandLogo
                        src={logo}
                        alt={`${brand} — beranda`}
                        className="h-8 w-auto object-contain lg:h-9"
                    />
                </Link>

                <ul className="hidden items-center gap-1 lg:flex">
                    {items.map((item) => {
                        const className = cn(
                            'flex items-center gap-1.5 rounded-full px-3.5 py-2 text-[14px] font-medium transition-colors duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-avana-blue',
                            item.isActive
                                ? 'bg-avana-light text-avana-blue'
                                : 'text-avana-text/80 hover:text-avana-blue',
                        );
                        const badge = item.badge && (
                            <span className="rounded-full bg-avana-light px-2 py-0.5 text-[10.5px] font-bold tracking-wider text-avana-blue uppercase">
                                {item.badge}
                            </span>
                        );

                        if (item.menu === 'features') {
                            return (
                                <NavDropdown
                                    key={item.href}
                                    label={item.name}
                                    badge={badge}
                                    isActive={item.isActive}
                                    panel={
                                        <FeaturesMegaMenuPanel
                                            groups={PRODUCT_MENU_GROUPS}
                                            solutions={SOLUTION_MENU_ITEMS}
                                            platformHref={item.href}
                                            solutionHref={`${anchorPrefix}#solusi`}
                                        />
                                    }
                                />
                            );
                        }

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
                                        {badge}
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
                                        {badge}
                                    </a>
                                )}
                            </li>
                        );
                    })}
                </ul>

                <div className="hidden shrink-0 items-center gap-4 lg:flex">
                    {auth.user ? (
                        <Link
                            href={dashboard().url}
                            className="inline-flex h-11 items-center rounded-full bg-avana-blue px-6 text-[14px] font-bold text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-avana-blue-hover hover:shadow-avana-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-avana-blue active:translate-y-0"
                        >
                            Dashboard
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={login().url}
                                className="inline-flex h-11 items-center rounded-full border border-avana-border px-5 text-[14px] font-bold text-avana-text/80 transition-colors duration-200 hover:border-avana-blue hover:text-avana-blue focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-avana-blue"
                            >
                                Masuk
                            </Link>
                            <a
                                href={trialUrl}
                                target={trialExternal ? '_blank' : undefined}
                                rel={
                                    trialExternal
                                        ? 'noopener noreferrer'
                                        : undefined
                                }
                                className="inline-flex h-11 items-center rounded-full bg-avana-blue px-6 text-[14px] font-bold text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-avana-blue-hover hover:shadow-avana-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-avana-blue active:translate-y-0"
                            >
                                Coba Gratis
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
                        'ml-auto grid h-10 w-10 cursor-pointer place-items-center rounded-full text-avana-text transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-avana-blue lg:hidden',
                        mobileOpen ? 'bg-avana-light' : 'hover:bg-avana-soft',
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
                    className="max-h-[calc(100dvh-11rem)] overflow-y-auto overscroll-contain border-t border-avana-border bg-white px-5 pt-4 pb-6 shadow-avana-card lg:hidden"
                >
                    <ul className="space-y-0.5">
                        {items.map((item) => {
                            const className = cn(
                                'flex items-center justify-between gap-2 rounded-xl px-4 py-3 text-[15px] font-medium',
                                item.isActive
                                    ? 'bg-avana-light text-avana-blue'
                                    : 'text-avana-text hover:bg-avana-soft',
                            );
                            const badge = item.badge && (
                                <span className="rounded-full bg-avana-light px-2 py-0.5 text-[10px] font-bold tracking-wider text-avana-blue uppercase">
                                    {item.badge}
                                </span>
                            );

                            if (item.menu) {
                                const expanded = mobileExpanded === item.name;

                                return (
                                    <li key={item.href}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setMobileExpanded(
                                                    expanded ? null : item.name,
                                                )
                                            }
                                            aria-expanded={expanded}
                                            className={className}
                                        >
                                            <span className="flex items-center gap-2">
                                                {item.name}
                                                {badge}
                                            </span>
                                            <ChevronDown
                                                className={cn(
                                                    'h-4 w-4 shrink-0 transition-transform duration-200',
                                                    expanded && 'rotate-180',
                                                )}
                                                aria-hidden
                                            />
                                        </button>

                                        {expanded && (
                                            <div className="mt-1 mb-2 space-y-4 rounded-xl bg-avana-soft px-4 py-3">
                                                {PRODUCT_MENU_GROUPS.map(
                                                    (group) => (
                                                        <div key={group.title}>
                                                            <p className="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold tracking-wider text-avana-text/50 uppercase">
                                                                {group.title}
                                                                {group.title ===
                                                                    NATIVE_GROUP_TITLE && (
                                                                    <span className="rounded-full bg-avana-light px-1.5 py-0.5 text-[9px] font-bold tracking-wider text-avana-blue normal-case">
                                                                        Native
                                                                    </span>
                                                                )}
                                                            </p>
                                                            <ul className="space-y-0.5">
                                                                {group.items.map(
                                                                    (
                                                                        module,
                                                                    ) => (
                                                                        <li
                                                                            key={
                                                                                module.title
                                                                            }
                                                                        >
                                                                            <a
                                                                                href={
                                                                                    item.href
                                                                                }
                                                                                onClick={() => {
                                                                                    setMobileOpen(
                                                                                        false,
                                                                                    );
                                                                                    setMobileExpanded(
                                                                                        null,
                                                                                    );
                                                                                }}
                                                                                className="block rounded-lg px-2 py-1.5 text-[14px] text-avana-text hover:bg-white"
                                                                            >
                                                                                {
                                                                                    module.title
                                                                                }
                                                                            </a>
                                                                        </li>
                                                                    ),
                                                                )}
                                                            </ul>
                                                        </div>
                                                    ),
                                                )}

                                                <div>
                                                    <p className="mb-1.5 text-[11px] font-bold tracking-wider text-avana-text/50 uppercase">
                                                        Solusi Terpadu
                                                    </p>
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {SOLUTION_MENU_ITEMS.map(
                                                            (solution) => (
                                                                <a
                                                                    key={
                                                                        solution.id
                                                                    }
                                                                    href={`${anchorPrefix}#solusi`}
                                                                    onClick={() => {
                                                                        setMobileOpen(
                                                                            false,
                                                                        );
                                                                        setMobileExpanded(
                                                                            null,
                                                                        );
                                                                    }}
                                                                    className="rounded-full border border-avana-border bg-white px-2.5 py-1 text-[12.5px] font-medium text-avana-text"
                                                                >
                                                                    {
                                                                        solution.title
                                                                    }
                                                                </a>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </li>
                                );
                            }

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
                                            {badge}
                                        </Link>
                                    ) : (
                                        <a
                                            href={item.href}
                                            onClick={() => setMobileOpen(false)}
                                            className={className}
                                        >
                                            {item.name}
                                            {badge}
                                        </a>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                    <div className="mt-3 flex flex-col gap-2.5 border-t border-avana-border pt-4">
                        {auth.user ? (
                            <Link
                                href={dashboard().url}
                                className="inline-flex h-12 items-center justify-center rounded-full bg-avana-blue text-[15px] font-bold text-white"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login().url}
                                    onClick={() => setMobileOpen(false)}
                                    className="inline-flex h-12 items-center justify-center rounded-full border border-avana-border text-[15px] font-bold text-avana-text"
                                >
                                    Masuk
                                </Link>
                                <a
                                    href={trialUrl}
                                    target={
                                        trialExternal ? '_blank' : undefined
                                    }
                                    rel={
                                        trialExternal
                                            ? 'noopener noreferrer'
                                            : undefined
                                    }
                                    className="inline-flex h-12 items-center justify-center rounded-full bg-avana-blue text-[15px] font-bold text-white"
                                >
                                    Coba Gratis
                                </a>
                            </>
                        )}
                    </div>
                </div>
            )}
        </header>
    );
}
