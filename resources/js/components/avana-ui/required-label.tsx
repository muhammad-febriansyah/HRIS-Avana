import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';

interface RequiredLabelProps {
    children: ReactNode;
    htmlFor?: string;
    className?: string;
}

/**
 * Form label that appends a red asterisk to mark a field as required.
 */
export function RequiredLabel({
    children,
    htmlFor,
    className,
}: RequiredLabelProps) {
    return (
        <Label htmlFor={htmlFor} className={className}>
            {children} <span className="text-red-500">*</span>
        </Label>
    );
}

export default RequiredLabel;
