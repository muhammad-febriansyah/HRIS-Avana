import { C } from '@/lib/avana';
import type { ApprovalCounts, FilterKey } from './types';
import { filters } from './types';

interface FilterChipsProps {
    filter: FilterKey;
    counts: ApprovalCounts;
    onFilter: (key: FilterKey) => void;
}

/** Type filter pills with per-type pending counts. */
export function FilterChips({ filter, counts, onFilter }: FilterChipsProps) {
    return (
        <div
            style={{
                display: 'inline-flex',
                flexWrap: 'wrap',
                gap: 4,
                background: C.surface,
                padding: 4,
                borderRadius: 10,
                marginBottom: 18,
            }}
        >
            {filters.map((item) => {
                const active = filter === item.key;
                const badgeCount =
                    item.key === 'all' ? counts.total : counts[item.key];

                return (
                    <button
                        key={item.key}
                        onClick={() => onFilter(item.key)}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 7,
                            height: 34,
                            padding: '0 14px',
                            border: 'none',
                            borderRadius: 8,
                            fontSize: 13,
                            fontWeight: 600,
                            color: active ? '#fff' : C.muted,
                            background: active ? C.primary : 'transparent',
                            cursor: 'pointer',
                            transition: '.15s',
                        }}
                    >
                        {item.label}
                        <span
                            style={{
                                fontSize: 11,
                                fontWeight: 700,
                                padding: '1px 7px',
                                borderRadius: 100,
                                color: active ? '#fff' : C.faint,
                                background: active
                                    ? 'rgba(255,255,255,.22)'
                                    : C.line,
                            }}
                        >
                            {badgeCount}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
