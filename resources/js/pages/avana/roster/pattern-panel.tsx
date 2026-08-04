import { router } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import RosterController from '@/actions/App/Http/Controllers/Avana/RosterController';
import { DatePicker } from '@/components/avana/date-picker';
import { AIcon, C } from '@/lib/avana';
import type { RosterEmployee, RosterPatternOption } from './types';

const control: CSSProperties = {
    padding: '8px 12px',
    borderRadius: 9,
    border: `1px solid ${C.border}`,
    background: '#fff',
    fontSize: 12.5,
    color: C.navy,
};

interface PatternPanelProps {
    patterns: RosterPatternOption[];
    employees: RosterEmployee[];
    weekStart: string;
}

/** A date `days` after the given `Y-m-d`, as `Y-m-d`. */
function addDays(from: string, days: number): string {
    const date = new Date(`${from}T00:00:00`);
    date.setDate(date.getDate() + days);

    return date.toISOString().slice(0, 10);
}

/**
 * Fill the roster from a rotation rather than a cell at a time: pick the
 * pattern, who it applies to, and how long it runs for.
 */
export function PatternPanel({
    patterns,
    employees,
    weekStart,
}: PatternPanelProps) {
    const [patternId, setPatternId] = useState<number | ''>(
        patterns[0]?.id ?? '',
    );
    const [selected, setSelected] = useState<number[]>([]);
    const [startDate, setStartDate] = useState(weekStart);
    const [weeks, setWeeks] = useState(4);
    const [open, setOpen] = useState(false);

    const pattern = patterns.find((p) => p.id === patternId);
    const everyone = selected.length === 0;

    const toggle = (employeeId: number) => {
        setSelected((current) =>
            current.includes(employeeId)
                ? current.filter((id) => id !== employeeId)
                : [...current, employeeId],
        );
    };

    const apply = () => {
        if (patternId === '') {
            return;
        }

        router.post(
            RosterController.applyPattern().url,
            {
                pattern_id: patternId,
                employee_ids: everyone
                    ? employees.map((employee) => employee.id)
                    : selected,
                start_date: startDate,
                end_date: addDays(startDate, weeks * 7 - 1),
            },
            { preserveScroll: true, preserveState: false },
        );
    };

    if (patterns.length === 0) {
        return null;
    }

    return (
        <div
            style={{
                marginBottom: 16,
                padding: '12px 14px',
                borderRadius: 12,
                background: 'rgba(22,163,74,.05)',
                border: `1px solid ${C.border}`,
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    flexWrap: 'wrap',
                    gap: 10,
                }}
            >
                <span
                    style={{
                        fontSize: 12.5,
                        fontWeight: 600,
                        color: C.navy,
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 6,
                    }}
                >
                    <AIcon name="repeat" size={14} color={C.green} />
                    Pola rotasi
                </span>

                <select
                    value={patternId}
                    onChange={(event) =>
                        setPatternId(
                            event.target.value === ''
                                ? ''
                                : Number(event.target.value),
                        )
                    }
                    style={{ ...control, cursor: 'pointer' }}
                >
                    {patterns.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                            {option.industry ? ` · ${option.industry}` : ''}
                        </option>
                    ))}
                </select>

                <DatePicker
                    value={startDate}
                    onChange={setStartDate}
                    placeholder="Tanggal mulai"
                />

                <select
                    value={weeks}
                    onChange={(event) => setWeeks(Number(event.target.value))}
                    style={{ ...control, cursor: 'pointer' }}
                >
                    {[1, 2, 4, 8, 12, 26, 52].map((count) => (
                        <option key={count} value={count}>
                            {count} minggu
                        </option>
                    ))}
                </select>

                <button
                    type="button"
                    onClick={() => setOpen((current) => !current)}
                    style={{
                        ...control,
                        cursor: 'pointer',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 6,
                    }}
                >
                    <AIcon name="users" size={14} />
                    {everyone
                        ? 'Semua karyawan'
                        : `${selected.length} karyawan`}
                </button>

                <button
                    type="button"
                    onClick={apply}
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 6,
                        padding: '8px 14px',
                        borderRadius: 9,
                        border: 'none',
                        background: C.green,
                        color: '#fff',
                        fontSize: 12.5,
                        fontWeight: 600,
                        cursor: 'pointer',
                    }}
                >
                    <AIcon name="check" size={14} color="#fff" />
                    Terapkan pola
                </button>
            </div>

            {pattern && (
                <div
                    style={{
                        marginTop: 8,
                        fontSize: 12,
                        color: C.muted,
                    }}
                >
                    Siklus{' '}
                    <strong style={{ color: C.navy }}>{pattern.summary}</strong>{' '}
                    · {pattern.cycle_days} hari, diulang sampai{' '}
                    {addDays(startDate, weeks * 7 - 1)}. Tanggal yang sudah
                    terisi akan ditimpa.
                </div>
            )}

            {open && (
                <div
                    style={{
                        marginTop: 10,
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: 6,
                    }}
                >
                    {employees.map((employee) => {
                        const on = selected.includes(employee.id);

                        return (
                            <button
                                key={employee.id}
                                type="button"
                                onClick={() => toggle(employee.id)}
                                style={{
                                    padding: '6px 11px',
                                    borderRadius: 100,
                                    fontSize: 12,
                                    cursor: 'pointer',
                                    color: on ? '#fff' : C.muted,
                                    background: on ? C.primary : '#fff',
                                    border: `1px solid ${on ? C.primary : C.border}`,
                                }}
                            >
                                {employee.name}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
