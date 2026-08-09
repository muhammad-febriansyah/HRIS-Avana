<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\AttendancePenalty;
use App\Models\AttendancePenaltyRule;
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
        $attendances = Attendance::query()
            ->forTenant($tenantId)
            ->whereIn('status', self::GENERATABLE_STATUSES)
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->get(['id', 'tenant_id', 'employee_id', 'date', 'status', 'late_minutes']);

        $tiers = AttendancePenaltyRule::tiersFor($tenantId);

        AttendancePenalty::query()
            ->forTenant($tenantId)
            ->where('source', 'automatic')
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->when(
                $attendances->isNotEmpty(),
                fn ($query) => $query->whereNotIn('attendance_id', $attendances->pluck('id')),
            )
            ->delete();

        if ($attendances->isEmpty()) {
            return ['created' => 0, 'fined' => 0];
        }

        $created = 0;
        $fined = 0;

        foreach ($attendances as $attendance) {
            $penalty = self::syncWithTiers($attendance, $tiers);

            if ($penalty?->wasRecentlyCreated) {
                $created++;

                if ((float) $penalty->amount > 0) {
                    $fined++;
                }
            }
        }

        return ['created' => $created, 'fined' => $fined];
    }

    /** Keep one automatic penalty aligned with its attendance fact. */
    public static function sync(Attendance $attendance): ?AttendancePenalty
    {
        return self::syncWithTiers(
            $attendance,
            AttendancePenaltyRule::tiersFor((int) $attendance->tenant_id),
        );
    }

    /** Re-price existing automatic penalties after the tenant edits its tiers. */
    public static function refreshAutomaticForTenant(int $tenantId): void
    {
        AttendancePenalty::query()
            ->forTenant($tenantId)
            ->where('source', 'automatic')
            ->with('attendance')
            ->orderBy('id')
            ->chunkById(200, function ($penalties): void {
                foreach ($penalties as $penalty) {
                    if ($penalty->attendance !== null) {
                        self::sync($penalty->attendance);
                    }
                }
            });
    }

    /**
     * @param  Collection<int, AttendancePenaltyRule>  $tiers
     */
    private static function syncWithTiers(Attendance $attendance, Collection $tiers): ?AttendancePenalty
    {
        $existing = AttendancePenalty::query()
            ->where('tenant_id', $attendance->tenant_id)
            ->where('attendance_id', $attendance->id)
            ->where('source', 'automatic')
            ->first();

        if (! in_array($attendance->status, self::GENERATABLE_STATUSES, true)) {
            $existing?->delete();

            return null;
        }

        $tier = $attendance->status === 'late'
            ? AttendancePenaltyRule::match($tiers, (int) $attendance->late_minutes)
            : null;

        $penalty = $existing ?? new AttendancePenalty;
        $penalty->fill([
            'tenant_id' => $attendance->tenant_id,
            'attendance_id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'date' => $attendance->date->format('Y-m-d'),
            'violation_type' => $attendance->status,
            'source' => 'automatic',
            'penalty_type' => $tier?->penalty_type ?? 'warning',
            'amount' => $tier?->amount ?? 0,
            'notes' => self::note($attendance, $tier),
            'status' => $existing?->status ?? 'active',
        ])->save();

        return $penalty;
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

        return 'Otomatis dari absensi: '.$label;
    }
}
