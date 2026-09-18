import { useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import type { CSSProperties, FormEvent } from 'react';
import RosterPlanController from '@/actions/App/Http/Controllers/Avana/RosterPlanController';
import { AIcon } from '@/lib/avana';
import type {
    RosterEmployee,
    RosterPlan,
    RosterPlanCategory,
    RosterPlanDay,
    RosterWeekDay,
} from './types';

interface RosterPlanFormData {
    employee_id: number;
    name: string;
    shift_label: string;
    period_start: string;
    period_end: string;
    days: RosterPlanDay[];
}

interface RosterPlanModalProps {
    employee: RosterEmployee;
    plan?: RosterPlan;
    week: RosterWeekDay[];
    currentShiftLabel: string;
    onClose: () => void;
}

const inputStyle: CSSProperties = {
    width: '100%',
    boxSizing: 'border-box',
    padding: '7px 9px',
    border: '1px solid #d6dbe3',
    borderRadius: 5,
    background: '#fff',
    color: '#354052',
    fontSize: 12,
};

const selectStyle: CSSProperties = {
    ...inputStyle,
    cursor: 'pointer',
};

function dayTemplate(date: string): RosterPlanDay {
    return {
        date,
        configured: false,
        type: 'jadwal',
        category: 'wfo',
        boundary_start: null,
        boundary_end: null,
        schedule_start: null,
        schedule_end: null,
        break_start: null,
        break_end: null,
        notes: null,
    };
}

function toIso(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function dayName(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString('id-ID', {
        weekday: 'long',
    });
}

function timeValue(day: RosterPlanDay, field: keyof RosterPlanDay): string {
    return (day[field] as string | null | undefined) ?? '';
}

export function RosterPlanModal({
    employee,
    plan,
    week,
    currentShiftLabel,
    onClose,
}: RosterPlanModalProps) {
    const initialDays = useMemo(() => {
        const persisted = new Map(
            (plan?.days ?? []).map((day) => [day.date, day]),
        );
        const dates = [
            ...week.map((day) => day.date),
            ...(plan?.days ?? [])
                .map((day) => day.date)
                .filter((date) => !week.some((day) => day.date === date)),
        ];

        return dates.map((date) => {
            const saved = persisted.get(date);

            return saved ? { ...saved, configured: true } : dayTemplate(date);
        });
    }, [plan, week]);

    const form = useForm<RosterPlanFormData>({
        employee_id: employee.id,
        name: plan?.name ?? `Plan roster ${employee.name}`,
        shift_label: plan?.shift_label ?? currentShiftLabel,
        period_start: plan?.period_start ?? week[0]?.date ?? '',
        period_end: plan?.period_end ?? week[week.length - 1]?.date ?? '',
        days: initialDays,
    });

    const updateDay = (index: number, patch: Partial<RosterPlanDay>) => {
        form.setData(
            'days',
            form.data.days.map((day, dayIndex) =>
                dayIndex === index ? { ...day, ...patch } : day,
            ),
        );
    };

    const activateDay = (index: number) => {
        updateDay(index, { configured: true });
    };

    const deactivateDay = (index: number) => {
        updateDay(index, {
            ...dayTemplate(form.data.days[index].date),
            configured: false,
        });
    };

    const addDay = () => {
        const lastDate = form.data.days.at(-1)?.date ?? form.data.period_end;
        const nextDate = new Date(`${lastDate}T00:00:00`);
        nextDate.setDate(nextDate.getDate() + 1);
        const nextIso = toIso(nextDate);

        form.setData('days', [...form.data.days, dayTemplate(nextIso)]);
        form.setData('period_end', nextIso);
    };

    const setType = (index: number, type: RosterPlanDay['type']) => {
        updateDay(
            index,
            type === 'libur'
                ? {
                      type,
                      category: null,
                      boundary_start: null,
                      boundary_end: null,
                      schedule_start: null,
                      schedule_end: null,
                      break_start: null,
                      break_end: null,
                  }
                : { type, category: 'wfo' },
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            days: data.days
                .filter((day) => day.configured)
                .map((day) => ({
                    date: day.date,
                    type: day.type,
                    category: day.category,
                    boundary_start: day.boundary_start,
                    boundary_end: day.boundary_end,
                    schedule_start: day.schedule_start,
                    schedule_end: day.schedule_end,
                    break_start: day.break_start,
                    break_end: day.break_end,
                    notes: day.notes,
                })),
        }));
        form.submit(RosterPlanController.store(), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const errorFor = (key: string): string | undefined =>
        (form.errors as unknown as Record<string, string | undefined>)[key];

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="roster-plan-title"
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 100,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 22,
                background: 'rgba(15, 23, 42, .52)',
            }}
        >
            <div
                style={{
                    width: 'min(1280px, 100%)',
                    maxHeight: 'calc(100vh - 44px)',
                    overflow: 'hidden',
                    borderRadius: 4,
                    background: '#fff',
                    boxShadow: '0 18px 54px rgba(15, 23, 42, .28)',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        padding: '12px 18px',
                        borderBottom: '1px solid #e5e7eb',
                    }}
                >
                    <div
                        id="roster-plan-title"
                        style={{
                            color: '#737b87',
                            fontSize: 16,
                            fontWeight: 500,
                            letterSpacing: '.01em',
                        }}
                    >
                        ROSTER
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Tutup detail roster"
                        style={{
                            border: 0,
                            background: 'transparent',
                            color: '#a5abb4',
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon name="x" size={18} />
                    </button>
                </div>

                <form onSubmit={submit}>
                    <div
                        style={{
                            maxHeight: 'calc(100vh - 124px)',
                            overflow: 'auto',
                            padding: '18px 20px 24px',
                        }}
                    >
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 0,
                                marginBottom: 20,
                                border: '1px solid #e5e7eb',
                                borderRadius: 4,
                                overflow: 'hidden',
                            }}
                        >
                            <SummaryItem label="Nama" value={employee.name} />
                            <SummaryItem
                                label="NIP"
                                value={employee.employee_number}
                            />
                            <SummaryItem
                                label="Shift"
                                value={form.data.shift_label || '-'}
                                fullWidth
                            />
                        </div>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    'minmax(230px, 1fr) 170px 170px auto',
                                alignItems: 'end',
                                gap: 10,
                                marginBottom: 14,
                            }}
                        >
                            <label style={{ color: '#677181', fontSize: 11 }}>
                                Nama plan
                                <input
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    style={{ ...inputStyle, marginTop: 4 }}
                                />
                            </label>
                            <label style={{ color: '#677181', fontSize: 11 }}>
                                Periode mulai
                                <input
                                    type="date"
                                    value={form.data.period_start}
                                    onChange={(event) =>
                                        form.setData(
                                            'period_start',
                                            event.target.value,
                                        )
                                    }
                                    style={{ ...inputStyle, marginTop: 4 }}
                                />
                            </label>
                            <label style={{ color: '#677181', fontSize: 11 }}>
                                Periode selesai
                                <input
                                    type="date"
                                    value={form.data.period_end}
                                    onChange={(event) =>
                                        form.setData(
                                            'period_end',
                                            event.target.value,
                                        )
                                    }
                                    style={{ ...inputStyle, marginTop: 4 }}
                                />
                            </label>
                            <button
                                type="button"
                                onClick={addDay}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 5,
                                    height: 32,
                                    padding: '0 12px',
                                    border: '1px solid #d7dce3',
                                    borderRadius: 4,
                                    background: '#fff',
                                    color: '#66707d',
                                    fontSize: 11,
                                    cursor: 'pointer',
                                }}
                            >
                                <AIcon name="plus" size={13} />
                                Tambah tanggal
                            </button>
                        </div>

                        <div
                            style={{
                                overflowX: 'auto',
                                border: '1px solid #dfe3e8',
                                borderRadius: 2,
                            }}
                        >
                            <table
                                style={{
                                    width: '100%',
                                    minWidth: 1180,
                                    borderCollapse: 'collapse',
                                    tableLayout: 'fixed',
                                }}
                            >
                                <thead>
                                    <tr style={{ background: '#a5a5a5' }}>
                                        <HeaderCell
                                            label="Tanggal"
                                            rowSpan={2}
                                            width="11%"
                                        />
                                        <HeaderCell
                                            label="Hari"
                                            rowSpan={2}
                                            width="8%"
                                        />
                                        <HeaderCell
                                            label="Tipe"
                                            rowSpan={2}
                                            width="12%"
                                        />
                                        <HeaderCell
                                            label="Kategori"
                                            rowSpan={2}
                                            width="12%"
                                        />
                                        <HeaderCell
                                            label="Batas"
                                            colSpan={2}
                                            width="17%"
                                        />
                                        <HeaderCell
                                            label="Jadwal"
                                            colSpan={2}
                                            width="17%"
                                        />
                                        <HeaderCell
                                            label="Istirahat"
                                            colSpan={2}
                                            width="17%"
                                        />
                                        <HeaderCell
                                            label="Kontrol"
                                            rowSpan={2}
                                            width="6%"
                                        />
                                    </tr>
                                    <tr style={{ background: '#a5a5a5' }}>
                                        {[
                                            'Mulai',
                                            'Selesai',
                                            'Mulai',
                                            'Selesai',
                                            'Mulai',
                                            'Selesai',
                                        ].map((label, index) => (
                                            <th
                                                key={`${label}-${index}`}
                                                style={subHeaderStyle}
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {form.data.days.map((day, index) => {
                                        const isConfigured =
                                            day.configured === true;
                                        const isOff = day.type === 'libur';
                                        const dateError = errorFor(
                                            `days.${index}.date`,
                                        );

                                        return (
                                            <FragmentRows
                                                key={`${day.date}-${index}`}
                                                day={day}
                                                index={index}
                                                isConfigured={isConfigured}
                                                isOff={isOff}
                                                dateError={dateError}
                                                onActivate={() =>
                                                    activateDay(index)
                                                }
                                                onDeactivate={() =>
                                                    deactivateDay(index)
                                                }
                                                onDateChange={(date) =>
                                                    updateDay(index, { date })
                                                }
                                                onTypeChange={(type) =>
                                                    setType(index, type)
                                                }
                                                onCategoryChange={(category) =>
                                                    updateDay(index, {
                                                        category,
                                                    })
                                                }
                                                onTimeChange={(field, value) =>
                                                    updateDay(index, {
                                                        [field]: value || null,
                                                    })
                                                }
                                            />
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                marginTop: 10,
                                color: '#8b929d',
                                fontSize: 11,
                            }}
                        >
                            <span>
                                Klik <strong>+</strong> untuk mengisi jadwal
                                pada tanggal tersebut.
                            </span>
                            <span>
                                Draft ini belum diterapkan ke absensi atau
                                payroll.
                            </span>
                        </div>
                        {form.hasErrors && (
                            <div
                                style={{
                                    marginTop: 8,
                                    color: '#b42318',
                                    fontSize: 12,
                                }}
                            >
                                Periksa tanggal dan pasangan jam yang diisi.
                            </div>
                        )}
                    </div>

                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'flex-end',
                            gap: 10,
                            padding: '12px 20px',
                            borderTop: '1px solid #e5e7eb',
                            background: '#fafafa',
                        }}
                    >
                        <button
                            type="button"
                            onClick={onClose}
                            style={footerButtonStyle}
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            style={{
                                ...footerButtonStyle,
                                borderColor: '#4a9d52',
                                background: '#4a9d52',
                                color: '#fff',
                                minWidth: 170,
                                cursor: form.processing ? 'wait' : 'pointer',
                                opacity: form.processing ? 0.7 : 1,
                            }}
                        >
                            <AIcon name="check" size={14} color="#fff" />
                            {form.processing
                                ? 'Menyimpan...'
                                : 'Simpan Draft Plan'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

const subHeaderStyle: CSSProperties = {
    padding: '8px 5px',
    borderTop: '1px solid rgba(255,255,255,.32)',
    borderLeft: '1px solid rgba(255,255,255,.5)',
    color: '#fff',
    fontSize: 10,
    fontWeight: 700,
    textAlign: 'center',
    textTransform: 'uppercase',
};

const footerButtonStyle: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    height: 38,
    padding: '0 16px',
    border: '1px solid #d7dce3',
    borderRadius: 5,
    background: '#fff',
    color: '#626b78',
    fontSize: 12,
    fontWeight: 700,
};

function HeaderCell({
    label,
    rowSpan,
    colSpan,
    width,
}: {
    label: string;
    rowSpan?: number;
    colSpan?: number;
    width: string;
}) {
    return (
        <th
            rowSpan={rowSpan}
            colSpan={colSpan}
            style={{
                width,
                padding: '9px 5px',
                borderLeft: '1px solid rgba(255,255,255,.5)',
                color: '#fff',
                fontSize: 10,
                fontWeight: 700,
                textAlign: 'center',
                textTransform: 'uppercase',
            }}
        >
            {label}
        </th>
    );
}

function SummaryItem({
    label,
    value,
    fullWidth = false,
}: {
    label: string;
    value: string;
    fullWidth?: boolean;
}) {
    return (
        <div
            style={{
                gridColumn: fullWidth ? '1 / -1' : undefined,
                display: 'grid',
                gridTemplateColumns: '120px 1fr',
                minHeight: 40,
                borderBottom: fullWidth ? 0 : '1px solid #edf0f2',
                borderRight: fullWidth ? 0 : '1px solid #edf0f2',
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'flex-end',
                    padding: '0 12px',
                    background: '#f3f3f3',
                    color: '#7b8189',
                    fontSize: 11,
                    fontWeight: 600,
                }}
            >
                {label}
            </div>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    padding: '0 14px',
                    color: '#59616d',
                    fontSize: 12,
                }}
            >
                {value}
            </div>
        </div>
    );
}

interface FragmentRowsProps {
    day: RosterPlanDay;
    index: number;
    isConfigured: boolean;
    isOff: boolean;
    dateError?: string;
    onActivate: () => void;
    onDeactivate: () => void;
    onDateChange: (date: string) => void;
    onTypeChange: (type: RosterPlanDay['type']) => void;
    onCategoryChange: (category: RosterPlanCategory | null) => void;
    onTimeChange: (field: keyof RosterPlanDay, value: string) => void;
}

function FragmentRows({
    day,
    index,
    isConfigured,
    isOff,
    dateError,
    onActivate,
    onDeactivate,
    onDateChange,
    onTypeChange,
    onCategoryChange,
    onTimeChange,
}: FragmentRowsProps) {
    const timeGroups: [keyof RosterPlanDay, keyof RosterPlanDay][] = [
        ['boundary_start', 'boundary_end'],
        ['schedule_start', 'schedule_end'],
        ['break_start', 'break_end'],
    ];

    return (
        <>
            <tr style={{ background: '#fff', borderTop: '1px solid #e1e5e9' }}>
                <td style={dateCellStyle}>
                    <input
                        type="date"
                        value={day.date}
                        onChange={(event) => onDateChange(event.target.value)}
                        style={{ ...inputStyle, fontSize: 11 }}
                    />
                    {dateError && (
                        <div
                            style={{
                                color: '#b42318',
                                fontSize: 9,
                                marginTop: 2,
                            }}
                        >
                            {dateError}
                        </div>
                    )}
                </td>
                <td style={{ ...dateCellStyle, color: '#69717c' }}>
                    {dayName(day.date)}
                </td>
                <td colSpan={7} style={{ padding: 7 }} />
                <td style={{ padding: 6, textAlign: 'center' }}>
                    <button
                        type="button"
                        onClick={onActivate}
                        aria-label={`Tambah jadwal ${index + 1}`}
                        style={controlButtonStyle}
                    >
                        <AIcon name="plus" size={14} />
                    </button>
                </td>
            </tr>
            {isConfigured && (
                <tr style={{ background: '#fbfcfd' }}>
                    <td colSpan={2} style={{ padding: 7 }} />
                    <td style={{ padding: 7 }}>
                        <select
                            value={day.type}
                            onChange={(event) =>
                                onTypeChange(
                                    event.target.value as RosterPlanDay['type'],
                                )
                            }
                            style={selectStyle}
                        >
                            <option value="jadwal">Jadwal</option>
                            <option value="libur">Libur</option>
                        </select>
                    </td>
                    <td style={{ padding: 7 }}>
                        <select
                            value={day.category ?? ''}
                            disabled={isOff}
                            onChange={(event) =>
                                onCategoryChange(
                                    (event.target.value ||
                                        null) as RosterPlanCategory | null,
                                )
                            }
                            style={selectStyle}
                        >
                            <option value="">Pilih</option>
                            <option value="wfo">WFO/WFH</option>
                            <option value="wfo">WFO</option>
                            <option value="wfh">WFH</option>
                            <option value="wfa">WFA</option>
                        </select>
                    </td>
                    {timeGroups.map(([start, end]) => (
                        <td
                            key={String(start)}
                            colSpan={2}
                            style={{ padding: 7 }}
                        >
                            <div style={{ display: 'flex', gap: 5 }}>
                                <input
                                    type="time"
                                    disabled={isOff}
                                    value={timeValue(day, start)}
                                    onChange={(event) =>
                                        onTimeChange(start, event.target.value)
                                    }
                                    style={inputStyle}
                                />
                                <input
                                    type="time"
                                    disabled={isOff}
                                    value={timeValue(day, end)}
                                    onChange={(event) =>
                                        onTimeChange(end, event.target.value)
                                    }
                                    style={inputStyle}
                                />
                            </div>
                        </td>
                    ))}
                    <td style={{ padding: 7, textAlign: 'center' }}>
                        <button
                            type="button"
                            onClick={onDeactivate}
                            aria-label={`Hapus jadwal ${index + 1}`}
                            style={{ ...controlButtonStyle, color: '#c94b55' }}
                        >
                            <AIcon name="x" size={14} />
                        </button>
                    </td>
                </tr>
            )}
        </>
    );
}

const dateCellStyle: CSSProperties = {
    padding: 7,
    color: '#525b68',
    fontSize: 11,
    verticalAlign: 'middle',
};

const controlButtonStyle: CSSProperties = {
    width: 26,
    height: 26,
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    border: '1px solid #d7dce3',
    borderRadius: 3,
    background: '#eef1f5',
    color: '#7d8792',
    cursor: 'pointer',
};
