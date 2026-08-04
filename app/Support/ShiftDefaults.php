<?php

namespace App\Support;

use App\Models\RosterPattern;
use App\Models\Shift;

/**
 * The rotation shifts and templates every tenant starts with.
 *
 * M/A/N/O is the client's own legend — Morning 07:00–15:00, Afternoon
 * 15:00–23:00, Night 23:00–07:00, and Off as a rostered day carrying no shift.
 * The engine has understood it all along; only the demo tenant was ever given
 * the rows, so a real tenant opened the roster with one office shift and no way
 * to express a rotation without building the whole legend by hand first.
 *
 * Night deliberately ends before it starts: that is how the roster recognises a
 * shift running past midnight, and it is the case attendance has to get right.
 */
final class ShiftDefaults
{
    /**
     * code => [name, start, end].
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const SHIFTS = [
        'M' => ['Pagi (Morning)', '07:00', '15:00'],
        'A' => ['Siang (Afternoon)', '15:00', '23:00'],
        'N' => ['Malam (Night)', '23:00', '07:00'],
        'D12' => ['Siang 12 Jam', '07:00', '19:00'],
        'N12' => ['Malam 12 Jam', '19:00', '07:00'],
    ];

    /**
     * code => [name, industry, [[shift code or null for a day off, days], ...]].
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, array{0: string|null, 1: int}>}>
     */
    private const PATTERNS = [
        'OFFICE' => ['Office', 'Perkantoran', [['M', 5], [null, 2]]],
        'MANUFACTURING-3' => ['Manufacturing', 'Pabrik', [['M', 3], ['A', 3], ['N', 3], [null, 2]]],
        'MANUFACTURING-2' => ['Manufacturing 24 Jam', 'Pabrik 24 Jam', [['M', 2], ['A', 2], ['N', 2], [null, 2]]],
        'WAREHOUSE' => ['Warehouse', 'Logistik', [['M', 4], [null, 2]]],
        'SECURITY' => ['Security 12 Jam', 'Security', [['D12', 1], ['N12', 1]]],
        'HOSPITAL' => ['Hospital', 'Rumah Sakit', [['M', 1], ['A', 1], ['N', 1], [null, 1]]],
        'MINING' => ['Mining', 'Tambang', [['M', 14], [null, 14]]],
        'OILGAS-14' => ['Oil & Gas 14/14', 'Migas', [['M', 14], [null, 14]]],
        'OILGAS-28' => ['Oil & Gas 28/28', 'Migas', [['M', 28], [null, 28]]],
        'OFFSHORE' => ['Offshore', 'Offshore', [['M', 28], [null, 28]]],
    ];

    /**
     * Give a tenant the rotation legend and the templates built from it. Safe to
     * re-run: a code the tenant already has is left as they edited it, and a
     * pattern that already has steps is not rebuilt.
     */
    public static function seedDefaultsFor(int $tenantId): void
    {
        foreach (self::SHIFTS as $code => [$name, $start, $end]) {
            Shift::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                [
                    'name' => $name,
                    'start_time' => $start,
                    'end_time' => $end,
                    'late_tolerance_minutes' => 15,
                    'status' => 'active',
                ],
            );
        }

        $shiftIds = Shift::forTenant($tenantId)->pluck('id', 'code');

        foreach (self::PATTERNS as $code => [$name, $industry, $cycle]) {
            $pattern = RosterPattern::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                ['name' => $name, 'industry' => $industry, 'status' => 'active'],
            );

            if ($pattern->steps()->exists()) {
                continue;
            }

            foreach ($cycle as $position => [$shiftCode, $days]) {
                $pattern->steps()->create([
                    'tenant_id' => $tenantId,
                    'position' => $position,
                    'shift_id' => $shiftCode !== null ? $shiftIds->get($shiftCode) : null,
                    'days' => $days,
                ]);
            }
        }
    }
}
