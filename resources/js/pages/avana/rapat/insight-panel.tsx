import { useState } from 'react';
import type { CSSProperties } from 'react';
import { AIcon, btnOut, btnProcess, C, hexA } from '@/lib/avana';
import type { InsightRow } from './types';
import { formatDateTime } from './types';

const rowStyle: CSSProperties = {
    border: `1px solid ${C.border}`,
    borderRadius: 10,
    padding: '14px 16px',
};

const bodyText: CSSProperties = {
    fontSize: 14.5,
    color: C.text,
    lineHeight: 1.7,
    // Analysis is prose too — the same reading measure as the summary, so the
    // eye does not have to travel the whole card to find the next line.
    maxWidth: '64ch',
};

const listStyle: CSSProperties = {
    margin: '6px 0 0',
    paddingLeft: 18,
    ...bodyText,
};

const chipStyle: CSSProperties = {
    fontSize: 11,
    fontWeight: 600,
    padding: '2px 8px',
    borderRadius: 999,
};

/** A severity/priority/sentiment word coloured by how bad it is. */
function Chip({ value }: { value: string }) {
    const normalised = value.toLowerCase();
    const tone =
        normalised.includes('tinggi') || normalised.includes('negatif')
            ? C.red
            : normalised.includes('sedang') || normalised.includes('netral')
              ? C.amber
              : C.green;

    return (
        <span
            style={{ ...chipStyle, color: tone, background: hexA(tone, 0.1) }}
        >
            {value}
        </span>
    );
}

function asStringArray(value: unknown): string[] {
    return Array.isArray(value) ? value.map((entry) => String(entry)) : [];
}

function asRecordArray(value: unknown): Record<string, unknown>[] {
    return Array.isArray(value)
        ? value.filter(
              (entry): entry is Record<string, unknown> =>
                  typeof entry === 'object' && entry !== null,
          )
        : [];
}

function text(value: unknown): string {
    return value === null || value === undefined ? '' : String(value);
}

/**
 * Renders one analysis. Each type has its own shape, so each gets its own
 * layout rather than a generic key/value dump — these are meant to be read by a
 * manager, not inspected.
 */
function Payload({ insight }: { insight: InsightRow }) {
    const payload = insight.payload ?? {};

    if (insight.type === 'executive_summary') {
        return (
            <div>
                <div
                    style={{
                        ...bodyText,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 8,
                    }}
                >
                    {text(payload.headline)}
                </div>
                {asStringArray(payload.paragraphs).map((paragraph, index) => (
                    <p key={index} style={{ ...bodyText, margin: '0 0 8px' }}>
                        {paragraph}
                    </p>
                ))}
                {asStringArray(payload.key_points).length > 0 && (
                    <ul style={listStyle}>
                        {asStringArray(payload.key_points).map(
                            (point, index) => (
                                <li key={index}>{point}</li>
                            ),
                        )}
                    </ul>
                )}
            </div>
        );
    }

    if (insight.type === 'decision_analysis') {
        return (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                {asRecordArray(payload.decisions).map((decision, index) => (
                    <div key={index}>
                        <div
                            style={{
                                ...bodyText,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            {text(decision.decision)}
                        </div>
                        <div style={bodyText}>
                            <strong style={{ color: C.muted }}>Alasan:</strong>{' '}
                            {text(decision.rationale)}
                        </div>
                        <div style={bodyText}>
                            <strong style={{ color: C.muted }}>Pemilik:</strong>{' '}
                            {text(decision.owner) || '-'} ·{' '}
                            <strong style={{ color: C.muted }}>Dampak:</strong>{' '}
                            {text(decision.impact)}
                        </div>
                        {asStringArray(decision.open_questions).length > 0 && (
                            <ul style={listStyle}>
                                {asStringArray(decision.open_questions).map(
                                    (question, qIndex) => (
                                        <li key={qIndex}>{question}</li>
                                    ),
                                )}
                            </ul>
                        )}
                    </div>
                ))}
            </div>
        );
    }

    if (insight.type === 'project_risk') {
        return (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                {asRecordArray(payload.risks).map((risk, index) => (
                    <div key={index}>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                flexWrap: 'wrap',
                            }}
                        >
                            <span
                                style={{
                                    ...bodyText,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                {text(risk.risk)}
                            </span>
                            <Chip value={text(risk.severity) || 'sedang'} />
                            <span style={{ fontSize: 11.5, color: C.faint }}>
                                kemungkinan {text(risk.likelihood) || '-'}
                            </span>
                        </div>
                        <div style={bodyText}>
                            <strong style={{ color: C.muted }}>
                                Mitigasi:
                            </strong>{' '}
                            {text(risk.mitigation)}
                            {text(risk.owner) !== ''
                                ? ` · ${text(risk.owner)}`
                                : ''}
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    if (insight.type === 'sentiment') {
        return (
            <div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        marginBottom: 8,
                    }}
                >
                    <Chip value={text(payload.overall) || 'netral'} />
                    <span style={{ fontSize: 11.5, color: C.faint }}>
                        skor {text(payload.score)}
                    </span>
                </div>
                <p style={{ ...bodyText, margin: '0 0 8px' }}>
                    {text(payload.note)}
                </p>
                {asRecordArray(payload.per_speaker).map((entry, index) => (
                    <div key={index} style={bodyText}>
                        <strong style={{ color: C.navy }}>
                            {text(entry.speaker)}
                        </strong>{' '}
                        — {text(entry.sentiment)}: {text(entry.note)}
                    </div>
                ))}
                {asStringArray(payload.tension_points).length > 0 && (
                    <ul style={listStyle}>
                        {asStringArray(payload.tension_points).map(
                            (point, index) => (
                                <li key={index}>{point}</li>
                            ),
                        )}
                    </ul>
                )}
            </div>
        );
    }

    if (insight.type === 'follow_up') {
        return (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                {asRecordArray(payload.recommendations).map(
                    (recommendation, index) => (
                        <div key={index}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    flexWrap: 'wrap',
                                }}
                            >
                                <span
                                    style={{
                                        ...bodyText,
                                        fontWeight: 600,
                                        color: C.navy,
                                    }}
                                >
                                    {text(recommendation.action)}
                                </span>
                                <Chip
                                    value={
                                        text(recommendation.priority) ||
                                        'sedang'
                                    }
                                />
                            </div>
                            <div style={{ fontSize: 11.5, color: C.faint }}>
                                {text(recommendation.owner) || 'tanpa pemilik'}
                                {text(recommendation.deadline) !== ''
                                    ? ` · ${text(recommendation.deadline)}`
                                    : ''}
                            </div>
                        </div>
                    ),
                )}
            </div>
        );
    }

    return null;
}

interface InsightPanelProps {
    insight: InsightRow;
    disabled: boolean;
    onGenerate: (refresh: boolean) => void;
}

/**
 * One collapsible analysis: the button that pays for it, and the stored answer
 * once it has been paid for.
 */
export function InsightPanel({
    insight,
    disabled,
    onGenerate,
}: InsightPanelProps) {
    const has = insight.payload !== null;
    const [open, setOpen] = useState(false);

    return (
        <div style={rowStyle}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 12,
                    flexWrap: 'wrap',
                }}
            >
                <div>
                    <div
                        style={{
                            fontSize: 13.5,
                            fontWeight: 600,
                            color: C.text,
                        }}
                    >
                        {insight.label}
                    </div>
                    <div style={{ fontSize: 11.5, color: C.faint }}>
                        {has
                            ? `${formatDateTime(insight.generated_at)} · ${insight.tokens.toLocaleString('id-ID')} token`
                            : 'Belum dibuat — memotong token perusahaan saat dijalankan.'}
                    </div>
                </div>

                <div style={{ display: 'flex', gap: 8 }}>
                    {has && (
                        <button
                            type="button"
                            style={{ ...btnOut, height: 34, padding: '0 12px' }}
                            onClick={() => setOpen((current) => !current)}
                        >
                            <AIcon
                                name={open ? 'chevron-up' : 'chevron-down'}
                                size={14}
                            />
                            {open ? 'Tutup' : 'Lihat'}
                        </button>
                    )}
                    <button
                        type="button"
                        disabled={disabled}
                        style={{
                            ...(has ? btnOut : btnProcess),
                            height: 34,
                            padding: '0 12px',
                            opacity: disabled ? 0.5 : 1,
                            cursor: disabled ? 'not-allowed' : 'pointer',
                        }}
                        onClick={() => {
                            onGenerate(has);
                            setOpen(true);
                        }}
                    >
                        <AIcon
                            name={has ? 'refresh-cw' : 'sparkles'}
                            size={14}
                            color={has ? C.text : '#fff'}
                        />
                        {has ? 'Perbarui' : 'Buat'}
                    </button>
                </div>
            </div>

            {has && open && (
                <div
                    style={{
                        borderTop: `1px solid ${C.line}`,
                        marginTop: 12,
                        paddingTop: 12,
                    }}
                >
                    <Payload insight={insight} />
                </div>
            )}
        </div>
    );
}
