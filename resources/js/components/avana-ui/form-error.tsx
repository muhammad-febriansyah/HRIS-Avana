import { CircleAlert } from 'lucide-react';
import { cn } from '@/lib/utils';

interface FormErrorProps {
    message?: string;
    className?: string;
}

/**
 * Inline validation error message. Renders nothing when there is no message.
 */
export function FormError({ message, className }: FormErrorProps) {
    if (!message) {
        return null;
    }

    return (
        <p
            className={cn(
                'mt-1.5 flex items-center gap-1 text-xs text-red-600',
                className,
            )}
        >
            <CircleAlert className="size-3.5 flex-none" />
            <span>{message}</span>
        </p>
    );
}

export default FormError;
