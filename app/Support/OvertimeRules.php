<?php

namespace App\Support;

use App\Models\OvertimePolicy;
use App\Models\OvertimeRate;
use App\Models\OvertimeRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The overtime rule set of PP 35/2021, read from tenant configuration.
 *
 * Three things decide what an overtime hour is worth: the basis it is computed
 * from (Gaji Pokok plus the allowances marked as overtime basis, floored at 75%
 * of monthly earnings), the divisor that turns that basis into an hourly wage
 * (1/173), and a multiplier that depends on the kind of day and on which hour
 * of the shift it is. All three are configurable per tenant; the seeded values
 * are the statutory ones.
 */
final class OvertimeRules
{
    /**
     * The kinds of day the multiplier table distinguishes.
     *
     * @var array<string, string>
     */
    public const DAY_TYPES = [
        'workday' => 'Hari Kerja',
        'holiday' => 'Hari Libur / Istirahat Mingguan',
    ];

    /**
     * Nobody works more overtime hours than this in one stretch; the loop that
     * walks the multiplier bands needs an end even if a record is malformed.
     */
    private const HOUR_CEILING = 24;

    /**
     * Policies and rate tables read once per request: a payroll run asks for
     * them once per employee, and they cannot change mid-run.
     *
     * @var array<int, OvertimePolicy>
     */
    private static array $policyCache = [];

    /**
     * @var array<int, array<string, list<array{from: int, to: int|null, multiplier: float}>>>
     */
    private static array $rateCache = [];

    /**
     * Drop the memoised configuration — for tests, and for a request that has
     * just edited the rules and wants to read them back.
     */
    public static function forget(): void
    {
        self::$policyCache = [];
        self::$rateCache = [];
    }

    /**
     * The tenant's policy, created with the statutory defaults when a tenant
     * has not been given one yet (a tenant added after the migration ran).
     */
    public static function policyFor(int $tenantId): OvertimePolicy
    {
        return self::$policyCache[$tenantId] ??= OvertimePolicy::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'max_hours_per_day' => 4,
                'max_hours_per_week' => 18,
                'hours_divisor' => 173,
                'rounding_minutes' => 0,
                'fixed_basis_min_ratio' => 0.75,
                'enforce_hour_limits' => true,
            ],
        );
    }

    /**
     * The tenant's multiplier table grouped by day type, each band ordered by
     * its first hour. Falls back to the statutory table when a tenant has no
     * rows, so overtime is never silently paid at zero.
     *
     * @return array<string, list<array{from: int, to: int|null, multiplier: float}>>
     */
    public static function ratesFor(int $tenantId): array
    {
        return self::$rateCache[$tenantId] ??= self::loadRates($tenantId);
    }

    /**
     * @return array<string, list<array{from: int, to: int|null, multiplier: float}>>
     */
    private static function loadRates(int $tenantId): array
    {
        $rows = OvertimeRate::forTenant($tenantId)
            ->orderBy('day_type')
            ->orderBy('hour_from')
            ->get();

        if ($rows->isEmpty()) {
            return self::statutoryRates();
        }

        $table = [];

        foreach ($rows as $row) {
            $table[$row->day_type][] = [
                'from' => (int) $row->hour_from,
                'to' => $row->hour_to !== null ? (int) $row->hour_to : null,
                'multiplier' => (float) $row->multiplier,
            ];
        }

        foreach (array_keys(self::DAY_TYPES) as $dayType) {
            if (empty($table[$dayType])) {
                $table[$dayType] = self::statutoryRates()[$dayType];
            }
        }

        return $table;
    }

    /**
     * Write the statutory table for a tenant that has none, so the Setup Lembur
     * screen shows the rows payroll is already applying rather than an empty
     * table that reads as "overtime pays nothing".
     */
    public static function ensureRatesFor(int $tenantId): void
    {
        if (OvertimeRate::forTenant($tenantId)->exists()) {
            return;
        }

        foreach (self::statutoryRates() as $dayType => $bands) {
            foreach ($bands as $band) {
                OvertimeRate::create([
                    'tenant_id' => $tenantId,
                    'day_type' => $dayType,
                    'hour_from' => $band['from'],
                    'hour_to' => $band['to'],
                    'multiplier' => $band['multiplier'],
                ]);
            }
        }

        self::forget();
    }

    /**
     * PP 35/2021 Pasal 31 as shipped.
     *
     * @return array<string, list<array{from: int, to: int|null, multiplier: float}>>
     */
    public static function statutoryRates(): array
    {
        return [
            'workday' => [
                ['from' => 1, 'to' => 1, 'multiplier' => 1.5],
                ['from' => 2, 'to' => null, 'multiplier' => 2.0],
            ],
            'holiday' => [
                ['from' => 1, 'to' => 7, 'multiplier' => 2.0],
                ['from' => 8, 'to' => 8, 'multiplier' => 3.0],
                ['from' => 9, 'to' => null, 'multiplier' => 4.0],
            ],
        ];
    }

    /**
     * How many hourly wages a stretch of overtime is worth — the multipliers of
     * every hour it spans, added up. A partial hour counts for its fraction, so
     * 1,5 hours on a workday is 1,5 + (0,5 x 2) = 2,5 hourly wages.
     *
     * @param  array<string, list<array{from: int, to: int|null, multiplier: float}>>  $rates
     */
    public static function multiplierFor(array $rates, string $dayType, float $hours): float
    {
        $bands = $rates[$dayType] ?? $rates['workday'] ?? [];

        if ($bands === [] || $hours <= 0) {
            return 0.0;
        }

        $total = 0.0;
        $remaining = min($hours, self::HOUR_CEILING);

        for ($hour = 1; $remaining > 0 && $hour <= self::HOUR_CEILING; $hour++) {
            $portion = min(1.0, $remaining);
            $total += $portion * self::bandMultiplier($bands, $hour);
            $remaining -= $portion;
        }

        return $total;
    }

    /**
     * Payable hours for a stretch actually worked, under the tenant's rounding
     * rule: the duration is cut down to whole blocks of `roundingMinutes`, and
     * a stretch shorter than one block is not paid at all.
     *
     * With a 30-minute block that reads as the rule most companies write down —
     * under 30 minutes counts for nothing, 45 minutes for half an hour, 89 for
     * one hour. A zero block means no rounding: the exact hours stand.
     */
    public static function roundHours(float $hours, int $roundingMinutes): float
    {
        if ($roundingMinutes <= 0 || $hours <= 0) {
            return max(0.0, $hours);
        }

        $blocks = (int) floor(round($hours * 60, 6) / $roundingMinutes);

        return round($blocks * $roundingMinutes / 60, 2);
    }

    /**
     * What a filed stretch is worth to the tenant, with the reason when it is
     * worth nothing — the single place the three intake paths (HR desk, ESS and
     * the mobile API) agree on how long an overtime request has to be.
     *
     * @return array{hours: float, error: string|null}
     */
    public static function payableHours(int $tenantId, float $rawHours): array
    {
        $rounding = (int) self::policyFor($tenantId)->rounding_minutes;

        if ($rawHours > OvertimeWindow::MAX_HOURS) {
            return [
                'hours' => 0.0,
                'error' => sprintf('Durasi lembur maksimal %s jam.', OvertimeWindow::MAX_HOURS),
            ];
        }

        $hours = self::roundHours($rawHours, $rounding);

        if ($rounding > 0) {
            return $hours > 0
                ? ['hours' => $hours, 'error' => null]
                : ['hours' => 0.0, 'error' => sprintf('Lembur kurang dari %d menit tidak dihitung.', $rounding)];
        }

        return $hours >= OvertimeWindow::MIN_HOURS
            ? ['hours' => $hours, 'error' => null]
            : [
                'hours' => 0.0,
                'error' => sprintf(
                    'Durasi lembur harus antara %s dan %s jam.',
                    OvertimeWindow::MIN_HOURS,
                    OvertimeWindow::MAX_HOURS,
                ),
            ];
    }

    /**
     * The overtime basis: the components flagged as overtime basis, raised to
     * `ratio` of monthly earnings when they fall short of it. PP 35/2021 Pasal
     * 30 sets that floor so an employer cannot shrink the basis by paying most
     * of the wage as variable allowances.
     *
     * @return array{basis: float, floored: bool}
     */
    public static function basisFor(float $fixedBasis, float $monthlyEarnings, float $ratio): array
    {
        $floor = max(0.0, $monthlyEarnings) * $ratio;

        if ($fixedBasis <= 0.0) {
            return ['basis' => $floor, 'floored' => $floor > 0.0];
        }

        return $fixedBasis < $floor
            ? ['basis' => $floor, 'floored' => true]
            : ['basis' => $fixedBasis, 'floored' => false];
    }

    /**
     * Hours the employee already has on the books for the ISO week containing
     * `$date`, so a new request can be judged against the weekly ceiling.
     * Rejected requests do not count; a pending one does, because approving it
     * later must not push the week over the limit.
     */
    public static function weeklyHours(int $tenantId, int $employeeId, Carbon $date, ?int $exceptId = null): float
    {
        return (float) OvertimeRequest::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->whereBetween('date', [
                $date->copy()->startOfWeek()->toDateString(),
                $date->copy()->endOfWeek()->toDateString(),
            ])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->sum('hours');
    }

    /**
     * Hours the employee already has on the books for `$date` itself.
     */
    public static function dailyHours(int $tenantId, int $employeeId, Carbon $date, ?int $exceptId = null): float
    {
        return (float) OvertimeRequest::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('date', $date->toDateString())
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->sum('hours');
    }

    /**
     * Why a request cannot be filed, or null when it is within the ceilings.
     * Returns the message ready for a validation error.
     */
    public static function limitViolation(
        int $tenantId,
        int $employeeId,
        Carbon $date,
        float $hours,
        ?int $exceptId = null,
    ): ?string {
        $policy = self::policyFor($tenantId);

        if (! $policy->enforce_hour_limits) {
            return null;
        }

        $maxDay = (float) $policy->max_hours_per_day;
        $maxWeek = (float) $policy->max_hours_per_week;

        $dayTotal = self::dailyHours($tenantId, $employeeId, $date, $exceptId) + $hours;

        if ($maxDay > 0 && $dayTotal > $maxDay) {
            return sprintf(
                'Maksimal %s jam lembur per hari (PP 35/2021). Pengajuan ini membuat total hari itu menjadi %s jam.',
                self::hourLabel($maxDay),
                self::hourLabel($dayTotal),
            );
        }

        $weekTotal = self::weeklyHours($tenantId, $employeeId, $date, $exceptId) + $hours;

        if ($maxWeek > 0 && $weekTotal > $maxWeek) {
            return sprintf(
                'Maksimal %s jam lembur per minggu (PP 35/2021). Pengajuan ini membuat total minggu itu menjadi %s jam.',
                self::hourLabel($maxWeek),
                self::hourLabel($weekTotal),
            );
        }

        return null;
    }

    /**
     * Normalise a submitted day type to one the multiplier table knows.
     */
    public static function normaliseDayType(?string $dayType): string
    {
        return array_key_exists((string) $dayType, self::DAY_TYPES) ? (string) $dayType : 'workday';
    }

    /**
     * Whether a date falls on a weekend, used to preselect the day type when a
     * request is filed rather than making the filer classify their own Sunday.
     */
    public static function suggestDayType(Carbon $date): string
    {
        return $date->isWeekend() ? 'holiday' : 'workday';
    }

    /**
     * The multiplier that applies to a specific hour of the shift.
     *
     * @param  list<array{from: int, to: int|null, multiplier: float}>  $bands
     */
    private static function bandMultiplier(array $bands, int $hour): float
    {
        $applicable = 0.0;

        foreach ($bands as $band) {
            if ($hour >= $band['from'] && ($band['to'] === null || $hour <= $band['to'])) {
                $applicable = $band['multiplier'];
            }
        }

        return $applicable;
    }

    /**
     * Trim a trailing ",00" so a limit reads "4 jam", not "4,00 jam".
     */
    private static function hourLabel(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, ',', '.'), '0'), ',');
    }

    /**
     * The day-type options an overtime form offers.
     *
     * @return Collection<int, array{value: string, label: string}>
     */
    public static function dayTypeOptions(): Collection
    {
        return collect(self::DAY_TYPES)
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values();
    }
}
