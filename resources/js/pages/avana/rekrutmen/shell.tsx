import { Link } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import { AIcon, C, card } from '@/lib/avana';

/* ============================================================
 * Shared presentational shell for the multi-page recruitment
 * (ATS) module: page header, KPI tiles, section cards, and the
 * empty-state placeholder. Reuses the AvanaHR design tokens.
 * ============================================================ */

/** Page header with breadcrumb, title, subtitle, and optional action slot. */
export function RecruitmentHeader({
    title,
    subtitle,
    action,
}: {
    title: string;
    subtitle?: string;
    action?: ReactNode;
}) {
    return (
        <div
            style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'flex-end',
                flexWrap: 'wrap',
                gap: 12,
                marginBottom: 22,
            }}
        >
            <div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 7,
                    }}
                >
                    <Link
                        href="/avana/rekrutmen"
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        Rekrutmen
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{title}</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: 0,
                        letterSpacing: '-.01em',
                    }}
                >
                    {title}
                </h1>
                {subtitle !== undefined && (
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        {subtitle}
                    </div>
                )}
            </div>
            {action}
        </div>
    );
}

/** A single KPI tile with an icon accent. */
export function Kpi({
    label,
    value,
    hint,
    icon,
    color = C.primary,
}: {
    label: string;
    value: string | number;
    hint?: string;
    icon: string;
    color?: string;
}) {
    return (
        <div style={{ ...card, padding: '18px 20px' }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'flex-start',
                }}
            >
                <span
                    style={{
                        fontSize: 11.5,
                        fontWeight: 600,
                        letterSpacing: '.03em',
                        textTransform: 'uppercase',
                        color: C.muted,
                    }}
                >
                    {label}
                </span>
                <div
                    style={{
                        width: 34,
                        height: 34,
                        borderRadius: 9,
                        flex: 'none',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background: color + '1a',
                    }}
                >
                    <AIcon name={icon} size={17} color={color} />
                </div>
            </div>
            <div
                style={{
                    fontSize: 27,
                    fontWeight: 700,
                    color: C.navy,
                    marginTop: 8,
                    fontVariantNumeric: 'tabular-nums',
                }}
            >
                {value}
            </div>
            {hint !== undefined && (
                <div style={{ fontSize: 12, color: C.faint, marginTop: 2 }}>
                    {hint}
                </div>
            )}
        </div>
    );
}

/** Responsive KPI grid. */
export function KpiRow({ children }: { children: ReactNode }) {
    return (
        <div
            className="avn-kpi"
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(4,1fr)',
                gap: 18,
                marginBottom: 18,
            }}
        >
            {children}
        </div>
    );
}

/** A titled section card. */
export function Section({
    title,
    icon,
    action,
    children,
}: {
    title: string;
    icon?: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div style={{ ...card, padding: '20px 22px' }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    marginBottom: 16,
                }}
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
                    {icon !== undefined && (
                        <AIcon name={icon} size={18} color={C.primary} />
                    )}
                    <div
                        style={{ fontSize: 15, fontWeight: 600, color: C.navy }}
                    >
                        {title}
                    </div>
                </div>
                {action}
            </div>
            {children}
        </div>
    );
}

/** Centered empty-state placeholder. */
export function Empty({
    icon = 'inbox',
    title,
    hint,
}: {
    icon?: string;
    title: string;
    hint?: string;
}) {
    return (
        <div
            style={{
                padding: '44px 0',
                textAlign: 'center',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                gap: 8,
            }}
        >
            <AIcon name={icon} size={30} color={C.faint} />
            <div style={{ fontSize: 15, fontWeight: 600, color: C.text }}>
                {title}
            </div>
            {hint !== undefined && (
                <div style={{ fontSize: 13, color: C.faint, maxWidth: 360 }}>
                    {hint}
                </div>
            )}
        </div>
    );
}

/**
 * Funnel chart: centered bands whose width scales with the value, so the ordered
 * pipeline stages taper into a funnel silhouette. Stage label + value sit inside
 * each band; zero-value stages render as a slim neutral rail.
 */
export function Funnel({
    data,
    colors,
}: {
    data: { label: string; value: number }[];
    colors: string[];
}) {
    if (data.length === 0) {
        return <Empty icon="filter" title="Belum ada data" />;
    }

    const max = Math.max(...data.map((d) => d.value), 1);

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {data.map((row, i) => {
                const pct = (row.value / max) * 100;
                const filled = row.value > 0;
                const color = colors[i] ?? colors[colors.length - 1];

                return (
                    <div
                        key={row.label}
                        title={`${row.label}: ${row.value}`}
                        style={{
                            display: 'flex',
                            justifyContent: 'center',
                            height: 40,
                        }}
                    >
                        <div
                            style={{
                                width: `max(150px, ${pct}%)`,
                                height: '100%',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                padding: '0 16px',
                                borderRadius: 9,
                                background: filled ? color : C.line,
                                color: filled ? '#fff' : C.muted,
                                transition: 'width .3s ease',
                            }}
                        >
                            <span style={{ fontSize: 13, fontWeight: 600 }}>
                                {row.label}
                            </span>
                            <span
                                style={{
                                    fontSize: 15,
                                    fontWeight: 700,
                                    fontVariantNumeric: 'tabular-nums',
                                }}
                            >
                                {row.value}
                            </span>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

/** Shared table header-cell style. */
export const th: CSSProperties = {
    textAlign: 'left',
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    padding: '0 14px 12px',
    borderBottom: `1px solid ${C.line}`,
};

/** Shared table body-cell style. */
export const td: CSSProperties = {
    fontSize: 13.5,
    color: C.text,
    padding: '14px',
    borderBottom: `1px solid ${C.line}`,
};
