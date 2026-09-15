import { Link } from '@inertiajs/react';
import { findPublicProductNavigationGroup } from '@/data/public-product-navigation';
import { cn } from '@/lib/utils';
import { show as featureShow } from '@/routes/features';

export function FeatureSubnav({ currentSlug }: { currentSlug: string }) {
    const navigationGroup = findPublicProductNavigationGroup(currentSlug);

    if (!navigationGroup) {
        return null;
    }

    return (
        <nav
            aria-label={`Navigasi modul ${navigationGroup.label}`}
            className="border-b border-[#E3EAF5] bg-white"
        >
            <div className="mx-auto flex max-w-[1200px] overflow-x-auto px-5 sm:px-8">
                {navigationGroup.items.map((item) => {
                    const isActive = item.slug === currentSlug;

                    return (
                        <Link
                            key={item.slug}
                            href={featureShow.url(item.slug)}
                            prefetch
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
