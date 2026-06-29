import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface ActionButtonProps extends Omit<
    ComponentProps<typeof Button>,
    'asChild'
> {
    icon?: LucideIcon;
    href?: string;
}

/**
 * Action button with a leading icon. When `href` is provided it renders an
 * Inertia `<Link>` styled identically to the shadcn button; otherwise a regular
 * button. Defaults to the AvanaHR h-9 / gap-2 sizing.
 */
export function ActionButton({
    icon: Icon,
    children,
    variant,
    size,
    href,
    className,
    ...props
}: ActionButtonProps) {
    const content = (
        <>
            {Icon && <Icon className="size-4" />}
            {children}
        </>
    );

    if (href) {
        return (
            <Link
                href={href}
                className={cn(
                    buttonVariants({ variant, size }),
                    'gap-2',
                    className,
                )}
            >
                {content}
            </Link>
        );
    }

    return (
        <Button
            variant={variant}
            size={size}
            className={cn('gap-2', className)}
            {...props}
        >
            {content}
        </Button>
    );
}

export default ActionButton;
