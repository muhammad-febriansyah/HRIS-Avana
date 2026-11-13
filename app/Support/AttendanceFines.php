<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\AttendancePenalty;
use App\Models\AttendancePenaltyRule;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;

/**
 * Turns attendance violations into penalty rows under the tenant's late-fine
 * table (Sanksi Absensi).
 *
 * Shared between the screen's "Buat dari Absensi" button and the payroll run
 * itself: once a tenant writes its tiers, every run applies them to every
 * employee's attendance window automatically — the master is company policy,
 * not something HR re-applies month after month by hand.
 *
 * Idempotent through the (tenant, employee, date, violation) natural key, so
 * the button and the run never double-fine the same morning.
 */
class AttendanceFines
{
    /** Note prefix marking a penalty this class wrote, not one HR typed in. */
    public const AUTO_NOTE_PREFIX = 'Otomatis dari absensi';

    /** A penalty that counts — on the Sanksi list and in payroll. */
    public const STATUS_ACTIVE = 'active';

    /**
     * An automatic penalty HR cancelled. Kept rather than deleted, so the next
     * generate — every payroll run calls one — does not write it again.
     */
    public const STATUS_VOID = 'void';

    /** @var array<int, string> */
    public const GENERATABLE_STATUSES = ['late', 'absent', 'incomplete'];

    /** @var array<string, string> */
    public const VIOLATION_NOTES = [
        'late' => 'Terlambat',
        'absent' => 'Tidak hadir (alpa)',
        'incomplete' => 'Absensi belum lengkap',
    ];

    /**
     * Create the missing penalty rows for violations in the date range,
     * optionally narrowed to one employee (the payroll run's per-employee
     * window). Returns how many rows were created and how many carry a fine.
     *
     * @return array{created: int, fined: int}
     */
    public static function generate(int $tenantId, string $startDate, string $endDate, ?int $employeeId = null): array
    {
        self::dropOverturnedPenalties($tenantId, $startDate, $endDate, $employeeId);

        $attendances = Attendance::query()
            ->forTenant($tenantId)
            ->whereIn('status', self::GENERATABLE_STATUSES)
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->get(['id', 'employee_id', 'date', 'status', 'late_minutes']);

        if ($attendances->isEmpty()) {
            return ['created' => 0, 'fined' => 0];
        }

        $tiers = AttendancePenaltyRule::tiersFor($tenantId);
        $lockedPeriods = PayrollPeriod::forTenant($tenantId)
            ->where('status', 'locked')
            ->get(['start_date', 'end_date']);

        $created = 0;
        $fined = 0;

        foreach ($attendances as $attendance) {
            $tier = $attendance->status === 'late'
                ? AttendancePenaltyRule::match($tiers, (int) $attendance->late_minutes)
                : null;

            $date = $attendance->date->format('Y-m-d');
            $terms = [
                'penalty_type' => $tier?->penalty_type ?? 'warning',
                'amount' => $tier?->amount ?? 0,
                'notes' => self::note($attendance, $tier),
            ];

            $penalty = AttendancePenalty::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $attendance->employee_id,
                    'date' => $date,
                    'violation_type' => $attendance->status,
                ],
                $terms + ['status' => self::STATUS_ACTIVE],
            );

            if ($penalty->wasRecentlyCreated) {
                $created++;

                if ((float) $penalty->amount > 0) {
                    $fined++;
                }

                continue;
            }

            // An automatic fine follows the record it came from: the tier HR
            // re-priced, or the late minutes a correction changed. A fine HR
            // cancelled stays cancelled, one typed in by hand stands, and one
            // inside a locked payroll period stays as it was paid.
            if ($penalty->status === self::STATUS_ACTIVE
                && self::isAutomatic($penalty)
                && ! self::inLockedPeriod($lockedPeriods, $date)) {
                $penalty->update($terms);
            }
        }

        return ['created' => $created, 'fined' => $fined];
    }

    /**
     * Remove the automatic Alpa or Terlambat penalty from a day whose status
     * has since changed.
     *
     * The nightly marker writes Alpa before anyone can object, and a late mark
     * can be corrected (or re-judged) afterwards. The penalty generated from
     * the old status would otherwise stay on the employee's record for a day
     * that no longer carries it. Only rows this class wrote are touched — a
     * penalty HR typed in by hand stands.
     */
    private static function dropOverturnedPenalties(int $tenantId, string $startDate, string $endDate, ?int $employeeId): void
    {
        AttendancePenalty::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('violation_type', ['absent', 'late'])
            ->where('notes', 'like', self::AUTO_NOTE_PREFIX.'%')
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('attendances')
                    ->whereColumn('attendances.tenant_id', 'attendance_penalties.tenant_id')
                    ->whereColumn('attendances.employee_id', 'attendance_penalties.employee_id')
                    // date() on both sides: SQLite keeps a cast date with its
                    // midnight time, MySQL keeps the bare date.
                    ->whereRaw('date(attendances.date) = date(attendance_penalties.date)')
                    ->whereColumn('attendances.status', 'attendance_penalties.violation_type');
            })
            ->delete();
    }

    /**
     * Whether the penalty was written by this class rather than by hand.
     */
    public static function isAutomatic(AttendancePenalty $penalty): bool
    {
        return str_starts_with((string) $penalty->notes, self::AUTO_NOTE_PREFIX);
    }

    /**
     * Whether the date falls in one of the tenant's locked payroll periods.
     *
     * @param  Collection<int, PayrollPeriod>  $lockedPeriods
     */
    private static function inLockedPeriod(Collection $lockedPeriods, string $date): bool
    {
        return $lockedPeriods->contains(fn (PayrollPeriod $period): bool => $period->start_date !== null
            && $period->end_date !== null
            && $period->start_date->toDateString() <= $date
            && $period->end_date->toDateString() >= $date);
    }

    /**
     * The human note an auto-generated penalty carries, naming the violation
     * and — when a tier fined it — the band that decided the amount.
     */
    public static function note(Attendance $attendance, ?AttendancePenaltyRule $tier = null): string
    {
        $label = self::VIOLATION_NOTES[$attendance->status] ?? $attendance->status;

        if ($attendance->status === 'late' && (int) $attendance->late_minutes > 0) {
            $label .= ' '.(int) $attendance->late_minutes.' menit';
        }

        if ($tier !== null) {
            $label .= sprintf(
                ' (aturan %d–%s menit)',
                $tier->min_minutes,
                $tier->max_minutes !== null ? (string) $tier->max_minutes : 'seterusnya',
            );
        }

        return self::AUTO_NOTE_PREFIX.': '.$label;
    }
}
