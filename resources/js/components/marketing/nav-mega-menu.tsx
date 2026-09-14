import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { show as featureShow } from '@/routes/features';
import { MODULES } from './modules-section';
import type { Module } from './modules-section';

export type ProductMenuItem = Pick<Module, 'icon'> & {
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

            {open && (
                <div
                    className={cn(
                        'absolute top-full left-1/2 z-50 mt-3 max-h-[calc(100vh-8rem)] -translate-x-1/2 overflow-y-auto overscroll-contain rounded-2xl border border-avana-border bg-white p-5 shadow-avana-card',
                        panelClassName,
                    )}
                >
                    {panel}
                </div>
            )}
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
        <div className="w-full">
            <p className="mb-4 text-[11px] font-bold tracking-wider text-avana-text/50 uppercase">
                Modul AvanaHR
            </p>
            <ul className="grid grid-cols-1 gap-x-8 gap-y-1 sm:grid-cols-2">
                {items.map((item) => {
                    const Icon = item.icon;

                    return (
                        <li key={item.title}>
                            <Link
                                href={item.href}
                                className="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2 text-[14px] font-semibold text-avana-navy transition-colors hover:bg-avana-soft hover:text-avana-blue focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-avana-blue"
                            >
                                <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-avana-light text-avana-blue">
                                    <Icon className="h-4 w-4" aria-hidden />
                                </span>
                                {item.title}
                            </Link>
                        </li>
                    );
                })}
            </ul>

            <a
                href={platformHref}
                className="mt-5 flex items-center justify-between rounded-xl border-t border-avana-border pt-4 text-[13px] font-bold text-avana-blue hover:text-avana-blue-hover"
            >
                Lihat semua fitur
                <ArrowRight className="h-4 w-4" aria-hidden />
            </a>
        </div>
    );
}
