import type { CSSProperties } from 'react';
import { AIcon, C } from '@/lib/avana';
import { initialsOf } from './types';
import type { RosterEmployee, RosterShift } from './types';

/* ---------- shared grid styles ---------- */

export const headThStyle: CSSProperties = {
    padding: '11px 14px',
    textAlign: 'center',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
    borderLeft: `1px solid ${C.line}`,
};

export const navBtnStyle: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    height: 38,
    minWidth: 38,
    padding: '0 12px',
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13,
    fontWeight: 500,
    color: C.text,
    cursor: 'pointer',
    transition: '.15s',
};

/* ---------- presentational atoms ---------- */

interface WeekNavigatorProps {
    rangeLabel: string;
    onPrev: () => void;
    onNext: () => void;
    onToday: () => void;
}

/** Prev / next / today controls with the active week range label. */
export function WeekNavigator({
    rangeLabel,
    onPrev,
    onNext,
    onToday,
}: WeekNavigatorProps) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 10,
            }}
        >
            <button
                onClick={onPrev}
                style={navBtnStyle}
                aria-label="Minggu sebelumnya"
            >
                <AIcon name="chevron-left" size={16} />
            </button>
            <div
                style={{
                    fontSize: 14.5,
                    fontWeight: 600,
                    color: C.navy,
                    minWidth: 180,
                    textAlign: 'center',
                }}
            >
                {rangeLabel}
            </div>
            <button
                onClick={onNext}
                style={navBtnStyle}
                aria-label="Minggu berikutnya"
            >
                <AIcon name="chevron-right" size={16} />
            </button>
            <button onClick={onToday} style={navBtnStyle}>
                Minggu Ini
            </button>
        </div>
    );
}

interface ShiftLegendProps {
    shifts: RosterShift[];
    colorForShift: (shiftId: number) => string;
}

/** Colored swatch legend describing the available shifts. */
export function ShiftLegend({ shifts, colorForShift }: ShiftLegendProps) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 14,
                flexWrap: 'wrap',
            }}
        >
            {shifts.map((shift) => {
                const color = colorForShift(shift.id);

                return (
                    <div
                        key={shift.id}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 7,
                            fontSize: 12.5,
                            color: C.muted,
                        }}
                    >
                        <span
                            style={{
                                width: 12,
                                height: 12,
                                borderRadius: 3,
                                background: color,
                            }}
                        />
                        <span
                            style={{
                                color: C.text,
                                fontWeight: 500,
                            }}
                        >
                            {shift.code ?? shift.name}
                        </span>
                        {shift.start_time && shift.end_time && (
                            <span style={{ color: C.faint }}>
                                {shift.start_time}–{shift.end_time}
                            </span>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

/** Avatar + name + employee number cell for a roster row. */
export function EmployeeCell({ employee }: { employee: RosterEmployee }) {
    return (
        <td style={{ padding: '12px 18px' }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                }}
            >
                <div
                    style={{
                        width: 32,
                        height: 32,
                        borderRadius: '50%',
                        flex: 'none',
                        background: C.primary,
                        color: '#fff',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        fontSize: 11.5,
                        fontWeight: 600,
                    }}
                >
                    {initialsOf(employee.name)}
                </div>
                <div>
                    <div
                        style={{
                            fontSize: 13,
                            fontWeight: 500,
                            color: C.text,
                        }}
                    >
                        {employee.name}
                    </div>
                    <div
                        style={{
                            fontSize: 11.5,
                            color: C.faint,
                        }}
                    >
                        {employee.employee_number}
                    </div>
                </div>
            </div>
        </td>
    );
}
