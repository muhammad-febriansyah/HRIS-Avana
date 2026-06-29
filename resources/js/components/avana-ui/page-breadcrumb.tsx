import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { cn } from '@/lib/utils';

interface PageBreadcrumbItem {
    label: string;
    href?: string;
}

interface PageBreadcrumbProps {
    items: PageBreadcrumbItem[];
    className?: string;
}

/**
 * Renders an AvanaHR breadcrumb trail with chevron separators. The last item
 * (or any item without an href) is rendered as the current page.
 */
export function PageBreadcrumb({ items, className }: PageBreadcrumbProps) {
    return (
        <Breadcrumb className={cn('mb-3', className)}>
            <BreadcrumbList>
                {items.map((item, index) => {
                    const isLast = index === items.length - 1;

                    return (
                        <Fragment key={`${item.label}-${index}`}>
                            <BreadcrumbItem>
                                {isLast || !item.href ? (
                                    <BreadcrumbPage>
                                        {item.label}
                                    </BreadcrumbPage>
                                ) : (
                                    <BreadcrumbLink asChild>
                                        <Link href={item.href}>
                                            {item.label}
                                        </Link>
                                    </BreadcrumbLink>
                                )}
                            </BreadcrumbItem>
                            {!isLast && <BreadcrumbSeparator />}
                        </Fragment>
                    );
                })}
            </BreadcrumbList>
        </Breadcrumb>
    );
}

export default PageBreadcrumb;
