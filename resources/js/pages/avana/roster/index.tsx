import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
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
                    <WeekNavigator
                        rangeLabel={rangeLabel}
                        onPrev={() => shiftWeek(-7)}
                        onNext={() => shiftWeek(7)}
                        onToday={goToday}
                    />
                    <ShiftLegend shifts={shifts} colorForShift={colorForShift} />
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
