import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface PageHeaderProps {
    title: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
}

/**
 * AvanaHR page header: navy 24px title, muted description and a
 * right-aligned slot for action buttons.
 */
export function PageHeader({
    title,
    description,
    actions,
    className,
}: PageHeaderProps) {
    return (
        <div
            className={cn(
                'mb-6 flex flex-wrap items-start justify-between gap-4',
                className,
            )}
        >
            <div className="min-w-0">
                <h1 className="text-2xl font-semibold tracking-tight text-[#0E1A3A]">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1 text-sm text-slate-500">{description}</p>
                )}
            </div>
            {actions && (
                <div className="flex flex-shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}

export default PageHeader;
