import type { CSSProperties } from 'react';
import { C, card } from '@/lib/avana';
import type { MeetingStats } from './types';

/**
 * The measurable side of a meeting — how long, how much was said, and who did
 * the saying.
 *
 * Deliberately not a chart on the summary itself: that is prose, and a graph on
 * prose is decoration. These answer the questions the prose cannot — "did one
 * person take the whole hour?", "how much of this is still outstanding?" — from
 * data the transcript already carries.
 */

/**
 * Categorical hues, in fixed order, validated against the chart surface for
 * colour-blind separation and 3:1 contrast. Never cycled: a fifth speaker folds
 * into "Lainnya" rather than earning a generated hue nobody can tell apart.
 */
const TALK_HUES = [C.primary, C.amber, C.violet, C.green] as const;

const OTHER_HUE = C.faint;

function seconds(ms: number): string {
    const total = Math.round(ms / 1000);
    const minutes = Math.floor(total / 60);
    const rest = total % 60;

    if (minutes === 0) {
        return `${rest} detik`;
    }

    // "2m 0d" reads like a typo; a round number of minutes says so plainly.
    return rest === 0 ? `${minutes} menit` : `${minutes}m ${rest}d`;
}

const tileValue: CSSProperties = {
    fontSize: 22,
    fontWeight: 700,
    color: C.navy,
    lineHeight: 1.2,
    fontVariantNumeric: 'tabular-nums',
};

const tileLabel: CSSProperties = {
    fontSize: 11.5,
    color: C.muted,
    marginTop: 2,
};

function Tile({ value, label }: { value: string; label: string }) {
    return (
        <div style={{ minWidth: 0 }}>
            <div style={tileValue}>{value}</div>
            <div style={tileLabel}>{label}</div>
        </div>
    );
}

/**
 * A row of headline numbers. Four figures, not four charts — a single current
 * value is a stat tile's job, and a one-bar bar chart would be worse.
 */
export function MeetingStatTiles({ stats }: { stats: MeetingStats }) {
    return (
        <div
            style={{
                ...card,
                padding: 18,
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(110px, 1fr))',
                gap: 16,
            }}
        >
            <Tile value={seconds(stats.duration_ms)} label="Durasi rapat" />
            <Tile value={String(stats.lines)} label="Ucapan" />
            <Tile value={String(stats.speakers)} label="Pembicara" />
            <Tile
                value={`${stats.action_items.done}/${stats.action_items.total}`}
                label="Tindak lanjut selesai"
            />
        </div>
    );
}

/**
 * Who held the floor, as one horizontal bar per speaker.
 *
 * Horizontal because names are long, and directly labelled so identity never
 * rests on colour alone. Not a pie: two or three slices are never easier to
 * compare than two or three bars.
 */
export function TalkShareChart({ stats }: { stats: MeetingStats }) {
    if (stats.talk.length === 0 || stats.spoken_ms === 0) {
        return null;
    }

    // Past four speakers the tail folds together rather than inventing hues.
    const head = stats.talk.slice(0, TALK_HUES.length);
    const tail = stats.talk.slice(TALK_HUES.length);

    const rows = [
        ...head.map((row, index) => ({
            key: String(row.speaker_index),
            name: row.name,
            share: row.share,
            ms: row.ms,
            lines: row.lines,
            color: TALK_HUES[index],
        })),
        ...(tail.length > 0
            ? [
                  {
                      key: 'other',
                      name: `${tail.length} pembicara lain`,
                      share: tail.reduce((sum, row) => sum + row.share, 0),
                      ms: tail.reduce((sum, row) => sum + row.ms, 0),
                      lines: tail.reduce((sum, row) => sum + row.lines, 0),
                      color: OTHER_HUE,
                  },
              ]
            : []),
    ];

    return (
        <div style={{ ...card, padding: 22 }}>
            <div
                style={{
                    fontSize: 15,
                    fontWeight: 600,
                    color: C.navy,
                    marginBottom: 4,
                }}
            >
                Porsi Bicara
            </div>
            <div style={{ fontSize: 12.5, color: C.muted, marginBottom: 16 }}>
                Perkiraan dari pemisahan suara otomatis, bukan pengukuran
                stopwatch.
            </div>

            <div style={{ display: 'grid', gap: 14 }}>
                {rows.map((row) => (
                    <div key={row.key}>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'baseline',
                                gap: 12,
                                marginBottom: 6,
                            }}
                        >
                            <span
                                style={{
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    color: C.text,
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                    whiteSpace: 'nowrap',
                                }}
                            >
                                {row.name}
                            </span>
                            <span
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    whiteSpace: 'nowrap',
                                    fontVariantNumeric: 'tabular-nums',
                                }}
                            >
                                {row.share}% · {seconds(row.ms)} · {row.lines}{' '}
                                ucapan
                            </span>
                        </div>
                        <div
                            style={{
                                height: 10,
                                borderRadius: 999,
                                background: C.line,
                                overflow: 'hidden',
                            }}
                        >
                            <div
                                style={{
                                    width: `${Math.max(row.share, 1)}%`,
                                    height: '100%',
                                    borderRadius: 999,
                                    background: row.color,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

/** Clock label for the timeline axis. */
function clock(ms: number): string {
    const total = Math.round(ms / 1000);
    const minutes = Math.floor(total / 60);

    return `${minutes}:${String(total % 60).padStart(2, '0')}`;
}

/**
 * Who spoke when, laid along the meeting's own clock.
 *
 * The point is navigation, not measurement: on a long recording nobody reads
 * the transcript top to bottom, they want the part where a particular person
 * took the floor. Each block is a turn and lands in the transcript when
 * clicked.
 *
 * Shown only once there is a shape to see — one speaker, or a handful of
 * turns, is a picture of nothing.
 */
export function TurnTimeline({
    stats,
    onSeek,
}: {
    stats: MeetingStats;
    onSeek: (lineId: number) => void;
}) {
    const span = stats.turns.length
        ? Math.max(
              stats.duration_ms,
              stats.turns[stats.turns.length - 1].end_ms,
          )
        : 0;

    if (stats.speakers < 2 || stats.turns.length < 6 || span === 0) {
        return null;
    }

    // Same fixed order as the share bars, so a colour means one person across
    // the whole page.
    const order = stats.talk.map((row) => row.speaker_index);
    const hueOf = (speakerIndex: number): string => {
        const rank = order.indexOf(speakerIndex);

        return rank >= 0 && rank < TALK_HUES.length
            ? TALK_HUES[rank]
            : OTHER_HUE;
    };

    const lanes = stats.talk.slice(0, TALK_HUES.length + 1);

    return (
        <div style={{ ...card, padding: 22 }}>
            <div
                style={{
                    fontSize: 15,
                    fontWeight: 600,
                    color: C.navy,
                    marginBottom: 4,
                }}
            >
                Peta Giliran Bicara
            </div>
            <div style={{ fontSize: 12.5, color: C.muted, marginBottom: 16 }}>
                Klik bagian mana pun untuk lompat ke transkripnya.
            </div>

            <div style={{ display: 'grid', gap: 10 }}>
                {lanes.map((lane) => (
                    <div
                        key={lane.speaker_index}
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                        }}
                    >
                        <span
                            style={{
                                fontSize: 12,
                                color: C.muted,
                                width: 110,
                                flex: 'none',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                            title={lane.name}
                        >
                            {lane.name}
                        </span>
                        <div
                            style={{
                                position: 'relative',
                                flex: 1,
                                height: 16,
                                borderRadius: 6,
                                background: C.line,
                                minWidth: 0,
                            }}
                        >
                            {stats.turns
                                .filter(
                                    (turn) =>
                                        turn.speaker_index ===
                                        lane.speaker_index,
                                )
                                .map((turn) => (
                                    <button
                                        key={turn.line_id}
                                        type="button"
                                        onClick={() => onSeek(turn.line_id)}
                                        title={`${clock(turn.start_ms)} · ${turn.lines} ucapan`}
                                        style={{
                                            position: 'absolute',
                                            left: `${(turn.start_ms / span) * 100}%`,
                                            // Never thinner than a thumb can
                                            // hit, however short the turn.
                                            width: `${Math.max(((turn.end_ms - turn.start_ms) / span) * 100, 1.2)}%`,
                                            top: 0,
                                            bottom: 0,
                                            padding: 0,
                                            border: 'none',
                                            borderRadius: 4,
                                            background: hueOf(
                                                lane.speaker_index,
                                            ),
                                            cursor: 'pointer',
                                        }}
                                    />
                                ))}
                        </div>
                    </div>
                ))}
            </div>

            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    marginLeft: 122,
                    marginTop: 8,
                    fontSize: 11,
                    color: C.faint,
                    fontVariantNumeric: 'tabular-nums',
                }}
            >
                <span>0:00</span>
                <span>{clock(span / 2)}</span>
                <span>{clock(span)}</span>
            </div>
        </div>
    );
}

/**
 * How much of the follow-up list is done — a single ratio against a limit, so a
 * meter rather than a two-slice pie.
 */
export function ActionItemMeter({ stats }: { stats: MeetingStats }) {
    const { total, done } = stats.action_items;

    if (total === 0) {
        return null;
    }

    const pct = Math.round((done / total) * 100);
    // The fill carries severity: finished is settled, nothing done yet is worth
    // a warning tone rather than the same calm blue.
    const fill = pct === 100 ? C.green : pct === 0 ? C.amber : C.primary;

    return (
        <div style={{ marginBottom: 16 }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'baseline',
                    marginBottom: 6,
                }}
            >
                <span style={{ fontSize: 12.5, color: C.muted }}>
                    {done} dari {total} selesai
                </span>
                <span
                    style={{
                        fontSize: 12.5,
                        fontWeight: 700,
                        color: fill,
                        fontVariantNumeric: 'tabular-nums',
                    }}
                >
                    {pct}%
                </span>
            </div>
            <div
                style={{
                    height: 8,
                    borderRadius: 999,
                    background: C.line,
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        width: `${pct}%`,
                        height: '100%',
                        borderRadius: 999,
                        background: fill,
                    }}
                />
            </div>
        </div>
    );
}
