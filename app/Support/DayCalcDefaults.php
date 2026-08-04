<?php

namespace App\Support;

use App\Models\DayCalcMethod;

/**
 * The "Perhitungan Hari" methods every tenant starts with.
 *
 * Proration is switched off entirely when a Master Gaji has no method and no
 * divisor: the factor comes back null and every component is paid in full. A
 * tenant that had to invent these rows before their first payroll therefore ran
 * that payroll unprorated without being told, so the three ordinary bases ship
 * with the tenant instead.
 *
 * They are a starting point, not a policy — a tenant edits the divisors or adds
 * their own from the Perhitungan Hari screen.
 */
final class DayCalcDefaults
{
    /**
     * code => [name, basis, divisor, description].
     *
     * `absen` carries no divisor: it prorates against days actually attended,
     * which is a count, not a fixed denominator.
     *
     * @var array<string, array{0: string, 1: string, 2: int|null, 3: string}>
     */
    private const METHODS = [
        'HK-22' => ['Hari Kerja 22', 'hari_kerja', 22, 'Prorata berdasar 22 hari kerja (Senin–Jumat)'],
        'HK-25' => ['Hari Kalender 25', 'hari_kalender', 25, 'Prorata berdasar 25 hari kalender'],
        'ABSEN' => ['Berdasar Absen', 'absen', null, 'Prorata mengikuti jumlah hari hadir'],
    ];

    /**
     * Give a tenant the standard methods. Safe to re-run: a code the tenant
     * already has is left exactly as they edited it.
     */
    public static function seedDefaultsFor(int $tenantId): void
    {
        foreach (self::METHODS as $code => [$name, $basis, $divisor, $description]) {
            DayCalcMethod::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                [
                    'name' => $name,
                    'basis' => $basis,
                    'divisor' => $divisor,
                    'description' => $description,
                    'is_active' => true,
                ],
            );
        }
    }
}
