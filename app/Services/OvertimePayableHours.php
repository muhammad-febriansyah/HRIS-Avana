<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\OvertimeRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How many overtime hours payroll actually pays for.
 *
 * An approved overtime request says how long somebody expected to stay; it is
 * not evidence that they did. Paying the requested figure means a request filed
 * for four hours is paid in full even when the clock-out says the employee left
 * after one — the approval is a plan, attendance is the record.
 *
 * The payable figure is therefore the smaller of the two:
 *
 *     payable = min(approved hours, hours actually worked past the shift's end)
 *
 * Actual hours are measured from the later of the shift's end time and the
 * request's own start time — overtime cannot start before the work day is over,
 * and where no shift is known the request's start time is the only boundary on
 * record. With no attendance row, or a day the employee never clocked out of,
 * nothing is payable: there is no evidence of the hours, and the correction path
 * is the attendance correction, which a re-run then picks up.
 */
final class OvertimePayableHours
{
    /** Nothing is payable and why, for each case the rule recognises. */
    public const BASIS_NO_ATTENDANCE = 'no_attendance';

    public const BASIS_NO_CLOCK_OUT = 'no_clock_out';

    /** Left at or before the boundary: the overtime never happened. */
    public const BASIS_NO_OVERTIME_WORKED = 'no_overtime_worked';

    /** Clocked out earlier than requested: attendance is the smaller figure. */
    public const BASIS_ACTUAL = 'actual';

    /** Stayed at least as long as approved: the approval caps the payment. */
    public const BASIS_APPROVED = 'approved';

    /**
     * Resolve payable hours for every approved record and record the working on
     * each one, so a payslip line can be explained later without re-deriving it.
     *
     * @param  Collection<int, OvertimeRequest>  $records
     * @return Collection<int, OvertimeRequest> the same records, with
     *                                          actual_hours/payable_hours/payable_basis filled in
     */
    public function verify(int $tenantId, int $employeeId, Collection $records): Collection
    {
        if ($records->isEmpty()) {
            return $records;
        }

        $dates = $records
            ->map(fn (OvertimeRequest $record): ?string => $record->date?->toDateString())
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Matched on the date range and keyed in PHP: `date` is a date cast, and
        // a stored "2026-08-15 00:00:00" never equals a whereIn('2026-08-15').
        /** @var Collection<string, Attendance> $attendances */
        $attendances = Attendance::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [min($dates), Carbon::parse(max($dates))->addDay()->toDateString()])
            ->with('shift:id,start_time,end_time')
            ->get()
            ->keyBy(fn (Attendance $attendance): string => $attendance->date->toDateString());

        foreach ($records as $record) {
            [$actual, $payable, $basis] = $this->resolve(
                $record,
                $attendances->get($record->date?->toDateString() ?? ''),
            );

            $record->actual_hours = $actual;
            $record->payable_hours = $payable;
            $record->payable_basis = $basis;
            $record->hours_verified_at = now();

            // saveQuietly: this is payroll reading attendance, not somebody
            // editing the request — it must not look like an approval change.
            $record->saveQuietly();
        }

        return $records;
    }

    /**
     * The audit trail of one verification pass, for the payroll snapshot.
     *
     * @param  Collection<int, OvertimeRequest>  $records
     * @return list<array<string, mixed>>
     */
    public function audit(Collection $records): array
    {
        return $records
            ->map(fn (OvertimeRequest $record): array => [
                'overtime_request_id' => $record->id,
                'date' => $record->date?->toDateString(),
                'day_type' => $record->day_type,
                'requested_hours' => (float) $record->hours,
                'actual_hours' => $record->actual_hours === null ? null : (float) $record->actual_hours,
                'payable_hours' => (float) ($record->payable_hours ?? 0),
                'basis' => $record->payable_basis,
            ])
            ->values()
            ->all();
    }

    /**
     * Payable hours across records already run through verify().
     *
     * @param  Collection<int, OvertimeRequest>  $records
     */
    public function payableTotal(Collection $records): float
    {
        return (float) $records->sum(fn (OvertimeRequest $record): float => (float) ($record->payable_hours ?? 0));
    }

    /**
     * @return array{0: float|null, 1: float, 2: string} actual, payable, basis
     */
    private function resolve(OvertimeRequest $record, ?Attendance $attendance): array
    {
        $requested = max(0.0, (float) $record->hours);

        if ($attendance === null) {
            return [null, 0.0, self::BASIS_NO_ATTENDANCE];
        }

        if ($attendance->clock_out_at === null) {
            return [null, 0.0, self::BASIS_NO_CLOCK_OUT];
        }

        $boundary = $this->boundary($record, $attendance);

        if ($boundary === null) {
            // Nothing says when the work day ended, so the clock-out cannot be
            // split into ordinary and overtime hours. The request stands as the
            // only figure on record.
            return [null, $requested, self::BASIS_APPROVED];
        }

        $actualMinutes = $boundary->diffInMinutes($attendance->clock_out_at, false);
        $actual = round(max(0.0, $actualMinutes / 60), 2);

        if ($actual <= 0.0) {
            return [0.0, 0.0, self::BASIS_NO_OVERTIME_WORKED];
        }

        return $actual < $requested
            ? [$actual, $actual, self::BASIS_ACTUAL]
            : [$actual, $requested, self::BASIS_APPROVED];
    }

    /**
     * When overtime may start on this day: the later of the shift's end and the
     * request's own start time. A shift ending before it starts is an overnight
     * shift, so its end belongs to the following day.
     */
    private function boundary(OvertimeRequest $record, Attendance $attendance): ?Carbon
    {
        $date = $attendance->date->toDateString();
        $candidates = [];

        $shiftEnd = $attendance->shift?->end_time;

        if (is_string($shiftEnd) && $shiftEnd !== '') {
            $end = Carbon::parse($date.' '.$shiftEnd);
            $shiftStart = $attendance->shift?->start_time;

            if (is_string($shiftStart) && $shiftStart !== '' && $end->lessThanOrEqualTo(Carbon::parse($date.' '.$shiftStart))) {
                $end->addDay();
            }

            $candidates[] = $end;
        }

        if (is_string($record->start_time) && $record->start_time !== '') {
            $candidates[] = Carbon::parse($date.' '.$record->start_time);
        }

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)->sortDesc()->first();
    }
}
