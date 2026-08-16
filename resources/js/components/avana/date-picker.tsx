import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import { createPortal } from 'react-dom';
import { AIcon, C, hexA } from '@/lib/avana';

/** Wide enough for the month + year dropdowns to sit between the chevrons. */
const PANEL_WIDTH = 288;
/** Roughly the panel's height; only used to decide whether to flip upward. */
const PANEL_HEIGHT = 330;

const MONTHS = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];
const DOW = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

/** Parse a `YYYY-MM-DD` string as a *local* date (no timezone shift). */
function parseYmd(value: string): Date {
    const [y, m, d] = value.split('-').map(Number);

    if (!y || !m || !d) {
        return new Date();
    }

    return new Date(y, m - 1, d);
}

/** Format a Date back to `YYYY-MM-DD` (local). */
function toYmd(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

function sameDay(a: Date, b: Date): boolean {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

/** How far back and forward the year dropdown reaches by default. */
const YEARS_BACK = 80;
const YEARS_AHEAD = 10;

/**
 * The years a year dropdown offers. `include` is always present, so a value
 * already outside the bounds — an old birth date, a far-off contract — still
 * shows the year it is actually on rather than snapping to an edge.
 */
function yearRange(
    include: number,
    minYear?: number,
    maxYear?: number,
): number[] {
    const currentYear = new Date().getFullYear();
    const from = Math.min(minYear ?? currentYear - YEARS_BACK, include);
    const to = Math.max(maxYear ?? currentYear + YEARS_AHEAD, include);

    return Array.from({ length: to - from + 1 }, (_, i) => to - i);
}

const jumpSelect: CSSProperties = {
    height: 28,
    padding: '0 4px',
    borderRadius: 7,
    border: `1px solid ${C.border}`,
    background: '#fff',
    fontSize: 12.5,
    fontWeight: 700,
    color: C.navy,
    cursor: 'pointer',
    outline: 'none',
};

/**
 * Month + year dropdowns. Stepping a chevron at a time is fine for the next
 * month and useless for a birth date twenty years back, so every calendar in
 * the app puts these two controls where the month label used to be.
 */
export function MonthYearJump({
    month,
    year,
    onChange,
    minYear,
    maxYear,
    style,
}: {
    /** 0-indexed, like `Date.getMonth()`. */
    month: number;
    year: number;
    onChange: (month: number, year: number) => void;
    minYear?: number;
    maxYear?: number;
    /** Merged into each dropdown, for pages that need a taller control. */
    style?: CSSProperties;
}) {
    const years = useMemo(
        () => yearRange(year, minYear, maxYear),
        [year, minYear, maxYear],
    );
    const control = { ...jumpSelect, ...style };

    return (
        <div style={{ display: 'flex', gap: 6 }}>
            <select
                value={month}
                onChange={(e) => onChange(Number(e.target.value), year)}
                style={control}
                aria-label="Bulan"
            >
                {MONTHS.map((m, i) => (
                    <option key={m} value={i}>
                        {m}
                    </option>
                ))}
            </select>
            <select
                value={year}
                onChange={(e) => onChange(month, Number(e.target.value))}
                style={control}
                aria-label="Tahun"
            >
                {years.map((y) => (
                    <option key={y} value={y}>
                        {y}
                    </option>
                ))}
            </select>
        </div>
    );
}

/**
 * A self-contained calendar date picker (button trigger + pop-over grid),
 * styled with the Avana palette and Indonesian labels. Value/onChange speak
 * `YYYY-MM-DD`; no external date library or extra dependency.
 */
export function DatePicker({
    value,
    onChange,
    label,
    width = 'auto',
    placeholder,
    hasError = false,
    minYear,
    maxYear,
    minDate,
    maxDate,
}: {
    value: string;
    onChange: (value: string) => void;
    label?: string;
    /** Any CSS width: a number of pixels, 'auto', or '100%' inside a form grid. */
    width?: number | string;
    /**
     * Shown instead of a date while `value` is empty. Without it an unfilled
     * required field reads as though today were already chosen.
     */
    placeholder?: string;
    hasError?: boolean;
    /**
     * Bounds of the year dropdown. Defaults reach 80 years back (old enough for
     * a birth date) and 10 forward (far enough for a contract end).
     */
    minYear?: number;
    maxYear?: number;
    /**
     * Selectable range as `YYYY-MM-DD`. Days outside it are greyed and inert,
     * the year dropdown stops there, and an empty field opens on the nearest
     * allowed month instead of today — a birth date field bounded to "17 years
     * ago" must not open on a month where every day is refused, which is how
     * employee rows ended up carrying the date they were typed on.
     */
    minDate?: string;
    maxDate?: string;
}) {
    const [open, setOpen] = useState(false);
    const selected = useMemo(() => parseYmd(value), [value]);

    const floor = useMemo(
        () => (minDate ? parseYmd(minDate) : null),
        [minDate],
    );
    const ceiling = useMemo(
        () => (maxDate ? parseYmd(maxDate) : null),
        [maxDate],
    );

    /** Where the calendar opens when the field is empty. */
    const openingMonth = useCallback((): Date => {
        const now = new Date();

        if (ceiling && now > ceiling) {
            return ceiling;
        }

        if (floor && now < floor) {
            return floor;
        }

        return now;
    }, [ceiling, floor]);

    const viewFor = useCallback(
        (current: string): Date =>
            current === '' ? openingMonth() : parseYmd(current),
        [openingMonth],
    );

    const outOfRange = useCallback(
        (date: Date): boolean =>
            (floor !== null && date < floor && !sameDay(date, floor)) ||
            (ceiling !== null && date > ceiling && !sameDay(date, ceiling)),
        [floor, ceiling],
    );

    const [view, setView] = useState(() => viewFor(value));
    const rootRef = useRef<HTMLDivElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);
    const [anchor, setAnchor] = useState({ top: 0, left: 0 });
    const today = new Date();

    /**
     * Pin the panel to the trigger in viewport coordinates. It renders in a
     * portal because any ancestor with `overflow: hidden` — a card, a table
     * wrapper — would otherwise clip it.
     */
    const positionPanel = useCallback(() => {
        const trigger = rootRef.current?.getBoundingClientRect();

        if (!trigger) {
            return;
        }

        // Measured once the panel exists; the constant only covers the first
        // paint, before there is anything to measure.
        const height = panelRef.current?.offsetHeight ?? PANEL_HEIGHT;

        const flipUp =
            trigger.bottom + height > window.innerHeight &&
            trigger.top > height;

        setAnchor({
            top: flipUp
                ? Math.max(8, trigger.top - height - 6)
                : Math.min(
                      trigger.bottom + 6,
                      Math.max(8, window.innerHeight - height - 8),
                  ),
            // Keep it on screen when the trigger sits near the right edge.
            left: Math.max(
                8,
                Math.min(trigger.left, window.innerWidth - PANEL_WIDTH - 8),
            ),
        });
    }, []);

    // Re-run once the panel has rendered, now that its real height is known.
    useEffect(() => {
        if (open) {
            positionPanel();
        }
    }, [open, positionPanel]);

    // Re-anchor the visible month whenever the picker opens or the value moves.
    useEffect(() => {
        if (open) {
            setView(viewFor(value));
        }
    }, [open, value, viewFor]);

    // Close on outside click or Escape, and follow the trigger while open.
    useEffect(() => {
        if (!open) {
            return;
        }

        const onDown = (event: MouseEvent) => {
            const target = event.target as Node;

            // The panel lives in a portal, so it is not inside rootRef.
            if (
                !rootRef.current?.contains(target) &&
                !panelRef.current?.contains(target)
            ) {
                setOpen(false);
            }
        };
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        window.addEventListener('scroll', positionPanel, true);
        window.addEventListener('resize', positionPanel);

        return () => {
            window.removeEventListener('scroll', positionPanel, true);
            window.removeEventListener('resize', positionPanel);
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open, positionPanel]);

    // Build the month grid: leading blanks (Mon-start) then day cells.
    const cells = useMemo(() => {
        const year = view.getFullYear();
        const month = view.getMonth();
        const firstDow = (new Date(year, month, 1).getDay() + 6) % 7; // Mon = 0
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const out: (Date | null)[] = [];

        for (let i = 0; i < firstDow; i += 1) {
            out.push(null);
        }

        for (let d = 1; d <= daysInMonth; d += 1) {
            out.push(new Date(year, month, d));
        }

        return out;
    }, [view]);

    const isEmpty = value === '';
    const buttonLabel = isEmpty
        ? (placeholder ?? 'Pilih tanggal')
        : `${selected.getDate()} ${MONTHS[selected.getMonth()]} ${selected.getFullYear()}`;

    const navBtn: CSSProperties = {
        width: 28,
        height: 28,
        display: 'grid',
        placeItems: 'center',
        borderRadius: 7,
        border: `1px solid ${C.border}`,
        background: '#fff',
        cursor: 'pointer',
        color: C.text,
    };

    return (
        <div ref={rootRef} style={{ position: 'relative' }}>
            <button
                type="button"
                onClick={() => {
                    positionPanel();
                    setOpen((o) => !o);
                }}
                style={{
                    width,
                    height: 40,
                    padding: '0 14px',
                    display: 'flex',
                    alignItems: 'center',
                    whiteSpace: 'nowrap',
                    gap: 8,
                    background: '#fff',
                    border: `1px solid ${hasError ? C.red : open ? C.primary : C.border}`,
                    borderRadius: 8,
                    fontSize: 13.5,
                    color: C.text,
                    cursor: 'pointer',
                    outline: 'none',
                    boxShadow: hasError
                        ? `0 0 0 3px ${hexA(C.red, 0.08)}`
                        : open
                          ? `0 0 0 3px ${hexA(C.primary, 0.14)}`
                          : 'none',
                }}
            >
                <AIcon name="calendar" size={16} color={C.muted} />
                {label && (
                    <span style={{ color: C.faint, fontWeight: 600 }}>
                        {label}
                    </span>
                )}
                <span
                    style={{
                        fontWeight: isEmpty ? 400 : 500,
                        color: isEmpty ? C.faint : C.text,
                    }}
                >
                    {buttonLabel}
                </span>
            </button>

            {open &&
                createPortal(
                    <div
                        ref={panelRef}
                        style={{
                            position: 'fixed',
                            top: anchor.top,
                            left: anchor.left,
                            zIndex: 90,
                            width: PANEL_WIDTH,
                            padding: 12,
                            background: '#fff',
                            border: `1px solid ${C.border}`,
                            borderRadius: 12,
                            boxShadow: '0 12px 32px rgba(15,23,42,.14)',
                        }}
                    >
                        {/* Month nav */}
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                marginBottom: 10,
                            }}
                        >
                            <button
                                type="button"
                                style={navBtn}
                                onClick={() =>
                                    setView(
                                        (v) =>
                                            new Date(
                                                v.getFullYear(),
                                                v.getMonth() - 1,
                                                1,
                                            ),
                                    )
                                }
                            >
                                <AIcon name="chevron-left" size={16} />
                            </button>
                            <MonthYearJump
                                month={view.getMonth()}
                                year={view.getFullYear()}
                                onChange={(m, y) => setView(new Date(y, m, 1))}
                                minYear={minYear ?? floor?.getFullYear()}
                                maxYear={maxYear ?? ceiling?.getFullYear()}
                            />
                            <button
                                type="button"
                                style={navBtn}
                                onClick={() =>
                                    setView(
                                        (v) =>
                                            new Date(
                                                v.getFullYear(),
                                                v.getMonth() + 1,
                                                1,
                                            ),
                                    )
                                }
                            >
                                <AIcon name="chevron-right" size={16} />
                            </button>
                        </div>

                        {/* Day-of-week header */}
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: 'repeat(7, 1fr)',
                                gap: 2,
                                marginBottom: 4,
                            }}
                        >
                            {DOW.map((d) => (
                                <div
                                    key={d}
                                    style={{
                                        textAlign: 'center',
                                        fontSize: 10.5,
                                        fontWeight: 600,
                                        color: C.faint,
                                        padding: '4px 0',
                                    }}
                                >
                                    {d}
                                </div>
                            ))}
                        </div>

                        {/* Day grid */}
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: 'repeat(7, 1fr)',
                                gap: 2,
                            }}
                        >
                            {cells.map((cell, i) => {
                                if (!cell) {
                                    return <div key={`b${i}`} />;
                                }

                                const isSel = sameDay(cell, selected);
                                const isToday = sameDay(cell, today);
                                const blocked = outOfRange(cell);

                                return (
                                    <button
                                        key={toYmd(cell)}
                                        type="button"
                                        disabled={blocked}
                                        onClick={() => {
                                            onChange(toYmd(cell));
                                            setOpen(false);
                                        }}
                                        style={{
                                            height: 32,
                                            border: 'none',
                                            borderRadius: 8,
                                            cursor: blocked
                                                ? 'not-allowed'
                                                : 'pointer',
                                            fontSize: 12.5,
                                            fontWeight:
                                                isSel || isToday ? 700 : 500,
                                            background: isSel
                                                ? C.primary
                                                : 'transparent',
                                            color: blocked
                                                ? C.faint
                                                : isSel
                                                  ? '#fff'
                                                  : isToday
                                                    ? C.primary
                                                    : C.text,
                                            opacity: blocked ? 0.4 : 1,
                                            outline:
                                                isToday && !isSel
                                                    ? `1px solid ${hexA(C.primary, 0.4)}`
                                                    : 'none',
                                        }}
                                    >
                                        {cell.getDate()}
                                    </button>
                                );
                            })}
                        </div>

                        {/* Footer: quick "today", only while today is allowed */}
                        {!outOfRange(today) && (
                            <button
                                type="button"
                                onClick={() => {
                                    onChange(toYmd(today));
                                    setOpen(false);
                                }}
                                style={{
                                    marginTop: 10,
                                    width: '100%',
                                    height: 34,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 8,
                                    background: '#fff',
                                    fontSize: 12.5,
                                    fontWeight: 600,
                                    color: C.primary,
                                    cursor: 'pointer',
                                }}
                            >
                                Hari ini
                            </button>
                        )}
                    </div>,
                    document.body,
                )}
        </div>
    );
}

/** Parse a `YYYY-MM` string into [year, 0-indexed month], today when blank. */
function parseYm(value: string): [number, number] {
    const [y, m] = value.split('-').map(Number);
    const now = new Date();

    if (!y || !m) {
        return [now.getFullYear(), now.getMonth()];
    }

    return [y, m - 1];
}

/**
 * A month-and-year field: the same two dropdowns the calendar header uses,
 * standing on their own. Value/onChange speak `YYYY-MM`, matching what an
 * `<input type="month">` submits — so it drops in where one used to be, with a
 * consistent look and a year list that is not the browser's.
 */
export function MonthPicker({
    value,
    onChange,
    minYear,
    maxYear,
    hasError = false,
}: {
    value: string;
    onChange: (value: string) => void;
    minYear?: number;
    maxYear?: number;
    hasError?: boolean;
}) {
    const [year, month] = parseYm(value);

    return (
        <MonthYearJump
            month={month}
            year={year}
            minYear={minYear}
            maxYear={maxYear}
            onChange={(m, y) =>
                onChange(`${y}-${String(m + 1).padStart(2, '0')}`)
            }
            style={{
                height: 40,
                padding: '0 10px',
                fontSize: 13.5,
                fontWeight: 500,
                color: C.text,
                border: `1px solid ${hasError ? C.red : C.border}`,
            }}
        />
    );
}

/**
 * A date field paired with a time field. Value/onChange speak
 * `YYYY-MM-DDTHH:mm`, the format an `<input type="datetime-local">` submits and
 * Laravel's date validation accepts.
 */
export function DateTimePicker({
    value,
    onChange,
    hasError = false,
    minYear,
    maxYear,
}: {
    value: string;
    onChange: (value: string) => void;
    hasError?: boolean;
    minYear?: number;
    maxYear?: number;
}) {
    const [date = '', time = ''] = value.split('T');

    /**
     * Neither half means anything alone, so a missing counterpart is filled in:
     * today for the date, 09:00 for the time — a working hour rather than
     * midnight, since this schedules interviews.
     */
    const emit = (nextDate: string, nextTime: string) => {
        if (nextDate === '' && nextTime === '') {
            onChange('');

            return;
        }

        onChange(`${nextDate || toYmd(new Date())}T${nextTime || '09:00'}`);
    };

    return (
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <DatePicker
                value={date}
                onChange={(d) => emit(d, time)}
                placeholder="Pilih tanggal"
                hasError={hasError}
                minYear={minYear}
                maxYear={maxYear}
            />
            <input
                type="time"
                value={time}
                onChange={(e) => emit(date, e.target.value)}
                style={{
                    height: 40,
                    padding: '0 10px',
                    borderRadius: 8,
                    border: `1px solid ${hasError ? C.red : C.border}`,
                    background: '#fff',
                    fontSize: 13.5,
                    color: C.text,
                    outline: 'none',
                }}
                aria-label="Jam"
            />
        </div>
    );
}
