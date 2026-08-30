import { useState } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import type { ApprovalCounts, FilterKey } from './types';
import { typeMeta } from './types';

interface StatCardsProps {
    counts: ApprovalCounts;
    filter: FilterKey;
    onFilter: (key: FilterKey) => void;
}

interface Stat {
    key: FilterKey;
    label: string;
    value: number;
    icon: string;
    color: string;
}

/**
 * The summary cards across the top of the approval center.
 *
 * They double as the type filter: the numbers and the filter pills used to be
 * two controls saying the same thing, and an approver who clicked a card
 * expecting the list to narrow got nothing. One card, one meaning.
 */
export function StatCards({ counts, filter, onFilter }: StatCardsProps) {
    const [hovered, setHovered] = useState<FilterKey | null>(null);

    const total: Stat = {
        key: 'all',
        label: 'Total Menunggu',
        value: counts.total,
        icon: 'inbox',
        color: C.primary,
    };

    const perType: Stat[] = (
        [
            ['leave', 'Cuti'],
            ['lembur', 'Lembur'],
            ['izin', 'Izin'],
            ['wfh', 'WFH'],
            ['koreksi', 'Koreksi'],
            ['klaim', 'Klaim'],
            ['dinas', 'Dinas'],
            ['data', 'Perubahan Data'],
            ['timesheet', 'Timesheet'],
        ] as const
    ).map(([key, label]) => ({
        key,
        label,
        value: counts[key],
        icon: typeMeta[key].icon,
        color: typeMeta[key].color,
    }));

    const renderCard = (stat: Stat, hero: boolean) => {
        const active = filter === stat.key;
        const empty = stat.value === 0;
        const isHovered = hovered === stat.key;

        return (
            <button
                key={stat.key}
                type="button"
                aria-pressed={active}
                aria-label={`Saring ${stat.label}, ${stat.value} pengajuan`}
                onClick={() => onFilter(stat.key)}
                onMouseEnter={() => setHovered(stat.key)}
                onMouseLeave={() => setHovered(null)}
                style={{
                    ...card,
                    display: 'flex',
                    alignItems: 'center',
                    gap: hero ? 14 : 11,
                    padding: hero ? '18px 20px' : '14px 16px',
                    textAlign: 'left',
                    cursor: 'pointer',
                    minHeight: hero ? 84 : 68,
                    // The selected card carries the accent; hovering only lifts
                    // the border, so the active state stays unambiguous.
                    borderColor: active ? stat.color : C.border,
                    boxShadow: active
                        ? `0 0 0 3px ${stat.color}1f`
                        : isHovered
                          ? '0 2px 8px rgba(15,23,42,.08)'
                          : card.boxShadow,
                    transition: 'box-shadow .15s, border-color .15s',
                }}
            >
                <div
                    style={{
                        width: hero ? 42 : 34,
                        height: hero ? 42 : 34,
                        borderRadius: 10,
                        flex: 'none',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background:
                            stat.color + (empty && !active ? '12' : '1f'),
                    }}
                >
                    <AIcon
                        name={stat.icon}
                        size={hero ? 20 : 17}
                        color={empty && !active ? C.faint : stat.color}
                    />
                </div>

                <div style={{ minWidth: 0 }}>
                    <div
                        style={{
                            fontSize: hero ? 28 : 20,
                            fontWeight: 700,
                            lineHeight: 1.05,
                            fontVariantNumeric: 'tabular-nums',
                            // A zero is information, not a headline: it steps
                            // back so the queues that need attention stand out.
                            color: empty ? C.faint : C.navy,
                        }}
                    >
                        {stat.value}
                    </div>
                    <div
                        style={{
                            fontSize: hero ? 13 : 12,
                            color: active ? stat.color : C.muted,
                            fontWeight: active ? 600 : 500,
                            marginTop: 4,
                            whiteSpace: 'nowrap',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                        }}
                    >
                        {stat.label}
                    </div>
                </div>
            </button>
        );
    };

    return (
        <div style={{ marginBottom: 20 }}>
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns:
                        'repeat(auto-fit,minmax(clamp(140px,14vw,190px),1fr))',
                    gap: 12,
                    alignItems: 'stretch',
                }}
            >
                {renderCard(total, true)}
                {perType.map((stat) => renderCard(stat, false))}
            </div>

            <div style={{ fontSize: 12, color: C.faint, marginTop: 9 }}>
                Klik kartu untuk menyaring daftar di bawah.
            </div>
        </div>
    );
}
