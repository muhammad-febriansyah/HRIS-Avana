import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import RecruitmentController from '@/actions/App/Http/Controllers/Avana/RecruitmentController';
import { AIcon, C } from '@/lib/avana';
import { RecruitmentHeader } from './shell';

interface Card {
    id: number;
    name: string;
    email: string | null;
    stage: string;
    applied_date: string | null;
    job_title: string | null;
    position: string | null;
    source?: string | null;
}

interface Stage {
    value: string;
    label: string;
}

interface PipelineProps {
    pipeline: Record<string, Card[]>;
    stages: Stage[];
    total: number;
}

/** Per-stage accent colour. */
const STAGE_COLOR: Record<string, string> = {
    applied: '#2563EB',
    screening: '#D97706',
    shortlisted: '#7C3AED',
    interview: '#4F46E5',
};

/** Deterministic avatar palette so a candidate keeps one colour. */
const AVATAR = [
    '#2563EB', '#7C3AED', '#DB2777', '#EA580C',
    '#0891B2', '#16A34A', '#4F46E5', '#D97706',
];

function initials(name: string): string {
    const parts = name.trim().split(/\s+/);
    return (
        (parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? parts[0]?.[1] ?? '')
    ).toUpperCase();
}

function avatarColor(name: string): string {
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = (hash * 31 + name.charCodeAt(i)) >>> 0;
    }
    return AVATAR[hash % AVATAR.length];
}

export default function RecruitmentPipeline({
    pipeline,
    stages,
    total,
}: PipelineProps) {
    const [board, setBoard] = useState(pipeline);
    const [dragId, setDragId] = useState<number | null>(null);
    const [overStage, setOverStage] = useState<string | null>(null);

    const moveTo = (targetStage: string) => {
        setOverStage(null);
        if (dragId === null) {
            return;
        }

        let moved: Card | null = null;
        const next: Record<string, Card[]> = {};
        for (const [stage, cards] of Object.entries(board)) {
            next[stage] = cards.filter((c) => {
                if (c.id === dragId) {
                    moved = c;
                    return false;
                }
                return true;
            });
        }
        if (moved === null || (moved as Card).stage === targetStage) {
            setDragId(null);
            return;
        }
        next[targetStage] = [
            { ...(moved as Card), stage: targetStage },
            ...(next[targetStage] ?? []),
        ];
        setBoard(next);
        setDragId(null);

        router.post(
            RecruitmentController.moveStage(dragId).url,
            { stage: targetStage },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Tahap kandidat diperbarui'),
                onError: () => {
                    setBoard(pipeline);
                    toast.error('Gagal memindahkan kandidat');
                },
            },
        );
    };

    return (
        <>
            <Head title="Pipeline" />
            <style>{`
                .pl-card { transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease; }
                .pl-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px -12px rgba(15,23,42,.28); }
                .pl-card:active { cursor: grabbing; }
                .pl-col { transition: background .15s ease, border-color .15s ease; }
                .pl-grip { opacity: 0; transition: opacity .15s ease; }
                .pl-card:hover .pl-grip { opacity: 1; }
            `}</style>
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="Pipeline"
                    subtitle="Tarik & lepas kandidat untuk memindahkan antar tahap."
                    action={
                        <span
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 7,
                                fontSize: 13,
                                fontWeight: 600,
                                color: C.navy,
                                padding: '8px 14px',
                                borderRadius: 100,
                                background: '#fff',
                                border: `1px solid ${C.line}`,
                            }}
                        >
                            <AIcon name="users" size={15} color={C.primary} />
                            {total} Kandidat
                        </span>
                    }
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: `repeat(${stages.length}, minmax(256px, 1fr))`,
                        gap: 16,
                        overflowX: 'auto',
                        paddingBottom: 8,
                    }}
                >
                    {stages.map((stage) => {
                        const color = STAGE_COLOR[stage.value] ?? C.faint;
                        const cards = board[stage.value] ?? [];
                        const isOver = overStage === stage.value;

                        return (
                            <div
                                key={stage.value}
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    if (overStage !== stage.value) {
                                        setOverStage(stage.value);
                                    }
                                }}
                                onDragLeave={(e) => {
                                    if (
                                        !e.currentTarget.contains(
                                            e.relatedTarget as Node,
                                        )
                                    ) {
                                        setOverStage((s) =>
                                            s === stage.value ? null : s,
                                        );
                                    }
                                }}
                                onDrop={() => moveTo(stage.value)}
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 12,
                                }}
                            >
                                {/* Column header */}
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        padding: '10px 14px',
                                        borderRadius: 12,
                                        background: '#fff',
                                        border: `1px solid ${C.line}`,
                                        borderTop: `3px solid ${color}`,
                                    }}
                                >
                                    <span
                                        style={{
                                            fontSize: 14,
                                            fontWeight: 700,
                                            color: C.navy,
                                        }}
                                    >
                                        {stage.label}
                                    </span>
                                    <span
                                        style={{
                                            fontSize: 12,
                                            fontWeight: 700,
                                            minWidth: 24,
                                            textAlign: 'center',
                                            padding: '2px 9px',
                                            borderRadius: 100,
                                            color,
                                            background: color + '18',
                                        }}
                                    >
                                        {cards.length}
                                    </span>
                                </div>

                                {/* Drop zone */}
                                <div
                                    className="pl-col"
                                    style={{
                                        minHeight: 440,
                                        borderRadius: 14,
                                        border: `1.5px ${isOver ? 'solid' : 'dashed'} ${isOver ? color : C.line}`,
                                        background: isOver
                                            ? color + '0c'
                                            : '#F8FAFC',
                                        padding: 10,
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 10,
                                    }}
                                >
                                    {cards.length === 0 && !isOver && (
                                        <div
                                            style={{
                                                flex: 1,
                                                display: 'flex',
                                                flexDirection: 'column',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                gap: 6,
                                                color: C.faint,
                                                fontSize: 12.5,
                                            }}
                                        >
                                            <AIcon
                                                name="inbox"
                                                size={22}
                                                color={C.faint}
                                            />
                                            Kosong
                                        </div>
                                    )}

                                    {cards.map((c) => {
                                        const ac = avatarColor(c.name);
                                        return (
                                            <div
                                                key={c.id}
                                                className="pl-card"
                                                draggable
                                                onDragStart={() =>
                                                    setDragId(c.id)
                                                }
                                                onDragEnd={() => {
                                                    setDragId(null);
                                                    setOverStage(null);
                                                }}
                                                style={{
                                                    background: '#fff',
                                                    borderRadius: 12,
                                                    border: `1px solid ${C.line}`,
                                                    padding: 14,
                                                    cursor: 'grab',
                                                    opacity:
                                                        dragId === c.id
                                                            ? 0.4
                                                            : 1,
                                                    boxShadow:
                                                        '0 1px 2px rgba(15,23,42,.04)',
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 10,
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            width: 34,
                                                            height: 34,
                                                            borderRadius: '50%',
                                                            flex: 'none',
                                                            display: 'flex',
                                                            alignItems:
                                                                'center',
                                                            justifyContent:
                                                                'center',
                                                            background: ac + '1a',
                                                            color: ac,
                                                            fontSize: 12.5,
                                                            fontWeight: 700,
                                                        }}
                                                    >
                                                        {initials(c.name)}
                                                    </div>
                                                    <div
                                                        style={{
                                                            flex: 1,
                                                            minWidth: 0,
                                                        }}
                                                    >
                                                        <div
                                                            style={{
                                                                fontSize: 14,
                                                                fontWeight: 600,
                                                                color: C.navy,
                                                                overflow:
                                                                    'hidden',
                                                                textOverflow:
                                                                    'ellipsis',
                                                                whiteSpace:
                                                                    'nowrap',
                                                            }}
                                                        >
                                                            {c.name}
                                                        </div>
                                                        {c.job_title && (
                                                            <div
                                                                style={{
                                                                    fontSize: 11.5,
                                                                    color: C.faint,
                                                                    overflow:
                                                                        'hidden',
                                                                    textOverflow:
                                                                        'ellipsis',
                                                                    whiteSpace:
                                                                        'nowrap',
                                                                }}
                                                            >
                                                                {c.job_title}
                                                            </div>
                                                        )}
                                                    </div>
                                                    <AIcon
                                                        name="grip-vertical"
                                                        size={15}
                                                        color={C.faint}
                                                        style={
                                                            {
                                                                flex: 'none',
                                                            } as React.CSSProperties
                                                        }
                                                    />
                                                </div>

                                                <div
                                                    style={{
                                                        marginTop: 11,
                                                        paddingTop: 11,
                                                        borderTop: `1px solid ${C.line}`,
                                                        display: 'flex',
                                                        flexDirection: 'column',
                                                        gap: 6,
                                                        fontSize: 12,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {c.applied_date && (
                                                        <span
                                                            style={{
                                                                display: 'flex',
                                                                alignItems:
                                                                    'center',
                                                                gap: 6,
                                                            }}
                                                        >
                                                            <AIcon
                                                                name="calendar"
                                                                size={12}
                                                                color={C.faint}
                                                            />
                                                            {c.applied_date}
                                                        </span>
                                                    )}
                                                    {c.email && (
                                                        <span
                                                            style={{
                                                                display: 'flex',
                                                                alignItems:
                                                                    'center',
                                                                gap: 6,
                                                                overflow:
                                                                    'hidden',
                                                                textOverflow:
                                                                    'ellipsis',
                                                                whiteSpace:
                                                                    'nowrap',
                                                            }}
                                                        >
                                                            <AIcon
                                                                name="mail"
                                                                size={12}
                                                                color={C.faint}
                                                            />
                                                            {c.email}
                                                        </span>
                                                    )}
                                                </div>

                                                {c.source && (
                                                    <div style={{ marginTop: 10 }}>
                                                        <span
                                                            style={{
                                                                display:
                                                                    'inline-flex',
                                                                alignItems:
                                                                    'center',
                                                                gap: 5,
                                                                fontSize: 11,
                                                                fontWeight: 600,
                                                                color: C.muted,
                                                                padding:
                                                                    '3px 9px',
                                                                borderRadius: 100,
                                                                background:
                                                                    C.surface,
                                                            }}
                                                        >
                                                            <AIcon
                                                                name="tag"
                                                                size={11}
                                                                color={C.faint}
                                                            />
                                                            {c.source}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </>
    );
}
