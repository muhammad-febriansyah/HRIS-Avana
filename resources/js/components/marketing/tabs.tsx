import { useRef } from 'react';
import { cn } from '@/lib/utils';

export type TabItem = { id: string; label: string };

/**
 * Accessible tab list: roving focus, arrow/Home/End keys, and `aria-controls`
 * pointing at the matching panel. Used by the talent, role and product-tour
 * sections so all three behave identically.
 */
export function TabList({
    items,
    active,
    onChange,
    idPrefix,
    orientation = 'horizontal',
    className,
}: {
    items: TabItem[];
    active: string;
    onChange: (id: string) => void;
    idPrefix: string;
    orientation?: 'horizontal' | 'vertical';
    className?: string;
}) {
    const refs = useRef<Record<string, HTMLButtonElement | null>>({});

    const focusTab = (id: string) => {
        onChange(id);
        refs.current[id]?.focus();
    };

    const handleKey = (event: React.KeyboardEvent, index: number) => {
        const next = {
            ArrowRight: index + 1,
            ArrowDown: index + 1,
            ArrowLeft: index - 1,
            ArrowUp: index - 1,
            Home: 0,
            End: items.length - 1,
        }[event.key];

        if (next === undefined) {
            return;
        }

        event.preventDefault();
        focusTab(items[(next + items.length) % items.length].id);
    };

    return (
        <div
            role="tablist"
            aria-orientation={orientation}
            className={cn(
                'flex gap-2',
                orientation === 'vertical'
                    ? 'flex-col'
                    : 'flex-wrap justify-center',
                className,
            )}
        >
            {items.map((item, index) => {
                const selected = item.id === active;

                return (
                    <button
                        key={item.id}
                        ref={(el) => {
                            refs.current[item.id] = el;
                        }}
                        type="button"
                        role="tab"
                        id={`${idPrefix}-tab-${item.id}`}
                        aria-selected={selected}
                        aria-controls={`${idPrefix}-panel-${item.id}`}
                        tabIndex={selected ? 0 : -1}
                        onClick={() => onChange(item.id)}
                        onKeyDown={(event) => handleKey(event, index)}
                        className={cn(
                            'cursor-pointer rounded-full px-4 py-2.5 text-[14px] font-medium transition-colors duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F54C9]',
                            orientation === 'vertical' &&
                                'rounded-xl px-4 py-3 text-left',
                            selected
                                ? 'bg-[#2F54C9] text-white shadow-[0_6px_16px_-6px_rgba(47,84,201,0.5)]'
                                : 'bg-white text-[#3B455C] ring-1 ring-[#E4EAF5] ring-inset hover:border-transparent hover:text-[#2F54C9] hover:shadow-soft',
                        )}
                    >
                        {item.label}
                    </button>
                );
            })}
        </div>
    );
}

export function TabPanel({
    id,
    idPrefix,
    active,
    children,
    className,
}: {
    id: string;
    idPrefix: string;
    active: string;
    children: React.ReactNode;
    className?: string;
}) {
    if (id !== active) {
        return null;
    }

    return (
        <div
            role="tabpanel"
            id={`${idPrefix}-panel-${id}`}
            aria-labelledby={`${idPrefix}-tab-${id}`}
            tabIndex={0}
            className={cn(
                'focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#2F54C9]',
                className,
            )}
        >
            {children}
        </div>
    );
}
