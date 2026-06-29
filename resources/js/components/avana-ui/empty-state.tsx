import { Inbox } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface EmptyStateProps {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
}

/**
 * Centered empty state inside a dashed card. Optionally renders an action
 * (typically a create button) below the description.
 */
export function EmptyState({
    icon: Icon = Inbox,
    title,
    description,
    action,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-6 py-12 text-center',
                className,
            )}
        >
            <div className="mb-4 flex size-12 items-center justify-center rounded-full bg-white text-slate-400 ring-1 ring-slate-200/70">
                <Icon className="size-6" />
            </div>
            <h3 className="text-sm font-semibold text-[#0E1A3A]">{title}</h3>
            {description && (
                <p className="mt-1 max-w-sm text-sm text-slate-500">
                    {description}
                </p>
            )}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}

export default EmptyState;
