import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { CSSProperties } from 'react';
import { toast } from 'sonner';
import RosterController from '@/actions/App/Http/Controllers/Avana/RosterController';
import { AIcon, C } from '@/lib/avana';
import { ShiftLegend, WeekNavigator } from './components';
import { RosterGrid } from './roster-grid';
import { SHIFT_PALETTE, toIso } from './types';
import type {
    FlashProps,
    RosterProps,
    RosterSchedule,
    RosterShift,
} from './types';

const selectStyle: CSSProperties = {
    padding: '8px 12px',
    borderRadius: 9,
    border: `1px solid ${C.border}`,
    background: '#fff',
    fontSize: 12.5,
    color: C.navy,
    cursor: 'pointer',
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
            toast.success(flash.success, { id: flash.success });
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
        shiftId: number | null,
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

    const [bulkShift, setBulkShift] = useState<number | ''>(
        shifts[0]?.id ?? '',
    );
    const [preset, setPreset] = useState<'weekdays' | 'sixdays' | 'all'>(
        'weekdays',
    );

    const datesForPreset = (): string[] => {
        const allow =
            preset === 'weekdays'
                ? ['Sen', 'Sel', 'Rab', 'Kam', 'Jum']
                : preset === 'sixdays'
                  ? ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']
                  : ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

        return week.filter((d) => allow.includes(d.dow)).map((d) => d.date);
    };

    const applyBulk = () => {
        if (bulkShift === '') {
            return;
        }

        const dates = datesForPreset();

        if (dates.length === 0) {
            return;
        }

        router.post(
            RosterController.bulkStore().url,
            { shift_id: bulkShift, dates },
            { preserveScroll: true, preserveState: false },
        );
    };

    const copyWeek = () => {
        router.post(
            RosterController.copyPreviousWeek().url,
            { week_start },
            { preserveScroll: true, preserveState: false },
        );
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
                    <WeekNavigator
                        rangeLabel={rangeLabel}
                        onPrev={() => shiftWeek(-7)}
                        onNext={() => shiftWeek(7)}
                        onToday={goToday}
                    />
                    <ShiftLegend
                        shifts={shifts}
                        colorForShift={colorForShift}
                    />
                </div>

                {/* Quick-fill toolbar — assign one shift to every employee for
                    the visible week in a single click (built for large teams). */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        flexWrap: 'wrap',
                        gap: 10,
                        padding: '12px 14px',
                        marginBottom: 16,
                        borderRadius: 12,
                        background: 'rgba(47,84,201,.05)',
                        border: `1px solid ${C.border}`,
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
                        <AIcon name="zap" size={14} color={C.primary} />
                        Isi cepat
                    </span>

                    <select
                        value={bulkShift}
                        onChange={(e) =>
                            setBulkShift(
                                e.target.value === ''
                                    ? ''
                                    : Number(e.target.value),
                            )
                        }
                        style={selectStyle}
                    >
                        {shifts.length === 0 && (
                            <option value="">Belum ada shift</option>
                        )}
                        {shifts.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name} ({s.start_time}–{s.end_time})
                            </option>
                        ))}
                    </select>

                    <select
                        value={preset}
                        onChange={(e) =>
                            setPreset(
                                e.target.value as
                                    'weekdays' | 'sixdays' | 'all',
                            )
                        }
                        style={selectStyle}
                    >
                        <option value="weekdays">Senin–Jumat</option>
                        <option value="sixdays">Senin–Sabtu</option>
                        <option value="all">Setiap hari</option>
                    </select>

                    <button
                        onClick={applyBulk}
                        disabled={bulkShift === ''}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            padding: '8px 14px',
                            borderRadius: 9,
                            border: 'none',
                            background: bulkShift === '' ? C.faint : C.primary,
                            color: '#fff',
                            fontSize: 12.5,
                            fontWeight: 600,
                            cursor: bulkShift === '' ? 'default' : 'pointer',
                        }}
                    >
                        <AIcon name="users" size={14} color="#fff" />
                        Terapkan ke semua karyawan
                    </button>

                    <div style={{ flex: 1 }} />

                    <button
                        onClick={copyWeek}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            padding: '8px 14px',
                            borderRadius: 9,
                            border: `1px solid ${C.border}`,
                            background: '#fff',
                            color: C.navy,
                            fontSize: 12.5,
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon name="copy" size={14} color={C.muted} />
                        Salin minggu lalu
                    </button>
                </div>

                {/* Roster grid */}
                <RosterGrid
                    employees={employees}
                    shifts={shifts}
                    week={week}
                    openCell={openCell}
                    setOpenCell={setOpenCell}
                    scheduleFor={scheduleFor}
                    shiftFor={shiftFor}
                    colorForShift={colorForShift}
                    onAssign={assignShift}
                    onRemove={removeSchedule}
                />
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
