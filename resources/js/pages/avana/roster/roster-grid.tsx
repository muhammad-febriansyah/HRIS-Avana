import type { Dispatch, SetStateAction } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import { EmployeeCell, headThStyle } from './components';
import { ShiftPicker } from './shift-picker';
import type {
    RosterEmployee,
    RosterSchedule,
    RosterShift,
    RosterWeekDay,
} from './types';

interface ShiftCellProps {
    shifts: RosterShift[];
    schedule: RosterSchedule | undefined;
    shift: RosterShift | undefined;
    color: string;
    isOpen: boolean;
    colorForShift: (shiftId: number) => string;
    onToggle: () => void;
    onSelect: (shiftId: number) => void;
    onRemove: () => void;
}

/** A single employee/day cell: the shift chip (or add button) and its picker. */
function ShiftCell({
    shifts,
    schedule,
    shift,
    color,
    isOpen,
    colorForShift,
    onToggle,
    onSelect,
    onRemove,
}: ShiftCellProps) {
    return (
        <td
            style={{
                padding: '8px 10px',
                textAlign: 'center',
                borderLeft: `1px solid ${C.line}`,
                verticalAlign: 'middle',
            }}
        >
            <div
                style={{
                    position: 'relative',
                    display: 'inline-block',
                    zIndex: isOpen ? 50 : undefined,
                }}
            >
                <button
                    onClick={onToggle}
                    style={
                        schedule && shift
                            ? {
                                  minWidth: 64,
                                  padding: '6px 10px',
                                  borderRadius: 7,
                                  border: 'none',
                                  background: color + '1a',
                                  color,
                                  fontSize: 12,
                                  fontWeight: 600,
                                  cursor: 'pointer',
                                  transition: '.15s',
                              }
                            : {
                                  width: 36,
                                  height: 36,
                                  borderRadius: 7,
                                  border: `1px dashed ${C.border}`,
                                  background: '#fff',
                                  color: C.faint,
                                  cursor: 'pointer',
                                  display: 'inline-flex',
                                  alignItems: 'center',
                                  justifyContent: 'center',
                                  transition: '.15s',
                              }
                    }
                >
                    {schedule && shift ? (
                        shift.code ?? shift.name
                    ) : (
                        <AIcon name="plus" size={15} color={C.faint} />
                    )}
                </button>

                {isOpen && (
                    <ShiftPicker
                        shifts={shifts}
                        schedule={schedule}
                        colorForShift={colorForShift}
                        onSelect={onSelect}
                        onRemove={onRemove}
                    />
                )}
            </div>
        </td>
    );
}

interface RosterGridProps {
    employees: RosterEmployee[];
    shifts: RosterShift[];
    week: RosterWeekDay[];
    openCell: string | null;
    setOpenCell: Dispatch<SetStateAction<string | null>>;
    scheduleFor: (employeeId: number, date: string) => RosterSchedule | undefined;
    shiftFor: (shiftId: number) => RosterShift | undefined;
    colorForShift: (shiftId: number) => string;
    onAssign: (employeeId: number, date: string, shiftId: number) => void;
    onRemove: (scheduleId: number) => void;
}

/** The weekly roster grid: employees as rows, week days as columns. */
export function RosterGrid({
    employees,
    shifts,
    week,
    openCell,
    setOpenCell,
    scheduleFor,
    shiftFor,
    colorForShift,
    onAssign,
    onRemove,
}: RosterGridProps) {
    return (
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
                            <tr style={{ borderTop: `1px solid ${C.line}` }}>
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
                                        <div>Belum ada karyawan aktif.</div>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {employees.map((employee) => (
                            <tr
                                key={employee.id}
                                style={{ borderTop: `1px solid ${C.line}` }}
                            >
                                <EmployeeCell employee={employee} />
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
                                        ? colorForShift(schedule.shift_id)
                                        : C.faint;

                                    return (
                                        <ShiftCell
                                            key={day.date}
                                            shifts={shifts}
                                            schedule={schedule}
                                            shift={shift}
                                            color={color}
                                            isOpen={isOpen}
                                            colorForShift={colorForShift}
                                            onToggle={() =>
                                                setOpenCell((prev) =>
                                                    prev === cellKey
                                                        ? null
                                                        : cellKey,
                                                )
                                            }
                                            onSelect={(shiftId) =>
                                                onAssign(
                                                    employee.id,
                                                    day.date,
                                                    shiftId,
                                                )
                                            }
                                            onRemove={() => {
                                                if (schedule) {
                                                    onRemove(schedule.id);
                                                }
                                            }}
                                        />
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
