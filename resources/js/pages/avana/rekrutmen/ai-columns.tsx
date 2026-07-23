import type { Column, ColumnDef, RowData } from '@tanstack/react-table';
import type { CSSProperties } from 'react';
import { AIcon, C } from '@/lib/avana';

declare module '@tanstack/react-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData extends RowData, TValue> {
        /** Human label shown in the column show/hide menu. */
        label?: string;
    }
}

export interface Rec {
    id: number;
    name: string;
    job_title: string | null;
    stage: string | null;
    confidence: number | null;
    recommendation: string | null;
    reasoning: string | null;
}

export interface Tier {
    label: string;
    color: string;
    bg: string;
}

export const STAGE_LABELS: Record<string, string> = {
    applied: 'Melamar',
    screening: 'Screening',
    shortlisted: 'Shortlist',
    interview: 'Wawancara',
    offer: 'Penawaran',
    hired: 'Diterima',
    rejected: 'Ditolak',
};

/** Classify a match score into a colour-coded recommendation tier. */
export function tierOf(confidence: number | null): Tier {
    const score = confidence ?? 0;

    if (score >= 80) {
        return {
            label: 'Rekomendasi Kuat',
            color: C.green,
            bg: 'rgba(22,163,74,.1)',
        };
    }

    if (score >= 60) {
        return {
            label: 'Layak Dipertimbangkan',
            color: C.amber,
            bg: 'rgba(217,119,6,.1)',
        };
    }

    return { label: 'Kurang Sesuai', color: C.red, bg: 'rgba(220,38,38,.1)' };
}

export function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

const headerStyle: CSSProperties = {
    fontSize: 11.5,
    fontWeight: 600,
    letterSpacing: '.03em',
    textTransform: 'uppercase',
    color: C.muted,
};

const pill: CSSProperties = {
    display: 'inline-block',
    padding: '3px 10px',
    borderRadius: 100,
    fontSize: 11.5,
    fontWeight: 600,
};

/** A sortable column header rendered in the Avana palette. */
function SortHeader<T>({
    column,
    label,
}: {
    column: Column<T, unknown>;
    label: string;
}) {
    const sorted = column.getIsSorted();

    return (
        <button
            type="button"
            onClick={() => column.toggleSorting(sorted === 'asc')}
            style={{
                ...headerStyle,
                display: 'inline-flex',
                alignItems: 'center',
                gap: 5,
                background: 'none',
                border: 'none',
                cursor: 'pointer',
                padding: 0,
            }}
        >
            {label}
            <AIcon
                name={
                    sorted === 'asc'
                        ? 'arrow-up'
                        : sorted === 'desc'
                          ? 'arrow-down'
                          : 'chevrons-up-down'
                }
                size={13}
                color={sorted ? C.primary : C.faint}
            />
        </button>
    );
}

export function makeAiColumns(): ColumnDef<Rec>[] {
    return [
        {
            id: 'rank',
            enableSorting: false,
            enableHiding: false,
            meta: { label: '#' },
            header: () => <span style={headerStyle}>#</span>,
            cell: ({ row }) => (
                <span style={{ fontSize: 13, fontWeight: 700, color: C.faint }}>
                    {row.index + 1}
                </span>
            ),
        },
        {
            accessorKey: 'name',
            meta: { label: 'Kandidat' },
            header: ({ column }) => (
                <SortHeader column={column} label="Kandidat" />
            ),
            cell: ({ row }) => {
                const r = row.original;
                const tier = tierOf(r.confidence);

                return (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 11,
                            minWidth: 0,
                        }}
                    >
                        <div
                            style={{
                                width: 36,
                                height: 36,
                                borderRadius: 9,
                                flex: 'none',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                background: tier.bg,
                                color: tier.color,
                                fontSize: 12.5,
                                fontWeight: 700,
                            }}
                        >
                            {initials(r.name)}
                        </div>
                        <span
                            style={{
                                fontSize: 13.5,
                                fontWeight: 600,
                                color: C.navy,
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {r.name}
                        </span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'job_title',
            meta: { label: 'Posisi' },
            header: ({ column }) => (
                <SortHeader column={column} label="Posisi" />
            ),
            cell: ({ row }) => (
                <span
                    style={{
                        fontSize: 13,
                        color: C.muted,
                        whiteSpace: 'nowrap',
                    }}
                >
                    {row.original.job_title ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'stage',
            meta: { label: 'Tahap' },
            header: ({ column }) => (
                <SortHeader column={column} label="Tahap" />
            ),
            cell: ({ row }) => {
                const stage = row.original.stage;

                return stage ? (
                    <span
                        style={{
                            ...pill,
                            color: C.muted,
                            background: C.surface,
                            border: `1px solid ${C.line}`,
                        }}
                    >
                        {STAGE_LABELS[stage] ?? stage}
                    </span>
                ) : (
                    <span style={{ color: C.faint }}>—</span>
                );
            },
        },
        {
            id: 'rekomendasi',
            enableSorting: false,
            meta: { label: 'Rekomendasi & Alasan' },
            header: () => <span style={headerStyle}>Rekomendasi & Alasan</span>,
            cell: ({ row }) => {
                const tier = tierOf(row.original.confidence);
                const reasoning = row.original.reasoning;

                return (
                    <div style={{ maxWidth: 340, minWidth: 220 }}>
                        <span
                            style={{
                                ...pill,
                                color: tier.color,
                                background: tier.bg,
                            }}
                        >
                            {tier.label}
                        </span>
                        {reasoning ? (
                            <div
                                title={reasoning}
                                style={{
                                    marginTop: 6,
                                    fontSize: 12,
                                    lineHeight: 1.45,
                                    color: C.muted,
                                    display: '-webkit-box',
                                    WebkitLineClamp: 2,
                                    WebkitBoxOrient: 'vertical',
                                    overflow: 'hidden',
                                }}
                            >
                                {reasoning}
                            </div>
                        ) : (
                            <div
                                style={{
                                    marginTop: 6,
                                    fontSize: 12,
                                    color: C.faint,
                                }}
                            >
                                Belum dianalisa AI
                            </div>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'confidence',
            meta: { label: 'Skor' },
            header: ({ column }) => (
                <SortHeader column={column} label="Skor Kecocokan" />
            ),
            cell: ({ row }) => {
                const score = row.original.confidence ?? 0;
                const tier = tierOf(row.original.confidence);

                return (
                    <div style={{ minWidth: 120 }}>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'baseline',
                                gap: 1,
                            }}
                        >
                            <span
                                style={{
                                    fontSize: 16,
                                    fontWeight: 700,
                                    color: tier.color,
                                }}
                            >
                                {score}
                            </span>
                            <span
                                style={{
                                    fontSize: 11,
                                    fontWeight: 600,
                                    color: tier.color,
                                }}
                            >
                                %
                            </span>
                        </div>
                        <div
                            style={{
                                height: 5,
                                borderRadius: 5,
                                background: C.line,
                                marginTop: 4,
                                overflow: 'hidden',
                                width: 110,
                            }}
                        >
                            <div
                                style={{
                                    width: `${score}%`,
                                    height: '100%',
                                    background: tier.color,
                                    borderRadius: 5,
                                }}
                            />
                        </div>
                    </div>
                );
            },
        },
    ];
}
