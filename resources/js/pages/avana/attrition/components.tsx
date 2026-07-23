import { AIcon, C, card } from '@/lib/avana';

export type Category = 'low' | 'medium' | 'high';

/** Risk band → label + colour + tinted background, shared by list and detail. */
export const RISK: Record<Category, { label: string; color: string; bg: string }> = {
    low: { label: 'Rendah', color: C.green, bg: 'rgba(22,163,74,.1)' },
    medium: { label: 'Sedang', color: C.amber, bg: 'rgba(217,119,6,.1)' },
    high: { label: 'Tinggi', color: C.red, bg: 'rgba(220,38,38,.1)' },
};

export function CategoryChip({ category }: { category: Category }) {
    const r = RISK[category];

    return (
        <span
            style={{
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color: r.color,
                background: r.bg,
            }}
        >
            {r.label}
        </span>
    );
}

export function ScoreBar({
    score,
    category,
}: {
    score: number;
    category: Category;
}) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <div
                style={{
                    flex: 1,
                    height: 8,
                    borderRadius: 6,
                    background: C.line,
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        width: `${score}%`,
                        height: '100%',
                        borderRadius: 6,
                        background: RISK[category].color,
                        transition: 'width .3s',
                    }}
                />
            </div>
            <span
                style={{
                    fontSize: 13,
                    fontWeight: 700,
                    color: C.navy,
                    fontVariantNumeric: 'tabular-nums',
                    minWidth: 26,
                    textAlign: 'right',
                }}
            >
                {score}
            </span>
        </div>
    );
}

export function KpiCard({
    label,
    value,
    icon,
    color,
}: {
    label: string;
    value: string | number;
    icon: string;
    color: string;
}) {
    return (
        <div
            style={{
                ...card,
                padding: '18px 20px',
                display: 'flex',
                alignItems: 'center',
                gap: 14,
            }}
        >
            <div
                style={{
                    width: 44,
                    height: 44,
                    borderRadius: 12,
                    flex: 'none',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: color + '1a',
                    color,
                }}
            >
                <AIcon name={icon} size={20} color={color} />
            </div>
            <div>
                <div style={{ fontSize: 12.5, color: C.muted }}>{label}</div>
                <div
                    style={{
                        fontSize: 22,
                        fontWeight: 700,
                        color: C.navy,
                        marginTop: 3,
                        fontVariantNumeric: 'tabular-nums',
                    }}
                >
                    {value}
                </div>
            </div>
        </div>
    );
}
