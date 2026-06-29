import { Head, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import RosterController from '@/actions/App/Http/Controllers/Avana/RosterController';
import { AIcon, C, card } from '@/lib/avana';
import type { FlashProps } from './employees/types';

/* ============================================================
 * Types — mirror the RosterController index payload.
 * ============================================================ */

interface RosterEmployee {
    id: number;
    name: string;
    employee_number: string;
}

interface RosterShift {
    id: number;
    code: string | null;
    name: string;
    start_time: string | null;
    end_time: string | null;
}

interface RosterSchedule {
    id: number;
    employee_id: number;
    shift_id: number;
    date: string;
}

interface RosterWeekDay {
    date: string;
    dow: string;
    day: number;
    label: string;
}

interface RosterProps {
    employees: RosterEmployee[];
    shifts: RosterShift[];
    schedules: RosterSchedule[];
    week: RosterWeekDay[];
    week_start: string;
}

/** Deterministic chip palette assigned to shifts by their order. */
const SHIFT_PALETTE = [
    '#2F54C9', '#16A34A', '#D97706', '#8b5cf6',
    '#0ea5e9', '#ec4899', '#14b8a6', '#f97316',
];

/** Format a Date as a local `Y-m-d` string (no UTC drift). */
function toIso(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

/** Two uppercase initials derived from a name. */
function initialsOf(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean).slice(0, 2);

    return parts.map((part) => part.charAt(0).toUpperCase()).join('') || '?';
}

const headThStyle: CSSProperties = {
    padding: '11px 14px',
    textAlign: 'center',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
    borderLeft: `1px solid ${C.line}`,
};

const navBtnStyle: CSSProperties = {
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

export default function AvanaRoster({
    employees,
    shifts,
    schedules,
    week,
    week_start,
}: RosterProps) {
    const { flash } = usePage<FlashProps>().props;
    const [openCell, setOpenCell] = useState<string | null>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const colorForShift = (shiftId: number): string => {
        const index = shifts.findIndex((shift) => shift.id === shiftId);

        return SHIFT_PALETTE[(index < 0 ? 0 : index) % SHIFT_PALETTE.length];
    };

    const scheduleFor = (
        employeeId: number,
        date: string,
    ): RosterSchedule | undefined =>
        schedules.find(
            (schedule) =>
                schedule.employee_id === employeeId && schedule.date === date,
        );

    const shiftFor = (shiftId: number): RosterShift | undefined =>
        shifts.find((shift) => shift.id === shiftId);

    const shiftWeek = (deltaDays: number) => {
        const base = new Date(`${week_start}T00:00:00`);
        base.setDate(base.getDate() + deltaDays);

        router.get(
            RosterController.index().url,
            { week_start: toIso(base) },
            { preserveScroll: true, preserveState: false },
        );
    };

    const goToday = () => {
        router.get(
            RosterController.index().url,
            {},
            { preserveScroll: true, preserveState: false },
        );
    };

    const assignShift = (
        employeeId: number,
        date: string,
        shiftId: number,
    ) => {
        router.post(
            RosterController.store().url,
            { employee_id: employeeId, shift_id: shiftId, date },
            {
                preserveScroll: true,
                onFinish: () => setOpenCell(null),
            },
        );
    };

    const removeSchedule = (scheduleId: number) => {
        router.delete(RosterController.destroy(scheduleId).url, {
            preserveScroll: true,
            onFinish: () => setOpenCell(null),
        });
    };

    const weekYear = week_start.slice(0, 4);
    const rangeLabel =
        week.length > 0
            ? `${week[0].label} – ${week[week.length - 1].label} ${weekYear}`
            : '';

    return (
        <>
            <Head title="Roster Shift" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ marginBottom: 22 }}>
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
                        <span>Manajemen</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Roster Shift</span>
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
                        Roster Shift
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Atur jadwal shift mingguan untuk setiap karyawan.
                    </div>
                </div>

                {/* Week navigator + legend */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 14,
                        marginBottom: 16,
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                        }}
                    >
                        <button
                            onClick={() => shiftWeek(-7)}
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
                            onClick={() => shiftWeek(7)}
                            style={navBtnStyle}
                            aria-label="Minggu berikutnya"
                        >
                            <AIcon name="chevron-right" size={16} />
                        </button>
                        <button onClick={goToday} style={navBtnStyle}>
                            Minggu Ini
                        </button>
                    </div>

                    {/* Legend */}
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
                </div>

                {/* Roster grid */}
                <div style={{ ...card, overflow: 'visible' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 880,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th
                                        style={{
                                            padding: '11px 18px',
                                            textAlign: 'left',
                                            fontSize: 11.5,
                                            fontWeight: 600,
                                            color: C.faint,
                                            textTransform: 'uppercase',
                                            minWidth: 220,
                                        }}
                                    >
                                        Karyawan
                                    </th>
                                    {week.map((day) => (
                                        <th key={day.date} style={headThStyle}>
                                            <div
                                                style={{
                                                    color: C.muted,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {day.dow}
                                            </div>
                                            <div
                                                style={{
                                                    color: C.faint,
                                                    fontWeight: 500,
                                                    marginTop: 2,
                                                    textTransform: 'none',
                                                }}
                                            >
                                                {day.label}
                                            </div>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {employees.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={week.length + 1}
                                            style={{
                                                padding: '48px 18px',
                                                textAlign: 'center',
                                                fontSize: 13.5,
                                                color: C.muted,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    flexDirection: 'column',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                }}
                                            >
                                                <AIcon
                                                    name="users"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Belum ada karyawan aktif.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {employees.map((employee) => (
                                    <tr
                                        key={employee.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
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
                                                        justifyContent:
                                                            'center',
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
                                                        {
                                                            employee.employee_number
                                                        }
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        {week.map((day) => {
                                            const schedule = scheduleFor(
                                                employee.id,
                                                day.date,
                                            );
                                            const shift = schedule
                                                ? shiftFor(schedule.shift_id)
                                                : undefined;
                                            const cellKey = `${employee.id}-${day.date}`;
                                            const isOpen = openCell === cellKey;
                                            const color = schedule
                                                ? colorForShift(
                                                      schedule.shift_id,
                                                  )
                                                : C.faint;

                                            return (
                                                <td
                                                    key={day.date}
                                                    style={{
                                                        padding: '8px 10px',
                                                        textAlign: 'center',
                                                        borderLeft: `1px solid ${C.line}`,
                                                        verticalAlign: 'middle',
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            position:
                                                                'relative',
                                                            display:
                                                                'inline-block',
                                                            zIndex: isOpen
                                                                ? 50
                                                                : undefined,
                                                        }}
                                                    >
                                                        <button
                                                            onClick={() =>
                                                                setOpenCell(
                                                                    (prev) =>
                                                                        prev ===
                                                                        cellKey
                                                                            ? null
                                                                            : cellKey,
                                                                )
                                                            }
                                                            style={
                                                                schedule && shift
                                                                    ? {
                                                                          minWidth: 64,
                                                                          padding:
                                                                              '6px 10px',
                                                                          borderRadius: 7,
                                                                          border: 'none',
                                                                          background:
                                                                              color +
                                                                              '1a',
                                                                          color,
                                                                          fontSize: 12,
                                                                          fontWeight: 600,
                                                                          cursor: 'pointer',
                                                                          transition:
                                                                              '.15s',
                                                                      }
                                                                    : {
                                                                          width: 36,
                                                                          height: 36,
                                                                          borderRadius: 7,
                                                                          border: `1px dashed ${C.border}`,
                                                                          background:
                                                                              '#fff',
                                                                          color: C.faint,
                                                                          cursor: 'pointer',
                                                                          display:
                                                                              'inline-flex',
                                                                          alignItems:
                                                                              'center',
                                                                          justifyContent:
                                                                              'center',
                                                                          transition:
                                                                              '.15s',
                                                                      }
                                                            }
                                                        >
                                                            {schedule &&
                                                            shift ? (
                                                                shift.code ??
                                                                shift.name
                                                            ) : (
                                                                <AIcon
                                                                    name="plus"
                                                                    size={15}
                                                                    color={
                                                                        C.faint
                                                                    }
                                                                />
                                                            )}
                                                        </button>

                                                        {isOpen && (
                                                            <div
                                                                style={{
                                                                    position:
                                                                        'absolute',
                                                                    top: 42,
                                                                    left: '50%',
                                                                    transform:
                                                                        'translateX(-50%)',
                                                                    width: 188,
                                                                    background:
                                                                        '#fff',
                                                                    border: `1px solid ${C.border}`,
                                                                    borderRadius: 10,
                                                                    boxShadow:
                                                                        '0 8px 24px rgba(15,23,42,.14)',
                                                                    padding: 6,
                                                                    textAlign:
                                                                        'left',
                                                                }}
                                                            >
                                                                <div
                                                                    style={{
                                                                        fontSize: 11,
                                                                        fontWeight: 600,
                                                                        color: C.faint,
                                                                        textTransform:
                                                                            'uppercase',
                                                                        padding:
                                                                            '4px 8px 6px',
                                                                    }}
                                                                >
                                                                    Pilih Shift
                                                                </div>
                                                                {shifts.map(
                                                                    (option) => {
                                                                        const optionColor =
                                                                            colorForShift(
                                                                                option.id,
                                                                            );
                                                                        const selected =
                                                                            schedule?.shift_id ===
                                                                            option.id;

                                                                        return (
                                                                            <button
                                                                                key={
                                                                                    option.id
                                                                                }
                                                                                onClick={() =>
                                                                                    assignShift(
                                                                                        employee.id,
                                                                                        day.date,
                                                                                        option.id,
                                                                                    )
                                                                                }
                                                                                style={{
                                                                                    width: '100%',
                                                                                    display:
                                                                                        'flex',
                                                                                    alignItems:
                                                                                        'center',
                                                                                    gap: 9,
                                                                                    padding:
                                                                                        '8px 9px',
                                                                                    border: 'none',
                                                                                    background:
                                                                                        selected
                                                                                            ? C.surface
                                                                                            : 'none',
                                                                                    borderRadius: 7,
                                                                                    fontSize: 12.5,
                                                                                    color: C.text,
                                                                                    cursor: 'pointer',
                                                                                    transition:
                                                                                        '.12s',
                                                                                }}
                                                                            >
                                                                                <span
                                                                                    style={{
                                                                                        width: 10,
                                                                                        height: 10,
                                                                                        borderRadius: 3,
                                                                                        flex: 'none',
                                                                                        background:
                                                                                            optionColor,
                                                                                    }}
                                                                                />
                                                                                <span
                                                                                    style={{
                                                                                        flex: 1,
                                                                                        fontWeight: 500,
                                                                                    }}
                                                                                >
                                                                                    {
                                                                                        option.name
                                                                                    }
                                                                                </span>
                                                                                {selected && (
                                                                                    <AIcon
                                                                                        name="check"
                                                                                        size={
                                                                                            14
                                                                                        }
                                                                                        color={
                                                                                            C.primary
                                                                                        }
                                                                                    />
                                                                                )}
                                                                            </button>
                                                                        );
                                                                    },
                                                                )}
                                                                {schedule && (
                                                                    <>
                                                                        <div
                                                                            style={{
                                                                                height: 1,
                                                                                background:
                                                                                    C.line,
                                                                                margin: '5px 4px',
                                                                            }}
                                                                        />
                                                                        <button
                                                                            onClick={() =>
                                                                                removeSchedule(
                                                                                    schedule.id,
                                                                                )
                                                                            }
                                                                            style={{
                                                                                width: '100%',
                                                                                display:
                                                                                    'flex',
                                                                                alignItems:
                                                                                    'center',
                                                                                gap: 9,
                                                                                padding:
                                                                                    '8px 9px',
                                                                                border: 'none',
                                                                                background:
                                                                                    'none',
                                                                                borderRadius: 7,
                                                                                fontSize: 12.5,
                                                                                fontWeight: 500,
                                                                                color: C.red,
                                                                                cursor: 'pointer',
                                                                                transition:
                                                                                    '.12s',
                                                                            }}
                                                                        >
                                                                            <AIcon
                                                                                name="trash-2"
                                                                                size={
                                                                                    14
                                                                                }
                                                                                color={
                                                                                    C.red
                                                                                }
                                                                            />
                                                                            Hapus
                                                                        </button>
                                                                    </>
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Click-catcher to dismiss an open shift picker */}
            {openCell && (
                <div
                    onClick={() => setOpenCell(null)}
                    style={{
                        position: 'fixed',
                        inset: 0,
                        zIndex: 30,
                        background: 'transparent',
                    }}
                />
            )}
        </>
    );
}
