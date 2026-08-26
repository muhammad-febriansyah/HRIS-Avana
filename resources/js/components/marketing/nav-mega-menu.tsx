import { ArrowRight, ChevronDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { SOLUTIONS } from './content';
import type { Solution } from './content';
import { MODULES } from './modules-section';
import type { Module } from './modules-section';

export type ProductMenuGroup = { title: string; items: Module[] };

/**
 * The 14 concrete modules from `MODULES`, grouped into 4 columns for the
 * "Fitur" mega menu. Keep the titles below in sync with `MODULES` in
 * modules-section.tsx — an unknown title throws at import time instead of
 * silently dropping a module from the menu.
 */
const PRODUCT_MENU_GROUP_TITLES: { title: string; modules: string[] }[] = [
    {
        title: 'HR & Karyawan',
        modules: ['Core HR', 'Leave & Cuti', 'Recruitment', 'Performance'],
    },
    {
        title: 'Payroll & Bisnis',
        modules: ['Payroll', 'Settlement', 'CRM'],
    },
    {
        title: 'Attendance & Lapangan',
        modules: ['Attendance', 'Live Tracking', 'Visiting Pekerjaan'],
    },
    {
        title: 'AI & Analytics',
        modules: [
            'AI Intelligence',
            'Workforce Analytics',
            'Rapat & Transkrip',
        ],
    },
    {
        title: 'Kolaborasi & Engagement',
        modules: [
            'HR Helpdesk',
            'Ruang Kita',
            'Pengumuman',
            'Survei Karyawan',
            'Kalender Acara',
        ],
    },
];

/** This group gets a small "Native" badge next to its column header instead
 * of repeating the tag on every item inside. */
export const NATIVE_GROUP_TITLE = 'AI & Analytics';

export const PRODUCT_MENU_GROUPS: ProductMenuGroup[] =
    PRODUCT_MENU_GROUP_TITLES.map((group) => ({
        title: group.title,
        items: group.modules.map((title) => {
            const module = MODULES.find(
                (candidate) => candidate.title === title,
            );

            if (!module) {
                throw new Error(
                    `nav-mega-menu: unknown module "${title}" in PRODUCT_MENU_GROUP_TITLES`,
                );
            }

            return module;
        }),
    }));

export const SOLUTION_MENU_ITEMS: Solution[] = SOLUTIONS;

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
                        'absolute top-full left-1/2 z-50 mt-3 -translate-x-1/2 rounded-2xl border border-avana-border bg-white p-5 shadow-avana-card',
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
 * "Fitur" mega menu — the single consolidated dropdown grouping everything
 * that used to be three separate nav items (Produk & Modul, Solusi Terpadu,
 * AI & Analytics): 4 evenly-sized module-group columns as the main grid,
 * plus "Solusi Terpadu" as a compact chip row underneath.
 */
export function FeaturesMegaMenuPanel({
    groups,
    solutions,
    platformHref,
    solutionHref,
}: {
    groups: ProductMenuGroup[];
    solutions: Solution[];
    platformHref: string;
    solutionHref: string;
}) {
    return (
        <div className="w-[min(980px,calc(100vw-3rem))]">
            <div className="grid grid-cols-2 gap-x-8 gap-y-6 lg:grid-cols-5 lg:gap-x-0 lg:divide-x lg:divide-avana-border">
                {groups.map((group, index) => (
                    <div
                        key={group.title}
                        className={cn(
                            index > 0 && 'lg:pl-5',
                            index < groups.length - 1 && 'lg:pr-5',
                        )}
                    >
                        <p className="mb-3 flex items-center gap-1.5 text-[11px] font-bold tracking-wider text-avana-text/50 uppercase">
                            {group.title}
                            {group.title === NATIVE_GROUP_TITLE && (
                                <span className="rounded-full bg-avana-light px-1.5 py-0.5 text-[9px] font-bold tracking-wider text-avana-blue normal-case">
                                    Native
                                </span>
                            )}
                        </p>
                        <ul className="space-y-3">
                            {group.items.map((item) => {
                                const Icon = item.icon;

                                return (
                                    <li key={item.title}>
                                        <a
                                            href={platformHref}
                                            className="group -m-1.5 flex items-start gap-3 rounded-xl p-1.5 hover:bg-avana-soft"
                                        >
                                            <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-avana-light text-avana-blue">
                                                <Icon
                                                    className="h-4 w-4"
                                                    aria-hidden
                                                />
                                            </span>
                                            <span>
                                                <span className="block text-[13.5px] font-semibold text-avana-navy group-hover:text-avana-blue">
                                                    {item.title}
                                                </span>
                                                <span className="block text-[12px] text-avana-text/60">
                                                    {item.tagline}
                                                </span>
                                            </span>
                                        </a>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </div>

            <div className="mt-6 flex flex-wrap items-center gap-2 border-t border-avana-border pt-5">
                <span className="mr-1 text-[11px] font-bold tracking-wider text-avana-text/50 uppercase">
                    Solusi Terpadu
                </span>
                {solutions.map((solution) => (
                    <a
                        key={solution.id}
                        href={solutionHref}
                        className="rounded-full border border-avana-border px-3 py-1.5 text-[12.5px] font-medium text-avana-text transition-colors hover:border-avana-blue hover:text-avana-blue"
                    >
                        {solution.title}
                    </a>
                ))}
            </div>

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
