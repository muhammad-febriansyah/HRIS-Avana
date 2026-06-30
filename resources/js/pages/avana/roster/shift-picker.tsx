import { AIcon, C } from '@/lib/avana';
import type { RosterSchedule, RosterShift } from './types';

interface ShiftPickerProps {
    shifts: RosterShift[];
    schedule: RosterSchedule | undefined;
    colorForShift: (shiftId: number) => string;
    onSelect: (shiftId: number) => void;
    onRemove: () => void;
}

/**
 * Popover anchored under a roster cell. Lists the available shifts (with the
 * current assignment highlighted) and, when a shift is already assigned,
 * offers an inline delete action.
 */
export function ShiftPicker({
    shifts,
    schedule,
    colorForShift,
    onSelect,
    onRemove,
}: ShiftPickerProps) {
    return (
        <div
            style={{
                position: 'absolute',
                top: 42,
                left: '50%',
                transform: 'translateX(-50%)',
                width: 188,
                background: '#fff',
                border: `1px solid ${C.border}`,
                borderRadius: 10,
                boxShadow: '0 8px 24px rgba(15,23,42,.14)',
                padding: 6,
                textAlign: 'left',
            }}
        >
            <div
                style={{
                    fontSize: 11,
                    fontWeight: 600,
                    color: C.faint,
                    textTransform: 'uppercase',
                    padding: '4px 8px 6px',
                }}
            >
                Pilih Shift
            </div>
            {shifts.map((option) => {
                const optionColor = colorForShift(option.id);
                const selected = schedule?.shift_id === option.id;

                return (
                    <button
                        key={option.id}
                        onClick={() => onSelect(option.id)}
                        style={{
                            width: '100%',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 9,
                            padding: '8px 9px',
                            border: 'none',
                            background: selected ? C.surface : 'none',
                            borderRadius: 7,
                            fontSize: 12.5,
                            color: C.text,
                            cursor: 'pointer',
                            transition: '.12s',
                        }}
                    >
                        <span
                            style={{
                                width: 10,
                                height: 10,
                                borderRadius: 3,
                                flex: 'none',
                                background: optionColor,
                            }}
                        />
                        <span
                            style={{
                                flex: 1,
                                fontWeight: 500,
                            }}
                        >
                            {option.name}
                        </span>
                        {selected && (
                            <AIcon name="check" size={14} color={C.primary} />
                        )}
                    </button>
                );
            })}
            {schedule && (
                <>
                    <div
                        style={{
                            height: 1,
                            background: C.line,
                            margin: '5px 4px',
                        }}
                    />
                    <button
                        onClick={onRemove}
                        style={{
                            width: '100%',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 9,
                            padding: '8px 9px',
                            border: 'none',
                            background: 'none',
                            borderRadius: 7,
                            fontSize: 12.5,
                            fontWeight: 500,
                            color: C.red,
                            cursor: 'pointer',
                            transition: '.12s',
                        }}
                    >
                        <AIcon name="trash-2" size={14} color={C.red} />
                        Hapus
                    </button>
                </>
            )}
        </div>
    );
}
