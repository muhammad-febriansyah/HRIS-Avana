import { Link } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, ChevronDown, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { show as featureShow } from '@/routes/features';
import { MODULES } from './modules-section';
import type { Module } from './modules-section';

export type ProductMenuItem = Pick<Module, 'icon' | 'tagline'> & {
    title: string;
    href: string;
};

type ProductMenuDefinition = {
    title: string;
    moduleTitle: string;
    slug: string;
};

/**
 * Parent modules shown in the "Fitur" mega menu. Submodules and feature
 * details stay on their parent pages, matching the product navigation pattern
 * from the reference site.
 */
const PRODUCT_MENU_DEFINITIONS: ProductMenuDefinition[] = [
    {
        title: 'HR Core',
        moduleTitle: 'Core HR',
        slug: 'core-hr',
    },
    {
        title: 'Payroll',
        moduleTitle: 'Payroll',
        slug: 'payroll',
    },
    {
        title: 'Pelatihan',
        moduleTitle: 'Pelatihan',
        slug: 'pelatihan',
    },
    {
        title: 'Recruitment',
        moduleTitle: 'Recruitment',
        slug: 'rekrutmen',
    },
    {
        title: 'Time Management',
        moduleTitle: 'Time Management',
        slug: 'time-management',
    },
    {
        title: 'Manajemen Talenta',
        moduleTitle: 'Manajemen Talenta',
        slug: 'manajemen-talenta',
    },
    {
        title: 'Compensation & Benefits',
        moduleTitle: 'Compensation & Benefits',
        slug: 'compensation-benefits',
    },
    {
        title: 'Expenses & Reimbursement',
        moduleTitle: 'Settlement',
        slug: 'reimbursement',
    },
    {
        title: 'Loans Management',
        moduleTitle: 'Loans Management',
        slug: 'loans-management',
    },
    {
        title: 'KPI',
        moduleTitle: 'KPI',
        slug: 'kpi',
    },
    {
        title: 'AI & Analytics',
        moduleTitle: 'AI & Analytics',
        slug: 'ai-analytics',
    },
];

export const PRODUCT_MENU_ITEMS: ProductMenuItem[] =
    PRODUCT_MENU_DEFINITIONS.map(({ title, moduleTitle, slug }) => {
        const module = MODULES.find(
            (candidate) => candidate.title === moduleTitle,
        );

        if (!module) {
            throw new Error(
                `nav-mega-menu: unknown parent module "${moduleTitle}"`,
            );
        }

        return {
            title,
            tagline: module.tagline,
            icon: module.icon,
            href: featureShow.url(slug),
        };
    });

/**
 * Hover/click-controlled dropdown wrapper for a top-nav item. Desktop only —
 * the mobile drawer renders its own accordion instead (see SiteNavbar).
 */
export function NavDropdown({
    label,
    badge,
    isActive,
    panel,
    panelClassName,
}: {
    label: string;
    badge?: ReactNode;
    isActive: boolean;
    panel: ReactNode;
    panelClassName?: string;
}) {
    const [open, setOpen] = useState(false);
    const closeTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const containerRef = useRef<HTMLLIElement>(null);

    const cancelClose = () => {
        if (closeTimer.current) {
            clearTimeout(closeTimer.current);
            closeTimer.current = null;
        }
    };

    const scheduleClose = () => {
        cancelClose();
        closeTimer.current = setTimeout(() => setOpen(false), 150);
    };

    useEffect(() => {
        if (!open) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };
        const onPointerDown = (event: MouseEvent) => {
            if (
                containerRef.current &&
                !containerRef.current.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener('keydown', onKeyDown);
        document.addEventListener('mousedown', onPointerDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('mousedown', onPointerDown);
        };
    }, [open]);

    useEffect(() => () => cancelClose(), []);

    const panelId = `${label.toLowerCase().replace(/\s+/g, '-')}-menu`;

    return (
        <li
            ref={containerRef}
            onMouseEnter={() => {
                cancelClose();
                setOpen(true);
            }}
            onMouseLeave={scheduleClose}
        >
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                aria-haspopup="true"
                aria-controls={panelId}
                className={cn(
                    'flex items-center gap-1.5 rounded-full px-3.5 py-2 text-[14px] font-medium transition-colors duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-avana-blue',
                    isActive || open
                        ? 'bg-avana-light text-avana-blue'
                        : 'text-avana-text/80 hover:text-avana-blue',
                )}
            >
                {label}
                {badge}
                <ChevronDown
                    className={cn(
                        'h-3.5 w-3.5 transition-transform duration-200',
                        open && 'rotate-180',
                    )}
                    aria-hidden
                />
            </button>

            <div
                id={panelId}
                aria-hidden={!open}
                className={cn(
                    'absolute top-full left-1/2 z-50 mt-3 max-h-[calc(100vh-8rem)] -translate-x-1/2 overflow-y-auto overscroll-contain rounded-[22px] border border-avana-border/80 bg-white p-0 shadow-[0_24px_70px_rgba(16,42,92,.16)] transition-[opacity,visibility,transform] duration-200 ease-out will-change-[opacity,transform] motion-reduce:transition-none',
                    open
                        ? 'visible translate-y-0 opacity-100'
                        : 'pointer-events-none invisible -translate-y-2 opacity-0',
                    panelClassName,
                )}
            >
                {panel}
            </div>
        </li>
    );
}

/**
 * "Fitur" mega menu — only parent modules are shown here. The detailed
 * submodule navigation will live on each parent feature page.
 */
export function FeaturesMegaMenuPanel({
    items,
    platformHref,
}: {
    items: ProductMenuItem[];
    platformHref: string;
}) {
    return (
        <div className="grid w-full grid-cols-1 lg:grid-cols-[276px_minmax(0,1fr)]">
            <div className="relative overflow-hidden bg-gradient-to-br from-avana-navy via-[#172e75] to-avana-blue px-7 py-7 text-white lg:rounded-l-[21px]">
                <div className="pointer-events-none absolute -right-12 -bottom-14 h-40 w-40 rounded-full border border-white/10 bg-white/5" />
                <div className="pointer-events-none absolute -right-5 -bottom-7 h-24 w-24 rounded-full border border-white/10" />
                <div className="relative flex h-full flex-col">
                    <span className="mb-7 inline-flex w-fit items-center gap-1.5 rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-[10px] font-bold tracking-[0.12em] text-blue-100 uppercase backdrop-blur-sm">
                        <Sparkles className="h-3 w-3" aria-hidden />
                        Platform HR
                    </span>
                    <h2 className="max-w-[220px] text-[24px] leading-[1.1] font-bold tracking-[-0.03em]">
                        Satu ekosistem untuk HR yang bergerak cepat.
                    </h2>
                    <p className="mt-4 max-w-[230px] text-[12.5px] leading-relaxed text-blue-100/80">
                        Pilih modul yang ingin Anda jelajahi. Detail fitur ada
                        di halaman masing-masing.
                    </p>
                    <a
                        href={platformHref}
                        className="mt-auto flex items-center justify-between gap-3 border-t border-white/15 pt-6 text-[12.5px] font-bold text-white transition-colors hover:text-blue-200 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white"
                    >
                        Lihat semua fitur
                        <ArrowUpRight className="h-4 w-4" aria-hidden />
                    </a>
                </div>
            </div>

            <div className="min-w-0 p-5 sm:p-6">
                <div className="mb-4 flex items-end justify-between gap-4 border-b border-avana-border/70 pb-3">
                    <div>
                        <p className="text-[10px] font-bold tracking-[0.14em] text-avana-blue uppercase">
                            Modul AvanaHR
                        </p>
                        <p className="mt-1 text-[12px] text-avana-text/55">
                            Fondasi, operasional, dan insight dalam satu
                            platform
                        </p>
                    </div>
                    <span className="hidden shrink-0 text-[11px] font-semibold text-avana-text/40 sm:block">
                        {items.length} modul
                    </span>
                </div>

                <ul className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    {items.map((item) => {
                        const Icon = item.icon;

                        return (
                            <li key={item.title}>
                                <Link
                                    href={item.href}
                                    className="group flex min-h-[66px] items-center gap-3 rounded-[14px] border border-transparent px-3 py-2.5 transition-[background-color,border-color,box-shadow,transform] duration-200 hover:-translate-y-0.5 hover:border-avana-blue/15 hover:bg-avana-soft hover:shadow-[0_8px_20px_rgba(47,84,201,.08)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-avana-blue motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                                >
                                    <span className="grid h-9 w-9 shrink-0 place-items-center rounded-[11px] bg-avana-light text-avana-blue transition-colors duration-200 group-hover:bg-avana-blue group-hover:text-white">
                                        <Icon
                                            className="h-[17px] w-[17px]"
                                            aria-hidden
                                        />
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block truncate text-[13.5px] font-bold text-avana-navy group-hover:text-avana-blue">
                                            {item.title}
                                        </span>
                                        <span className="mt-0.5 block truncate text-[11.5px] text-avana-text/55">
                                            {item.tagline}
                                        </span>
                                    </span>
                                    <ArrowRight
                                        className="ml-auto h-3.5 w-3.5 shrink-0 -translate-x-1 text-avana-blue opacity-0 transition-[opacity,transform] duration-200 group-hover:translate-x-0 group-hover:opacity-100"
                                        aria-hidden
                                    />
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </div>
    );
}
