import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { show as featureShow } from '@/routes/features';

type FeatureSubnavItem = {
    label: string;
    slug: string;
};

const CORE_HR_SUBNAV_ITEMS: FeatureSubnavItem[] = [
    { label: 'Core HR', slug: 'core-hr' },
    { label: 'Struktur Organisasi', slug: 'struktur-organisasi' },
    { label: 'Data Karyawan', slug: 'data-karyawan' },
    { label: 'Administrasi Karier', slug: 'administrasi-karier' },
    { label: 'Helpdesk HR', slug: 'hr-helpdesk' },
    { label: 'ESS', slug: 'ess' },
    { label: 'Otomatisasi Alur Kerja', slug: 'otomatisasi-alur-kerja' },
];

const CORE_HR_SLUGS = new Set(CORE_HR_SUBNAV_ITEMS.map((item) => item.slug));

export function FeatureSubnav({ currentSlug }: { currentSlug: string }) {
    if (!CORE_HR_SLUGS.has(currentSlug)) {
        return null;
    }

    return (
        <nav
            aria-label="Navigasi Core HR"
            className="border-b border-[#E3EAF5] bg-white"
        >
            <div className="mx-auto flex max-w-[1200px] overflow-x-auto px-5 sm:px-8">
                {CORE_HR_SUBNAV_ITEMS.map((item) => {
                    const isActive = item.slug === currentSlug;

                    return (
                        <Link
                            key={item.slug}
                            href={featureShow.url(item.slug)}
                            aria-current={isActive ? 'page' : undefined}
                            className={cn(
                                'relative flex min-h-14 shrink-0 items-center px-4 text-[13px] font-semibold whitespace-nowrap transition-colors first:pl-0 last:pr-0 focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[#315FD4] sm:px-5',
                                isActive
                                    ? 'text-[#315FD4]'
                                    : 'text-[#475467] hover:text-[#315FD4]',
                            )}
                        >
                            {item.label}
                            <span
                                aria-hidden
                                className={cn(
                                    'absolute right-4 bottom-0 left-4 h-0.5 rounded-full transition-colors sm:right-5 sm:left-5',
                                    isActive
                                        ? 'bg-[#315FD4]'
                                        : 'bg-transparent',
                                )}
                            />
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
