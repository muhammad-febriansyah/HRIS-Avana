<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\AttendancePenalty;
use App\Models\AttendancePenaltyRule;

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
            ->get(['id', 'employee_id', 'date', 'status', 'late_minutes']);

        if ($attendances->isEmpty()) {
            return ['created' => 0, 'fined' => 0];
        }

        $tiers = AttendancePenaltyRule::tiersFor($tenantId);

        $created = 0;
        $fined = 0;

        foreach ($attendances as $attendance) {
            $tier = $attendance->status === 'late'
                ? AttendancePenaltyRule::match($tiers, (int) $attendance->late_minutes)
                : null;

            $penalty = AttendancePenalty::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $attendance->employee_id,
                    'date' => $attendance->date->format('Y-m-d'),
                    'violation_type' => $attendance->status,
                ],
                [
                    'penalty_type' => $tier?->penalty_type ?? 'warning',
                    'amount' => $tier?->amount ?? 0,
                    'notes' => self::note($attendance, $tier),
                    'status' => 'active',
                ],
            );

            if ($penalty->wasRecentlyCreated) {
                $created++;

                if ((float) $penalty->amount > 0) {
                    $fined++;
                }
            }
        }

        return ['created' => $created, 'fined' => $fined];
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
