import type { CSSProperties, ReactNode } from 'react';
import { AIcon, C } from '@/lib/avana';
import { badgeOf, initials, STAGE_LABELS } from './ai-columns';
import type { Rec } from './ai-columns';

function InfoItem({
    icon,
    label,
    value,
}: {
    icon: string;
    label: string;
    value: ReactNode;
}) {
    return (
        <div style={{ display: 'flex', gap: 11, alignItems: 'flex-start' }}>
            <div
                style={{
                    width: 32,
                    height: 32,
                    borderRadius: 8,
                    flex: 'none',
                    background: C.surface,
                    border: `1px solid ${C.line}`,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                }}
            >
                <AIcon name={icon} size={15} color={C.muted} />
            </div>
            <div style={{ minWidth: 0 }}>
                <div
                    style={{ fontSize: 11.5, color: C.faint, fontWeight: 600 }}
                >
                    {label}
                </div>
                <div
                    style={{
                        fontSize: 13.5,
                        color: C.text,
                        fontWeight: 500,
                        marginTop: 2,
                        wordBreak: 'break-word',
                    }}
                >
                    {value || (
                        <span style={{ color: C.faint, fontWeight: 400 }}>
                            —
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * A wide, detailed candidate card shown in a modal: the AI match score with its
 * reasoning up top, then the candidate's recruitment info in a grid.
 */
export function CandidateDetailModal({
    rec,
    onClose,
}: {
    rec: Rec;
    onClose: () => void;
}) {
    const scored = rec.confidence !== null;
    const tier = badgeOf(rec.confidence);
    const score = rec.confidence ?? 0;

    const sectionLabel: CSSProperties = {
        fontSize: 11.5,
        fontWeight: 700,
        letterSpacing: '.05em',
        textTransform: 'uppercase',
        color: C.faint,
        marginBottom: 12,
    };

    return (
        <div
            onClick={onClose}
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 70,
                background: 'rgba(9,11,20,.55)',
                display: 'flex',
                alignItems: 'flex-start',
                justifyContent: 'center',
                padding: '48px 20px',
                overflowY: 'auto',
            }}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                style={{
                    width: '100%',
                    maxWidth: 720,
                    background: '#fff',
                    borderRadius: 16,
                    boxShadow: '0 24px 60px rgba(15,23,42,.25)',
                    overflow: 'hidden',
                }}
            >
                {/* Header */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 14,
                        padding: '22px 26px',
                        borderBottom: `1px solid ${C.line}`,
                    }}
                >
                    <div
                        style={{
                            width: 52,
                            height: 52,
                            borderRadius: 13,
                            flex: 'none',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            background: tier.bg,
                            color: tier.color,
                            fontSize: 17,
                            fontWeight: 700,
                        }}
                    >
                        {initials(rec.name)}
                    </div>
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 700,
                                color: C.navy,
                            }}
                        >
                            {rec.name}
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 2,
                            }}
                        >
                            {rec.position ?? rec.job_title ?? 'Kandidat'}
                            {rec.stage &&
                                ` · ${STAGE_LABELS[rec.stage] ?? rec.stage}`}
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        style={{
                            width: 34,
                            height: 34,
                            borderRadius: 9,
                            border: `1px solid ${C.line}`,
                            background: '#fff',
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                        }}
                    >
                        <AIcon name="x" size={17} color={C.muted} />
                    </button>
                </div>

                <div style={{ padding: 26 }}>
                    {/* AI score hero */}
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 22,
                            padding: '18px 20px',
                            borderRadius: 14,
                            background: scored ? tier.bg : C.surface,
                            border: `1px solid ${scored ? 'transparent' : C.line}`,
                            marginBottom: 22,
                        }}
                    >
                        <div style={{ textAlign: 'center', flex: 'none' }}>
                            {scored ? (
                                <>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'baseline',
                                            justifyContent: 'center',
                                        }}
                                    >
                                        <span
                                            style={{
                                                fontSize: 40,
                                                fontWeight: 800,
                                                color: tier.color,
                                                lineHeight: 1,
                                            }}
                                        >
                                            {score}
                                        </span>
                                        <span
                                            style={{
                                                fontSize: 18,
                                                fontWeight: 700,
                                                color: tier.color,
                                            }}
                                        >
                                            %
                                        </span>
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11,
                                            color: tier.color,
                                            marginTop: 4,
                                            fontWeight: 600,
                                        }}
                                    >
                                        Skor Kecocokan
                                    </div>
                                </>
                            ) : (
                                <div
                                    style={{
                                        fontSize: 28,
                                        fontWeight: 700,
                                        color: C.faint,
                                    }}
                                >
                                    —
                                </div>
                            )}
                        </div>
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <span
                                style={{
                                    display: 'inline-block',
                                    padding: '4px 12px',
                                    borderRadius: 100,
                                    fontSize: 12.5,
                                    fontWeight: 700,
                                    color: tier.color,
                                    background: scored ? '#fff' : tier.bg,
                                }}
                            >
                                {tier.label}
                            </span>
                            {rec.recommendation && (
                                <div
                                    style={{
                                        fontSize: 13.5,
                                        color: C.text,
                                        marginTop: 8,
                                        fontWeight: 500,
                                    }}
                                >
                                    {rec.recommendation}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Reasoning */}
                    <div style={{ marginBottom: 24 }}>
                        <div style={sectionLabel}>Alasan Penilaian AI</div>
                        {rec.reasoning ? (
                            <div
                                style={{
                                    fontSize: 13.5,
                                    lineHeight: 1.6,
                                    color: C.text,
                                    background: C.surface,
                                    border: `1px solid ${C.line}`,
                                    borderRadius: 12,
                                    padding: '14px 16px',
                                }}
                            >
                                {rec.reasoning}
                            </div>
                        ) : (
                            <div
                                style={{
                                    fontSize: 13,
                                    color: C.faint,
                                    background: C.surface,
                                    border: `1px dashed ${C.line}`,
                                    borderRadius: 12,
                                    padding: '14px 16px',
                                }}
                            >
                                Kandidat ini belum dianalisa AI. Jalankan
                                “Analisa dengan AI” untuk mendapat skor &
                                alasan.
                            </div>
                        )}
                    </div>

                    {/* Candidate info */}
                    <div style={sectionLabel}>Data Kandidat</div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fit, minmax(240px, 1fr))',
                            gap: 16,
                        }}
                    >
                        <InfoItem
                            icon="briefcase"
                            label="Lowongan"
                            value={rec.job_title}
                        />
                        <InfoItem
                            icon="user"
                            label="Posisi Dilamar"
                            value={rec.position}
                        />
                        <InfoItem
                            icon="git-branch"
                            label="Tahap"
                            value={
                                rec.stage
                                    ? (STAGE_LABELS[rec.stage] ?? rec.stage)
                                    : null
                            }
                        />
                        <InfoItem
                            icon="megaphone"
                            label="Sumber"
                            value={rec.source}
                        />
                        <InfoItem icon="mail" label="Email" value={rec.email} />
                        <InfoItem
                            icon="phone"
                            label="Telepon"
                            value={rec.phone}
                        />
                        <InfoItem
                            icon="calendar"
                            label="Tanggal Melamar"
                            value={rec.applied_date}
                        />
                        <InfoItem
                            icon="calendar-clock"
                            label="Jadwal Wawancara"
                            value={rec.interview_at}
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}
