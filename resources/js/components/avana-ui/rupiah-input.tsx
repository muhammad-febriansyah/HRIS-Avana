import * as React from 'react';
import { cn } from '@/lib/utils';

interface RupiahInputProps {
    value: number | string | null | undefined;
    onChange: (value: number) => void;
    placeholder?: string;
    error?: boolean;
    name?: string;
    id?: string;
    disabled?: boolean;
    className?: string;
}

/** Inserts dot thousand separators into a string of digits. */
function formatThousands(digits: string): string {
    if (!digits) {
        return '';
    }

    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/**
 * Normalises an incoming value to a digit-only string. A zero value is treated
 * as empty so the placeholder stays visible until the user types.
 */
function toDigits(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const digits = String(value).replace(/\D/g, '');

    return digits === '0' ? '' : digits;
}

/**
 * Controlled Rupiah amount field. Displays a `Rp` prefix and formats the value
 * with dot thousand separators while the user types, but reports the raw
 * numeric value through `onChange`.
 */
export function RupiahInput({
    value,
    onChange,
    placeholder = 'Masukkan nominal',
    error = false,
    name,
    id,
    disabled,
    className,
}: RupiahInputProps) {
    const display = formatThousands(toDigits(value));

    const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const digits = event.target.value.replace(/\D/g, '');

        onChange(digits === '' ? 0 : Number(digits));
    };

    return (
        <div className={cn('relative flex w-full items-center', className)}>
            <span className="pointer-events-none absolute left-3 text-sm font-medium text-slate-500">
                Rp
            </span>
            <input
                type="text"
                inputMode="numeric"
                name={name}
                id={id}
                disabled={disabled}
                value={display}
                onChange={handleChange}
                placeholder={placeholder}
                aria-invalid={error || undefined}
                className={cn(
                    'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent py-1 pr-3 pl-9 text-sm shadow-xs transition-[color,box-shadow] outline-none',
                    'placeholder:text-muted-foreground',
                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                    error &&
                        'border-destructive ring-destructive/20 focus-visible:ring-destructive/20',
                )}
            />
        </div>
    );
}

export default RupiahInput;
